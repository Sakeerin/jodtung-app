<?php

namespace App\Services\Line;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use LINE\Clients\MessagingApi\Api\MessagingApiApi;
use LINE\Clients\MessagingApi\Configuration;
use LINE\Clients\MessagingApi\Model\FlexMessage;
use LINE\Clients\MessagingApi\Model\Message;
use LINE\Clients\MessagingApi\Model\PushMessageRequest;
use LINE\Clients\MessagingApi\Model\ReplyMessageRequest;
use LINE\Clients\MessagingApi\Model\TextMessage;

class LineService
{
    private MessagingApiApi $messagingApi;
    private string $channelAccessToken;

    public function __construct()
    {
        $this->channelAccessToken = config('services.line.channel_access_token');

        $config = new Configuration();
        $config->setAccessToken($this->channelAccessToken);

        $client = new Client();
        $this->messagingApi = new MessagingApiApi($client, $config);
    }

    /**
     * Reply with a text message.
     */
    public function replyText(string $replyToken, string $text): bool
    {
        return $this->reply($replyToken, [
            new TextMessage([
                'type' => 'text',
                'text' => $text,
            ]),
        ]);
    }

    /**
     * Reply with a Flex Message.
     */
    public function replyFlex(string $replyToken, string $altText, array $contents): bool
    {
        return $this->reply($replyToken, [
            new FlexMessage([
                'type' => 'flex',
                'altText' => $altText,
                'contents' => $contents,
            ]),
        ]);
    }

    /**
     * Reply with multiple messages.
     *
     * @param string $replyToken
     * @param Message[] $messages
     * @return bool
     */
    public function reply(string $replyToken, array $messages): bool
    {
        try {
            $request = new ReplyMessageRequest([
                'replyToken' => $replyToken,
                'messages' => $messages,
            ]);

            $this->messagingApi->replyMessage($request);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to reply message', [
                'error' => $e->getMessage(),
                'reply_token' => $replyToken,
            ]);
            return false;
        }
    }

    /**
     * Push a text message to a user.
     */
    public function pushText(string $to, string $text): bool
    {
        return $this->push($to, [
            new TextMessage([
                'type' => 'text',
                'text' => $text,
            ]),
        ]);
    }

    /**
     * Push a Flex Message to a user.
     */
    public function pushFlex(string $to, string $altText, array $contents): bool
    {
        return $this->push($to, [
            new FlexMessage([
                'type' => 'flex',
                'altText' => $altText,
                'contents' => $contents,
            ]),
        ]);
    }

    /**
     * Push multiple messages to a user.
     *
     * @param string $to User ID, Group ID, or Room ID
     * @param Message[] $messages
     * @return bool
     */
    public function push(string $to, array $messages): bool
    {
        try {
            $request = new PushMessageRequest([
                'to' => $to,
                'messages' => $messages,
            ]);

            $this->messagingApi->pushMessage($request);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to push message', [
                'error' => $e->getMessage(),
                'to' => $to,
            ]);
            return false;
        }
    }

    /**
     * Get user profile.
     *
     * @return array{displayName: string, userId: string, pictureUrl: ?string, statusMessage: ?string}|null
     */
    public function getProfile(string $userId): ?array
    {
        try {
            $profile = $this->messagingApi->getProfile($userId);

            return [
                'userId' => $profile->getUserId(),
                'displayName' => $profile->getDisplayName(),
                'pictureUrl' => $profile->getPictureUrl(),
                'statusMessage' => $profile->getStatusMessage(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get user profile', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);
            return null;
        }
    }

    /**
     * Get group member profile.
     *
     * @return array{displayName: string, userId: string, pictureUrl: ?string}|null
     */
    public function getGroupMemberProfile(string $groupId, string $userId): ?array
    {
        try {
            $profile = $this->messagingApi->getGroupMemberProfile($groupId, $userId);

            return [
                'userId' => $profile->getUserId(),
                'displayName' => $profile->getDisplayName(),
                'pictureUrl' => $profile->getPictureUrl(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get group member profile', [
                'error' => $e->getMessage(),
                'group_id' => $groupId,
                'user_id' => $userId,
            ]);
            return null;
        }
    }

    /**
     * Get group summary.
     *
     * @return array{groupId: string, groupName: string, pictureUrl: ?string}|null
     */
    public function getGroupSummary(string $groupId): ?array
    {
        try {
            $summary = $this->messagingApi->getGroupSummary($groupId);

            return [
                'groupId' => $summary->getGroupId(),
                'groupName' => $summary->getGroupName(),
                'pictureUrl' => $summary->getPictureUrl(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get group summary', [
                'error' => $e->getMessage(),
                'group_id' => $groupId,
            ]);
            return null;
        }
    }

    /**
     * Leave a group.
     */
    public function leaveGroup(string $groupId): bool
    {
        try {
            $this->messagingApi->leaveGroup($groupId);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to leave group', [
                'error' => $e->getMessage(),
                'group_id' => $groupId,
            ]);
            return false;
        }
    }

    /**
     * Get the messaging API instance for advanced operations.
     */
    public function getMessagingApi(): MessagingApiApi
    {
        return $this->messagingApi;
    }
}
