<?php

namespace App\Services\Line;

use App\Models\Group;
use App\Models\Shortcut;
use App\Models\User;
use App\Models\Category;
use App\Services\ConnectionService;
use App\Services\GroupService;
use App\Services\TransactionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LineCommandDispatcher
{
    public function __construct(
        private LineService $lineService,
        private MessageParser $messageParser,
        private FlexMessageBuilder $flexBuilder,
        private ConnectionService $connectionService,
        private GroupService $groupService,
        private TransactionService $transactionService,
    ) {}

    /**
     * Route text message based on parsed type.
     */
    public function dispatchMessage(ParsedMessage $parsed, ?User $user, string $lineUserId, string $replyToken, ?Group $group = null): void
    {
        match (true) {
            $parsed->isConnectionCode() => $this->handleConnectionCode($parsed, $lineUserId, $replyToken),
            $parsed->isCommand()        => $this->handleCommand($parsed, $user, $lineUserId, $replyToken, $group),
            $parsed->isTransaction()    => $this->handleTransaction($parsed, $user, $replyToken, $group),
            default                     => $this->handleUnknownMessage($parsed, $user, $replyToken),
        };
    }

    private function handleConnectionCode(ParsedMessage $parsed, string $lineUserId, string $replyToken): void
    {
        $result = $this->connectionService->connectWithCode($parsed->connectionCode, $lineUserId);

        if ($result['success']) {
            $flexContents = $this->flexBuilder->connectionSuccessCard($result['user']->name);
            $this->lineService->replyFlex($replyToken, 'เชื่อมต่อสำเร็จ!', $flexContents);
        } else {
            $flexContents = $this->flexBuilder->errorCard(
                $result['message'],
                'กรุณาเข้าเว็บไซต์เพื่อขอรหัสใหม่'
            );
            $this->lineService->replyFlex($replyToken, 'เกิดข้อผิดพลาด', $flexContents);
        }
    }

    private function handleCommand(
        ParsedMessage $parsed,
        ?User $user,
        string $lineUserId,
        string $replyToken,
        ?Group $group = null
    ): void {
        match ($parsed->command) {
            'help'         => $this->handleHelpCommand($replyToken),
            'status'       => $this->handleStatusCommand($user, $lineUserId, $replyToken),
            'shortcuts'    => $this->handleShortcutsCommand($user, $replyToken),
            'categories'   => $this->handleCategoriesCommand($user, $replyToken),
            'summary_today', 'summary_week', 'summary_month', 'summary_all' =>
                $this->handleSummaryCommand($parsed->command, $user, $replyToken, $group),
            'stats'        => $this->handleStatsCommand($user, $replyToken, $group),
            'cancel'       => $this->handleCancelCommand($user, $replyToken, $group),
            'recent'       => $this->handleRecentCommand($user, $replyToken, $group),
            'record'       => $this->handleRecordCommand($replyToken),
            'clear'        => $this->handleClearCommand($user, $replyToken, $group),
            'confirm'      => $this->handleConfirmClearCommand($user, $lineUserId, $replyToken, $group),
            'rename_group' => $this->handleRenameGroupCommand($parsed->commandArgument, $group, $replyToken),
            'delete_group' => $this->handleDeleteGroupCommand($group, $replyToken),
            default        => $this->handleUnknownCommand($parsed->rawMessage, $replyToken),
        };
    }

    private function handleHelpCommand(string $replyToken): void
    {
        $flexContents = $this->flexBuilder->helpCard();
        $this->lineService->replyFlex($replyToken, 'คู่มือใช้งาน จดตังค์', $flexContents);
    }

    private function handleStatusCommand(?User $user, string $lineUserId, string $replyToken): void
    {
        if (!$user) {
            $this->lineService->replyText(
                $replyToken,
                "❌ บัญชี LINE ของคุณยังไม่ได้เชื่อมต่อกับระบบ\n\n" .
                "กรุณาสมัครสมาชิกที่เว็บไซต์แล้วพิมพ์รหัส CONNECT-XXXXXX เพื่อเชื่อมต่อ"
            );
            return;
        }

        $statusText = "✅ สถานะการเชื่อมต่อ\n\n" .
            "👤 ชื่อ: {$user->name}\n" .
            "📧 อีเมล: " . ($user->email ?? 'ไม่ระบุ') . "\n" .
            "🔗 LINE: เชื่อมต่อแล้ว";

        $this->lineService->replyText($replyToken, $statusText);
    }

    private function handleShortcutsCommand(?User $user, string $replyToken): void
    {
        if (!$user) {
            $this->lineService->replyText(
                $replyToken,
                "❌ กรุณาเชื่อมต่อบัญชีก่อนใช้งาน\n\nพิมพ์ /help เพื่อดูวิธีเชื่อมต่อ"
            );
            return;
        }

        $shortcutObjects = Shortcut::where('user_id', $user->id)
            ->with('category')
            ->orderBy('created_at', 'desc')
            ->get();

        $flexContents = $this->flexBuilder->shortcutsCard($shortcutObjects->all());
        $this->lineService->replyFlex($replyToken, 'คำสั่งลัดของคุณ', $flexContents);
    }

    private function handleCategoriesCommand(?User $user, string $replyToken): void
    {
        $categories = Category::where(function ($query) use ($user) {
            $query->where('is_default', true);
            if ($user) {
                $query->orWhere('user_id', $user->id);
            }
        })
            ->orderBy('sort_order')
            ->get();

        $incomeCategories  = $categories->where('type', \App\Enums\TransactionType::INCOME)->values();
        $expenseCategories = $categories->where('type', \App\Enums\TransactionType::EXPENSE)->values();

        $flexContents = $this->flexBuilder->categoriesCard(
            $incomeCategories->all(),
            $expenseCategories->all()
        );
        $this->lineService->replyFlex($replyToken, 'หมวดหมู่', $flexContents);
    }

    private function handleSummaryCommand(string $command, ?User $user, string $replyToken, ?Group $group = null): void
    {
        if (!$user && !$group) {
            $this->lineService->replyText(
                $replyToken,
                "❌ กรุณาเชื่อมต่อบัญชีก่อนใช้งาน\n\nพิมพ์ /help เพื่อดูวิธีเชื่อมต่อ"
            );
            return;
        }

        $summary = $this->transactionService->getSummary($user, $command, $group?->id);

        $flexContents = $this->flexBuilder->summaryCard(
            totalIncome: $summary['totalIncome'],
            totalExpense: $summary['totalExpense'],
            periodLabel: $summary['periodLabel'],
            periodDetail: $summary['periodDetail']
        );
        
        $this->lineService->replyFlex($replyToken, "สรุปยอด{$summary['periodLabel']}", $flexContents);
    }

    private function handleStatsCommand(?User $user, string $replyToken, ?Group $group = null): void
    {
        if (!$user && !$group) {
            $this->lineService->replyText(
                $replyToken,
                "❌ กรุณาเชื่อมต่อบัญชีก่อนใช้งาน\n\nพิมพ์ /help เพื่อดูวิธีเชื่อมต่อ"
            );
            return;
        }

        $stats = $this->transactionService->getStatsByCategory($user, 'summary_month', $group?->id);

        $flexContents = $this->flexBuilder->statsCard(
            incomeByCategory: $stats['incomeByCategory'],
            expenseByCategory: $stats['expenseByCategory'],
            totalIncome: $stats['totalIncome'],
            totalExpense: $stats['totalExpense'],
            periodLabel: $stats['periodLabel']
        );
        
        $this->lineService->replyFlex($replyToken, "สถิติ{$stats['periodLabel']}", $flexContents);
    }

    private function handleCancelCommand(?User $user, string $replyToken, ?Group $group = null): void
    {
        if (!$user && !$group) {
            $this->lineService->replyText($replyToken, "❌ กรุณาเชื่อมต่อบัญชีก่อนใช้งาน");
            return;
        }

        $deleted = $this->transactionService->cancelLast($user, $group?->id);

        if ($deleted) {
            $flexContents = $this->flexBuilder->cancelSuccessCard($deleted);
            $this->lineService->replyFlex($replyToken, 'ยกเลิกรายการสำเร็จ', $flexContents);
        } else {
            $this->lineService->replyText($replyToken, "ไม่มีรายการที่จะยกเลิก");
        }
    }

    private function handleRecentCommand(?User $user, string $replyToken, ?Group $group = null): void
    {
        if (!$user && !$group) {
            $this->lineService->replyText($replyToken, "❌ กรุณาเชื่อมต่อบัญชีก่อนใช้งาน");
            return;
        }

        $transactions = $this->transactionService->getRecent($user, $group?->id);

        $flexContents = $this->flexBuilder->transactionListCard($transactions->all(), 'รายการล่าสุด');
        $this->lineService->replyFlex($replyToken, 'รายการล่าสุด', $flexContents);
    }

    private function handleRecordCommand(string $replyToken): void
    {
        $this->lineService->replyText(
            $replyToken,
            "📝 บันทึกรายการ\n\n" .
            "รูปแบบ: [คำสั่งลัด] [จำนวน] [หมายเหตุ]\n\n" .
            "ตัวอย่าง:\n" .
            "• รายรับ: เงินเดือน 5000\n" .
            "• รายจ่าย: อาหาร 150 ข้าวมันไก่\n\n" .
            "พิมพ์ /คำสั่ง เพื่อดูคำสั่งลัดของคุณ"
        );
    }

    private function handleClearCommand(?User $user, string $replyToken, ?Group $group = null): void
    {
        if (!$user && !$group) {
            $this->lineService->replyText($replyToken, "❌ กรุณาเชื่อมต่อบัญชีก่อนใช้งาน");
            return;
        }

        $preview = $this->transactionService->getSummary($user, 'summary_month', $group?->id);
        $cacheKey = $this->buildClearConfirmCacheKey($user, $group);

        Cache::put($cacheKey, true, now()->addSeconds(60));

        $this->lineService->replyText(
            $replyToken,
            "⚠️ ยืนยันการเคลียร์ยอดเดือนนี้\n\n" .
            "📊 รายได้: ฿" . number_format($preview['totalIncome'], 2) . "\n" .
            "📊 รายจ่าย: ฿" . number_format($preview['totalExpense'], 2) . "\n\n" .
            "ข้อมูลทั้งหมดจะถูกลบถาวร\n" .
            "พิมพ์ /ยืนยัน ภายใน 60 วินาที เพื่อยืนยัน"
        );
    }

    private function handleConfirmClearCommand(?User $user, string $lineUserId, string $replyToken, ?Group $group = null): void
    {
        if (!$user && !$group) {
            $this->lineService->replyText($replyToken, "❌ กรุณาเชื่อมต่อบัญชีก่อนใช้งาน");
            return;
        }

        $cacheKey = $this->buildClearConfirmCacheKey($user, $group);

        if (!Cache::has($cacheKey)) {
            $this->lineService->replyText(
                $replyToken,
                "ℹ️ ไม่มีคำสั่งที่รอการยืนยัน หรือหมดเวลาแล้ว\n\nพิมพ์ /เคลียร์ยอด เพื่อเริ่มใหม่"
            );
            return;
        }

        Cache::forget($cacheKey);
        $count = $this->transactionService->clearPeriod($user, 'summary_month', $group?->id);

        if ($count > 0) {
            $this->lineService->replyText(
                $replyToken,
                "🗑️ เคลียร์ยอดเดือนนี้สำเร็จ\n\nลบรายการทั้งหมด {$count} รายการแล้ว"
            );
        } else {
            $this->lineService->replyText($replyToken, "ℹ️ ไม่มีรายการในเดือนนี้ที่จะเคลียร์");
        }
    }

    private function buildClearConfirmCacheKey(?User $user, ?Group $group): string
    {
        $scope = $group ? "group:{$group->id}" : "user:{$user->id}";
        return "clear_confirm:{$scope}";
    }

    private function handleRenameGroupCommand(?string $newName, ?Group $group, string $replyToken): void
    {
        if (!$group) {
            $this->lineService->replyText($replyToken, "❌ คำสั่งนี้ใช้ได้เฉพาะในกลุ่ม");
            return;
        }

        if (!$newName || trim($newName) === '') {
            $this->lineService->replyText($replyToken, "❌ กรุณาระบุชื่อกลุ่ม\n\nตัวอย่าง: /ชื่อกลุ่ม บ้านเรา");
            return;
        }

        $group = $this->groupService->renameGroup($group->line_group_id, trim($newName));

        $this->lineService->replyText($replyToken, "✅ เปลี่ยนชื่อกลุ่มเป็น \"{$group->name}\" แล้ว");
    }

    private function handleDeleteGroupCommand(?Group $group, string $replyToken): void
    {
        if (!$group) {
            $this->lineService->replyText($replyToken, "❌ คำสั่งนี้ใช้ได้เฉพาะในกลุ่ม");
            return;
        }

        $this->groupService->deactivateGroup($group->line_group_id);

        $this->lineService->replyText(
            $replyToken,
            "✅ ยกเลิกการเชื่อมต่อกลุ่มแล้ว\n\nข้อมูลรายการจะยังคงอยู่ในระบบ และบอทจะออกจากกลุ่มนี้"
        );

        $this->lineService->leaveGroup($group->line_group_id);
    }

    private function handleUnknownCommand(string $rawMessage, string $replyToken): void
    {
        $this->lineService->replyText(
            $replyToken,
            "❓ คำสั่งไม่รู้จัก: {$rawMessage}\n\nพิมพ์ /help เพื่อดูคำสั่งทั้งหมด"
        );
    }

    private function handleTransaction(ParsedMessage $parsed, ?User $user, string $replyToken, ?Group $group = null): void
    {
        if (!$user && !$group) {
            $this->lineService->replyText(
                $replyToken,
                "❌ กรุณาเชื่อมต่อบัญชีก่อนบันทึกรายการ\n\n" .
                "พิมพ์ /help เพื่อดูวิธีเชื่อมต่อ"
            );
            return;
        }

        if (!$user) {
            $this->lineService->replyText($replyToken, '❌ ไม่สามารถระบุผู้ส่งข้อความได้ กรุณาลองใหม่อีกครั้ง');
            return;
        }

        try {
            $result = $this->transactionService->createFromLine(
                user: $user,
                type: $parsed->transactionType,
                amount: $parsed->amount,
                categoryId: $parsed->category?->id,
                note: $parsed->note,
                groupId: $group?->id,
            );

            $transaction = $result['transaction'];
            $todayBalance = $result['todayBalance'];
            $accountName = $group?->name ?? 'ส่วนตัว';

            if ($parsed->isIncome()) {
                $flexContents = $this->flexBuilder->incomeRecordCard($transaction, $todayBalance, $accountName);
                $this->lineService->replyFlex($replyToken, 'บันทึกรายรับสำเร็จ', $flexContents);
            } else {
                $flexContents = $this->flexBuilder->expenseRecordCard($transaction, $todayBalance, $accountName);
                $this->lineService->replyFlex($replyToken, 'บันทึกรายจ่ายสำเร็จ', $flexContents);
            }
        } catch (\Exception $e) {
            Log::error('Failed to create transaction', [
                'error' => $e->getMessage(),
                'user_id' => $user?->id,
                'parsed' => [
                    'amount' => $parsed->amount,
                    'type' => $parsed->transactionType?->value,
                    'category' => $parsed->category?->name,
                ],
            ]);

            $flexContents = $this->flexBuilder->errorCard(
                'ไม่สามารถบันทึกรายการได้',
                'กรุณาลองใหม่อีกครั้ง หรือพิมพ์ /help เพื่อดูวิธีใช้งาน'
            );
            $this->lineService->replyFlex($replyToken, 'เกิดข้อผิดพลาด', $flexContents);
        }
    }

    private function handleUnknownMessage(ParsedMessage $parsed, ?User $user, string $replyToken): void
    {
        if ($this->messageParser->looksLikeTransaction($parsed->rawMessage)) {
            if (!$user) {
                $this->lineService->replyText(
                    $replyToken,
                    "❌ กรุณาเชื่อมต่อบัญชีก่อนบันทึกรายการ\n\n" .
                    "พิมพ์ /help เพื่อดูวิธีเชื่อมต่อ"
                );
            } else {
                $this->lineService->replyText(
                    $replyToken,
                    "❓ ไม่พบคำสั่งลัดที่ตรงกับ \"{$parsed->keyword}\"\n\n" .
                    "พิมพ์ /คำสั่ง เพื่อดูคำสั่งลัดของคุณ\n" .
                    "หรือสร้างคำสั่งลัดใหม่ได้ที่เว็บแอป"
                );
            }
        } else {
            $this->lineService->replyText(
                $replyToken,
                "❓ ไม่เข้าใจข้อความ\n\nพิมพ์ /help เพื่อดูวิธีใช้งาน"
            );
        }
    }
}
