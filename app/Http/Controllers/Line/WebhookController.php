<?php

namespace App\Http\Controllers\Line;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Group;
use App\Models\Shortcut;
use App\Models\User;
use App\Services\ConnectionService;
use App\Services\Line\FlexMessageBuilder;
use App\Services\Line\LineService;
use App\Services\Line\MessageParser;
use App\Services\Line\ParsedMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use LINE\Constants\HTTPHeader;
use LINE\Parser\EventRequestParser;
use LINE\Parser\Exception\InvalidEventRequestException;
use LINE\Parser\Exception\InvalidSignatureException;
use LINE\Webhook\Model\FollowEvent;
use LINE\Webhook\Model\JoinEvent;
use LINE\Webhook\Model\LeaveEvent;
use LINE\Webhook\Model\MessageEvent;
use LINE\Webhook\Model\TextMessageContent;
use LINE\Webhook\Model\UnfollowEvent;
use LINE\Webhook\Model\GroupSource;
use LINE\Webhook\Model\UserSource;

class WebhookController extends Controller
{
    public function __construct(
        private LineService $lineService,
        private MessageParser $messageParser,
        private FlexMessageBuilder $flexBuilder,
        private ConnectionService $connectionService,
    ) {}

    /**
     * Handle LINE webhook events.
     */
    public function handle(Request $request): JsonResponse
    {
        $channelSecret = config('services.line.channel_secret');
        $signature = $request->header(HTTPHeader::LINE_SIGNATURE);

        try {
            $parsedRequest = EventRequestParser::parseEventRequest(
                $request->getContent(),
                $channelSecret,
                $signature
            );
        } catch (InvalidSignatureException $e) {
            Log::error('Invalid LINE signature', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid signature'], 401);
        } catch (InvalidEventRequestException $e) {
            Log::error('Invalid LINE event request', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid event request'], 400);
        }

        foreach ($parsedRequest->getEvents() as $event) {
            $this->handleEvent($event);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Route event to appropriate handler.
     */
    private function handleEvent($event): void
    {
        match (true) {
            $event instanceof MessageEvent => $this->handleMessageEvent($event),
            $event instanceof FollowEvent => $this->handleFollowEvent($event),
            $event instanceof UnfollowEvent => $this->handleUnfollowEvent($event),
            $event instanceof JoinEvent => $this->handleJoinEvent($event),
            $event instanceof LeaveEvent => $this->handleLeaveEvent($event),
            default => Log::info('Unhandled event type', ['type' => get_class($event)]),
        };
    }

    /**
     * Handle text message events.
     */
    private function handleMessageEvent(MessageEvent $event): void
    {
        $message = $event->getMessage();

        if (!$message instanceof TextMessageContent) {
            return;
        }

        $text = trim($message->getText());
        $replyToken = $event->getReplyToken();
        $source = $event->getSource();
        
        // Get user ID based on source type
        $lineUserId = null;
        $lineGroupId = null;
        $isGroup = false;

        if ($source instanceof GroupSource) {
            $lineUserId = $source->getUserId();
            $lineGroupId = $source->getGroupId();
            $isGroup = true;
        } elseif ($source instanceof UserSource) {
            $lineUserId = $source->getUserId();
        }

        if (!$lineUserId) {
            Log::warning('Could not get user ID from message event');
            return;
        }

        // Find or create user
        $user = $this->connectionService->findUserByLineId($lineUserId);

        // Parse the message
        $parsed = $this->messageParser->parse($text, $user);

        // Handle based on parsed type
        match (true) {
            $parsed->isConnectionCode() => $this->handleConnectionCode($parsed, $lineUserId, $replyToken),
            $parsed->isCommand() => $this->handleCommand($parsed, $user, $lineUserId, $replyToken, $lineGroupId),
            $parsed->isTransaction() => $this->handleTransaction($parsed, $user, $replyToken, $lineGroupId),
            default => $this->handleUnknownMessage($parsed, $user, $replyToken),
        };
    }

    /**
     * Handle connection code input.
     */
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

    /**
     * Handle commands starting with /.
     */
    private function handleCommand(
        ParsedMessage $parsed,
        ?User $user,
        string $lineUserId,
        string $replyToken,
        ?string $lineGroupId = null
    ): void {
        match ($parsed->command) {
            'help' => $this->handleHelpCommand($replyToken),
            'status' => $this->handleStatusCommand($user, $lineUserId, $replyToken),
            'shortcuts' => $this->handleShortcutsCommand($user, $replyToken),
            'categories' => $this->handleCategoriesCommand($user, $replyToken),
            'summary_today', 'summary_week', 'summary_month', 'summary_all' => 
                $this->handleSummaryCommand($parsed->command, $user, $replyToken, $lineGroupId),
            'stats' => $this->handleStatsCommand($user, $replyToken, $lineGroupId),
            'cancel' => $this->handleCancelCommand($user, $replyToken, $lineGroupId),
            'recent' => $this->handleRecentCommand($user, $replyToken, $lineGroupId),
            'record' => $this->handleRecordCommand($replyToken),
            'rename_group' => $this->handleRenameGroupCommand($parsed->commandArgument, $lineGroupId, $replyToken),
            'delete_group' => $this->handleDeleteGroupCommand($lineGroupId, $replyToken),
            default => $this->handleUnknownCommand($parsed->rawMessage, $replyToken),
        };
    }

    /**
     * Handle /help command.
     */
    private function handleHelpCommand(string $replyToken): void
    {
        $flexContents = $this->flexBuilder->helpCard();
        $this->lineService->replyFlex($replyToken, 'คู่มือใช้งาน จดตังค์', $flexContents);
    }

    /**
     * Handle /สถานะ command.
     */
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

    /**
     * Handle /คำสั่ง command.
     */
    private function handleShortcutsCommand(?User $user, string $replyToken): void
    {
        if (!$user) {
            $this->lineService->replyText(
                $replyToken,
                "❌ กรุณาเชื่อมต่อบัญชีก่อนใช้งาน\n\nพิมพ์ /help เพื่อดูวิธีเชื่อมต่อ"
            );
            return;
        }

        $shortcuts = Shortcut::where('user_id', $user->id)
            ->with('category')
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();

        // Convert to objects for the flexBuilder
        $shortcutObjects = Shortcut::where('user_id', $user->id)
            ->with('category')
            ->orderBy('created_at', 'desc')
            ->get();

        $flexContents = $this->flexBuilder->shortcutsCard($shortcutObjects->all());
        $this->lineService->replyFlex($replyToken, 'คำสั่งลัดของคุณ', $flexContents);
    }

    /**
     * Handle /หมวดหมู่ command.
     */
    private function handleCategoriesCommand(?User $user, string $replyToken): void
    {
        // Get default categories and user's custom categories
        $incomeCategories = Category::where(function ($query) use ($user) {
            $query->where('is_default', true);
            if ($user) {
                $query->orWhere('user_id', $user->id);
            }
        })
            ->income()
            ->orderBy('sort_order')
            ->get();

        $expenseCategories = Category::where(function ($query) use ($user) {
            $query->where('is_default', true);
            if ($user) {
                $query->orWhere('user_id', $user->id);
            }
        })
            ->expense()
            ->orderBy('sort_order')
            ->get();

        $flexContents = $this->flexBuilder->categoriesCard(
            $incomeCategories->all(),
            $expenseCategories->all()
        );
        $this->lineService->replyFlex($replyToken, 'หมวดหมู่', $flexContents);
    }

    /**
     * Handle summary commands (/ยอดวันนี้, /ยอดสัปดาห์, /ยอดเดือนนี้).
     */
    private function handleSummaryCommand(
        string $command,
        ?User $user,
        string $replyToken,
        ?string $lineGroupId = null
    ): void {
        if (!$user && !$lineGroupId) {
            $this->lineService->replyText(
                $replyToken,
                "❌ กรุณาเชื่อมต่อบัญชีก่อนใช้งาน\n\nพิมพ์ /help เพื่อดูวิธีเชื่อมต่อ"
            );
            return;
        }

        // This will be fully implemented in Phase 3
        // For now, return a placeholder response
        $periodLabel = match ($command) {
            'summary_today' => 'วันนี้',
            'summary_week' => 'สัปดาห์นี้',
            'summary_month' => 'เดือนนี้',
            'summary_all' => 'ทั้งหมด',
            default => 'เดือนนี้',
        };

        // Placeholder data - will be replaced with actual calculation in Phase 3
        $flexContents = $this->flexBuilder->summaryCard(
            totalIncome: 0,
            totalExpense: 0,
            periodLabel: $periodLabel,
            periodDetail: null
        );
        
        $this->lineService->replyFlex($replyToken, "สรุปยอด{$periodLabel}", $flexContents);
    }

    /**
     * Handle /สถิติ command.
     */
    private function handleStatsCommand(?User $user, string $replyToken, ?string $lineGroupId = null): void
    {
        if (!$user && !$lineGroupId) {
            $this->lineService->replyText(
                $replyToken,
                "❌ กรุณาเชื่อมต่อบัญชีก่อนใช้งาน\n\nพิมพ์ /help เพื่อดูวิธีเชื่อมต่อ"
            );
            return;
        }

        // This will be fully implemented in Phase 3
        // For now, return a placeholder response
        $flexContents = $this->flexBuilder->statsCard(
            incomeByCategory: [],
            expenseByCategory: [],
            totalIncome: 0,
            totalExpense: 0,
            periodLabel: 'เดือนนี้'
        );
        
        $this->lineService->replyFlex($replyToken, 'สถิติเดือนนี้', $flexContents);
    }

    /**
     * Handle /ยกเลิก command.
     */
    private function handleCancelCommand(?User $user, string $replyToken, ?string $lineGroupId = null): void
    {
        if (!$user && !$lineGroupId) {
            $this->lineService->replyText(
                $replyToken,
                "❌ กรุณาเชื่อมต่อบัญชีก่อนใช้งาน"
            );
            return;
        }

        // This will be fully implemented in Phase 3
        $this->lineService->replyText($replyToken, "ไม่มีรายการที่จะยกเลิก");
    }

    /**
     * Handle /รายการล่าสุด command.
     */
    private function handleRecentCommand(?User $user, string $replyToken, ?string $lineGroupId = null): void
    {
        if (!$user && !$lineGroupId) {
            $this->lineService->replyText(
                $replyToken,
                "❌ กรุณาเชื่อมต่อบัญชีก่อนใช้งาน"
            );
            return;
        }

        // This will be fully implemented in Phase 3
        $flexContents = $this->flexBuilder->transactionListCard([], 'รายการล่าสุด');
        $this->lineService->replyFlex($replyToken, 'รายการล่าสุด', $flexContents);
    }

    /**
     * Handle /บันทึก command.
     */
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

    /**
     * Handle /ชื่อกลุ่ม command.
     */
    private function handleRenameGroupCommand(?string $newName, ?string $lineGroupId, string $replyToken): void
    {
        if (!$lineGroupId) {
            $this->lineService->replyText($replyToken, "❌ คำสั่งนี้ใช้ได้เฉพาะในกลุ่ม");
            return;
        }

        if (!$newName || trim($newName) === '') {
            $this->lineService->replyText($replyToken, "❌ กรุณาระบุชื่อกลุ่ม\n\nตัวอย่าง: /ชื่อกลุ่ม บ้านเรา");
            return;
        }

        $group = Group::where('line_group_id', $lineGroupId)->first();
        if (!$group) {
            $group = Group::create([
                'line_group_id' => $lineGroupId,
                'name' => trim($newName),
                'is_active' => true,
            ]);
        } else {
            $group->update(['name' => trim($newName)]);
        }

        $this->lineService->replyText($replyToken, "✅ เปลี่ยนชื่อกลุ่มเป็น \"{$group->name}\" แล้ว");
    }

    /**
     * Handle /ลบกลุ่ม command.
     */
    private function handleDeleteGroupCommand(?string $lineGroupId, string $replyToken): void
    {
        if (!$lineGroupId) {
            $this->lineService->replyText($replyToken, "❌ คำสั่งนี้ใช้ได้เฉพาะในกลุ่ม");
            return;
        }

        Group::where('line_group_id', $lineGroupId)->update(['is_active' => false]);

        $this->lineService->replyText(
            $replyToken,
            "✅ ยกเลิกการเชื่อมต่อกลุ่มแล้ว\n\nข้อมูลรายการจะยังคงอยู่ในระบบ"
        );
    }

    /**
     * Handle unknown command.
     */
    private function handleUnknownCommand(string $rawMessage, string $replyToken): void
    {
        $this->lineService->replyText(
            $replyToken,
            "❓ คำสั่งไม่รู้จัก: {$rawMessage}\n\nพิมพ์ /help เพื่อดูคำสั่งทั้งหมด"
        );
    }

    /**
     * Handle transaction message.
     * This will be fully implemented in Phase 3.
     */
    private function handleTransaction(
        ParsedMessage $parsed,
        ?User $user,
        string $replyToken,
        ?string $lineGroupId = null
    ): void {
        if (!$user && !$lineGroupId) {
            $this->lineService->replyText(
                $replyToken,
                "❌ กรุณาเชื่อมต่อบัญชีก่อนบันทึกรายการ\n\n" .
                "พิมพ์ /help เพื่อดูวิธีเชื่อมต่อ"
            );
            return;
        }

        // Phase 3 will implement actual transaction creation
        // For now, show a preview of what will be recorded
        $typeLabel = $parsed->isIncome() ? 'รายรับ' : 'รายจ่าย';
        $categoryName = $parsed->category?->name ?? 'ไม่ระบุ';
        $categoryEmoji = $parsed->category?->emoji ?? '';
        
        $message = "🔜 จะบันทึก{$typeLabel}\n\n" .
            "{$categoryEmoji} {$categoryName}\n" .
            "฿" . number_format($parsed->amount, 0) . "\n" .
            ($parsed->note ? "หมายเหตุ: {$parsed->note}\n\n" : "\n") .
            "(ระบบบันทึกจะเปิดใช้งานในเฟส 3)";

        $this->lineService->replyText($replyToken, $message);
    }

    /**
     * Handle unknown message.
     */
    private function handleUnknownMessage(ParsedMessage $parsed, ?User $user, string $replyToken): void
    {
        // Check if it looks like a transaction attempt
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

    /**
     * Handle follow event (new friend).
     */
    private function handleFollowEvent(FollowEvent $event): void
    {
        $replyToken = $event->getReplyToken();
        $source = $event->getSource();
        
        $lineUserId = null;
        if ($source instanceof UserSource) {
            $lineUserId = $source->getUserId();
        }

        if (!$lineUserId) {
            return;
        }

        // Check if user already connected
        $user = $this->connectionService->findUserByLineId($lineUserId);

        if ($user) {
            $flexContents = $this->flexBuilder->welcomeBackCard($user->name);
            $this->lineService->replyFlex($replyToken, 'ยินดีต้อนรับกลับ!', $flexContents);
        } else {
            $flexContents = $this->flexBuilder->welcomeCard();
            $this->lineService->replyFlex($replyToken, 'ยินดีต้อนรับสู่ จดตังค์!', $flexContents);
        }
    }

    /**
     * Handle unfollow event (blocked).
     */
    private function handleUnfollowEvent(UnfollowEvent $event): void
    {
        $source = $event->getSource();
        
        $lineUserId = null;
        if ($source instanceof UserSource) {
            $lineUserId = $source->getUserId();
        }

        if ($lineUserId) {
            Log::info('User unfollowed', ['line_user_id' => $lineUserId]);
        }
    }

    /**
     * Handle join event (bot added to group).
     */
    private function handleJoinEvent(JoinEvent $event): void
    {
        $replyToken = $event->getReplyToken();
        $source = $event->getSource();
        
        $lineGroupId = null;
        if ($source instanceof GroupSource) {
            $lineGroupId = $source->getGroupId();
        }

        if (!$lineGroupId) {
            return;
        }

        // Get group name from LINE API if possible
        $groupName = null;
        $groupSummary = $this->lineService->getGroupSummary($lineGroupId);
        if ($groupSummary) {
            $groupName = $groupSummary['groupName'];
        }

        // Create or update group record
        Group::updateOrCreate(
            ['line_group_id' => $lineGroupId],
            ['name' => $groupName ?? 'กลุ่ม', 'is_active' => true]
        );

        $flexContents = $this->flexBuilder->groupWelcomeCard($groupName);
        $this->lineService->replyFlex($replyToken, 'สวัสดีครับ!', $flexContents);
    }

    /**
     * Handle leave event (bot removed from group).
     */
    private function handleLeaveEvent(LeaveEvent $event): void
    {
        $source = $event->getSource();
        
        $lineGroupId = null;
        if ($source instanceof GroupSource) {
            $lineGroupId = $source->getGroupId();
        }

        if ($lineGroupId) {
            // Mark group as inactive
            Group::where('line_group_id', $lineGroupId)->update(['is_active' => false]);
            Log::info('Bot left group', ['line_group_id' => $lineGroupId]);
        }
    }
}
