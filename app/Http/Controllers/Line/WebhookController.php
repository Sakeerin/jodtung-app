<?php

namespace App\Http\Controllers\Line;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\LineConnection;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use LINE\Clients\MessagingApi\Api\MessagingApiApi;
use LINE\Clients\MessagingApi\Configuration;
use LINE\Clients\MessagingApi\Model\ReplyMessageRequest;
use LINE\Clients\MessagingApi\Model\TextMessage;
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
use GuzzleHttp\Client;

class WebhookController extends Controller
{
    private MessagingApiApi $messagingApi;

    public function __construct()
    {
        $config = new Configuration();
        $config->setAccessToken(config('services.line.channel_access_token'));

        $client = new Client();
        $this->messagingApi = new MessagingApiApi($client, $config);
    }

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
        $lineUserId = $source->getUserId();

        // Check for connection code
        if (preg_match('/^CONNECT-[A-Z0-9]{6}$/', $text)) {
            $this->handleConnectionCode($text, $lineUserId, $replyToken);
            return;
        }

        // Check for commands (starts with /)
        if (str_starts_with($text, '/')) {
            $this->handleCommand($text, $lineUserId, $replyToken);
            return;
        }

        // Handle as transaction (Phase 3)
        // For now, just echo back
        $this->replyText($replyToken, "รับข้อความ: $text\n\n(ระบบบันทึกรายการจะเปิดใช้งานในเฟส 3)");
    }

    /**
     * Handle connection code input.
     */
    private function handleConnectionCode(string $code, string $lineUserId, string $replyToken): void
    {
        $connection = LineConnection::where('connection_code', $code)
            ->where('is_connected', false)
            ->first();

        if (!$connection) {
            $this->replyText($replyToken, "❌ รหัสเชื่อมต่อไม่ถูกต้องหรือหมดอายุ\n\nกรุณาเข้าเว็บไซต์เพื่อขอรหัสใหม่");
            return;
        }

        // Check expiration
        if ($connection->isCodeExpired()) {
            $this->replyText($replyToken, "❌ รหัสเชื่อมต่อหมดอายุ\n\nกรุณาเข้าเว็บไซต์เพื่อขอรหัสใหม่");
            return;
        }

        // Connect LINE account
        $connection->update([
            'line_user_id' => $lineUserId,
            'is_connected' => true,
            'connected_at' => now(),
        ]);

        // Update user's line_user_id
        $connection->user->update([
            'line_user_id' => $lineUserId,
        ]);

        $userName = $connection->user->name;
        $this->replyText($replyToken, "✅ เชื่อมต่อสำเร็จ!\n\nสวัสดี {$userName} 👋\nคุณสามารถเริ่มบันทึกรายรับ-รายจ่ายได้แล้ว\n\nพิมพ์ /help เพื่อดูวิธีใช้งาน");
    }

    /**
     * Handle commands starting with /.
     */
    private function handleCommand(string $text, string $lineUserId, string $replyToken): void
    {
        $command = strtolower(trim($text));

        match (true) {
            $command === '/help' || $command === '/ช่วยเหลือ' => $this->handleHelpCommand($replyToken),
            $command === '/สถานะ' => $this->handleStatusCommand($lineUserId, $replyToken),
            default => $this->replyText($replyToken, "❓ คำสั่งไม่รู้จัก: $text\n\nพิมพ์ /help เพื่อดูคำสั่งทั้งหมด"),
        };
    }

    /**
     * Handle /help command.
     */
    private function handleHelpCommand(string $replyToken): void
    {
        $helpText = "📖 คู่มือใช้งาน จดตังค์\n\n"
            . "📝 บันทึกรายการ:\n"
            . "• รายรับ: พิมพ์ \"เงินเดือน 5000\"\n"
            . "• รายจ่าย: พิมพ์ \"🍔 150 ข้าวมันไก่\"\n\n"
            . "⌨️ คำสั่ง:\n"
            . "• /ยอดวันนี้ - ดูสรุปวันนี้\n"
            . "• /ยอดสัปดาห์ - ดูสรุปสัปดาห์\n"
            . "• /ยอดเดือนนี้ - ดูสรุปเดือน\n"
            . "• /สถิติ - ดูสถิติตามหมวดหมู่\n"
            . "• /ยกเลิก - ลบรายการล่าสุด\n"
            . "• /คำสั่ง - ดูคำสั่งลัดทั้งหมด\n"
            . "• /สถานะ - ดูสถานะการเชื่อมต่อ\n\n"
            . "🔗 เชื่อมต่อบัญชี:\n"
            . "พิมพ์ CONNECT-XXXXXX";

        $this->replyText($replyToken, $helpText);
    }

    /**
     * Handle /สถานะ command.
     */
    private function handleStatusCommand(string $lineUserId, string $replyToken): void
    {
        $user = User::where('line_user_id', $lineUserId)->first();

        if (!$user) {
            $this->replyText($replyToken, "❌ บัญชี LINE ของคุณยังไม่ได้เชื่อมต่อกับระบบ\n\nกรุณาสมัครสมาชิกที่เว็บไซต์แล้วพิมพ์รหัส CONNECT-XXXXXX เพื่อเชื่อมต่อ");
            return;
        }

        $statusText = "✅ สถานะการเชื่อมต่อ\n\n"
            . "👤 ชื่อ: {$user->name}\n"
            . "📧 อีเมล: {$user->email}\n"
            . "🔗 LINE: เชื่อมต่อแล้ว";

        $this->replyText($replyToken, $statusText);
    }

    /**
     * Handle follow event (new friend).
     */
    private function handleFollowEvent(FollowEvent $event): void
    {
        $replyToken = $event->getReplyToken();
        $lineUserId = $event->getSource()->getUserId();

        // Check if user already connected
        $user = User::where('line_user_id', $lineUserId)->first();

        if ($user) {
            $welcomeText = "👋 ยินดีต้อนรับกลับ {$user->name}!\n\n"
                . "คุณสามารถเริ่มบันทึกรายรับ-รายจ่ายได้เลย\n\n"
                . "พิมพ์ /help เพื่อดูวิธีใช้งาน";
        } else {
            $welcomeText = "👋 ยินดีต้อนรับสู่ จดตังค์!\n\n"
                . "บอทบันทึกรายรับ-รายจ่ายผ่าน LINE\n\n"
                . "🚀 เริ่มต้นใช้งาน:\n"
                . "1. สมัครสมาชิกที่เว็บไซต์\n"
                . "2. คัดลอกรหัส CONNECT-XXXXXX\n"
                . "3. พิมพ์รหัสในแชทนี้เพื่อเชื่อมต่อ\n\n"
                . "พิมพ์ /help เพื่อดูวิธีใช้งานเพิ่มเติม";
        }

        $this->replyText($replyToken, $welcomeText);
    }

    /**
     * Handle unfollow event (blocked).
     */
    private function handleUnfollowEvent(UnfollowEvent $event): void
    {
        $lineUserId = $event->getSource()->getUserId();

        Log::info('User unfollowed', ['line_user_id' => $lineUserId]);

        // Optional: Mark user as inactive or disconnect
    }

    /**
     * Handle join event (bot added to group).
     */
    private function handleJoinEvent(JoinEvent $event): void
    {
        $replyToken = $event->getReplyToken();
        $source = $event->getSource();
        $lineGroupId = $source->getGroupId();

        // Create or update group record
        Group::updateOrCreate(
            ['line_group_id' => $lineGroupId],
            ['name' => 'กลุ่ม', 'is_active' => true]
        );

        $welcomeText = "👋 สวัสดีครับ!\n\n"
            . "ผม จดตังค์ บอทบันทึกรายรับ-รายจ่าย\n\n"
            . "📝 วิธีใช้งานในกลุ่ม:\n"
            . "• บันทึกรายการ: \"🍔 150 ข้าวเย็น\"\n"
            . "• ดูสรุป: /ยอดเดือนนี้\n"
            . "• ตั้งชื่อกลุ่ม: /ชื่อกลุ่ม [ชื่อ]\n\n"
            . "พิมพ์ /help เพื่อดูคำสั่งทั้งหมด";

        $this->replyText($replyToken, $welcomeText);
    }

    /**
     * Handle leave event (bot removed from group).
     */
    private function handleLeaveEvent(LeaveEvent $event): void
    {
        $lineGroupId = $event->getSource()->getGroupId();

        // Mark group as inactive
        Group::where('line_group_id', $lineGroupId)->update(['is_active' => false]);

        Log::info('Bot left group', ['line_group_id' => $lineGroupId]);
    }

    /**
     * Reply with text message.
     */
    private function replyText(string $replyToken, string $text): void
    {
        try {
            $message = new TextMessage([
                'type' => 'text',
                'text' => $text,
            ]);

            $request = new ReplyMessageRequest([
                'replyToken' => $replyToken,
                'messages' => [$message],
            ]);

            $this->messagingApi->replyMessage($request);
        } catch (\Exception $e) {
            Log::error('Failed to reply message', [
                'error' => $e->getMessage(),
                'reply_token' => $replyToken,
            ]);
        }
    }
}
