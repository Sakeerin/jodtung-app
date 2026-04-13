<?php

namespace App\Services\Line;

use App\Models\Group;
use App\Models\User;
use App\Services\ConnectionService;
use App\Services\GroupService;
use Illuminate\Support\Facades\Log;
use LINE\Webhook\Model\FollowEvent;
use LINE\Webhook\Model\JoinEvent;
use LINE\Webhook\Model\LeaveEvent;
use LINE\Webhook\Model\MessageEvent;
use LINE\Webhook\Model\TextMessageContent;
use LINE\Webhook\Model\UnfollowEvent;
use LINE\Webhook\Model\GroupSource;
use LINE\Webhook\Model\UserSource;

class LineEventHandler
{
    public function __construct(
        private LineService $lineService,
        private MessageParser $messageParser,
        private FlexMessageBuilder $flexBuilder,
        private ConnectionService $connectionService,
        private GroupService $groupService,
        private LineCommandDispatcher $commandDispatcher,
    ) {}

    /**
     * Route event to appropriate handler.
     */
    public function handleEvent($event): void
    {
        try {
            match (true) {
                $event instanceof MessageEvent   => $this->handleMessageEvent($event),
                $event instanceof FollowEvent    => $this->handleFollowEvent($event),
                $event instanceof UnfollowEvent  => $this->handleUnfollowEvent($event),
                $event instanceof JoinEvent      => $this->handleJoinEvent($event),
                $event instanceof LeaveEvent     => $this->handleLeaveEvent($event),
                default => Log::info('Unhandled event type', ['type' => get_class($event)]),
            };
        } catch (\Throwable $e) {
            Log::error('Failed to handle LINE event', [
                'event_type' => get_class($event),
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);
        }
    }

    private function handleMessageEvent(MessageEvent $event): void
    {
        $message = $event->getMessage();

        if (!$message instanceof TextMessageContent) {
            return;
        }

        $text = trim($message->getText());
        $replyToken = $event->getReplyToken();
        $source = $event->getSource();

        $lineUserId = null;
        $group = null;

        if ($source instanceof GroupSource) {
            $lineUserId = $source->getUserId();
            $lineGroupId = $source->getGroupId();

            if ($lineGroupId) {
                $groupSummary = $this->lineService->getGroupSummary($lineGroupId);
                $group = $this->groupService->resolveMessageGroup($lineGroupId, $groupSummary['groupName'] ?? null);

                if (!$group) {
                    $this->lineService->replyText(
                        $replyToken,
                        "ℹ️ กลุ่มนี้ถูกยกเลิกการเชื่อมต่อแล้ว\n\n" .
                        'หากต้องการใช้งานอีกครั้ง ให้เชิญบอทออกและเพิ่มเข้ากลุ่มใหม่'
                    );
                    return;
                }
            }
        } elseif ($source instanceof UserSource) {
            $lineUserId = $source->getUserId();
        }

        if (!$lineUserId) {
            Log::warning('Could not get user ID from message event');
            return;
        }

        // Resolve sender account
        $user = $this->connectionService->findUserByLineId($lineUserId);
        if ($group) {
            $profile = $this->lineService->getGroupMemberProfile($group->line_group_id, $lineUserId);
            $user = $this->groupService->resolveGroupMember($group, $lineUserId, $profile);
        }

        // Parse the message
        $parsed = $this->messageParser->parse($text, $user);

        // Dispatch to Command Dispatcher
        $this->commandDispatcher->dispatchMessage($parsed, $user, $lineUserId, $replyToken, $group);
    }

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

        $user = $this->connectionService->findUserByLineId($lineUserId);

        if ($user) {
            $flexContents = $this->flexBuilder->welcomeBackCard($user->name);
            $this->lineService->replyFlex($replyToken, 'ยินดีต้อนรับกลับ!', $flexContents);
        } else {
            $flexContents = $this->flexBuilder->welcomeCard();
            $this->lineService->replyFlex($replyToken, 'ยินดีต้อนรับสู่ จดตังค์!', $flexContents);
        }
    }

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

        $groupName = null;
        $groupSummary = $this->lineService->getGroupSummary($lineGroupId);
        if ($groupSummary) {
            $groupName = $groupSummary['groupName'];
        }

        $this->groupService->ensureActiveGroup($lineGroupId, $groupName);

        $flexContents = $this->flexBuilder->groupWelcomeCard($groupName);
        $this->lineService->replyFlex($replyToken, 'สวัสดีครับ!', $flexContents);
    }

    private function handleLeaveEvent(LeaveEvent $event): void
    {
        $source = $event->getSource();
        
        $lineGroupId = null;
        if ($source instanceof GroupSource) {
            $lineGroupId = $source->getGroupId();
        }

        if ($lineGroupId) {
            $this->groupService->deactivateGroup($lineGroupId);
            Log::info('Bot left group', ['line_group_id' => $lineGroupId]);
        }
    }
}
