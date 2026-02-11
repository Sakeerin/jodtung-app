<?php

namespace App\Services\Line;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use LINE\Clients\MessagingApi\Api\MessagingApiApi;
use LINE\Clients\MessagingApi\Api\MessagingApiBlobApi;
use LINE\Clients\MessagingApi\Configuration;
use LINE\Clients\MessagingApi\Model\CreateRichMenuAliasRequest;
use LINE\Clients\MessagingApi\Model\RichMenuArea;
use LINE\Clients\MessagingApi\Model\RichMenuBounds;
use LINE\Clients\MessagingApi\Model\RichMenuRequest;
use LINE\Clients\MessagingApi\Model\RichMenuSize;

class RichMenuService
{
    private MessagingApiApi $messagingApi;
    private MessagingApiBlobApi $blobApi;
    private string $channelAccessToken;

    // Rich Menu dimensions (2500 x 1686 for 6-button layout)
    private const MENU_WIDTH = 2500;
    private const MENU_HEIGHT = 1686;
    private const CELL_WIDTH = 833; // 2500 / 3 (approx)
    private const CELL_HEIGHT = 843; // 1686 / 2

    public function __construct()
    {
        $this->channelAccessToken = config('services.line.channel_access_token');

        $config = new Configuration();
        $config->setAccessToken($this->channelAccessToken);

        $client = new Client();
        $this->messagingApi = new MessagingApiApi($client, $config);
        $this->blobApi = new MessagingApiBlobApi($client, $config);
    }

    /**
     * Create the default rich menu for JodTung.
     *
     * Layout:
     * ┌──────────┬──────────┬──────────┐
     * │  สรุปยอด  │   สถิติ   │  บันทึก+  │
     * │    📊     │    📈    │    ➕    │
     * ├──────────┼──────────┼──────────┤
     * │  คำสั่งลัด │   คู่มือ  │  เปิดเว็บ  │
     * │    ⚡     │    ❓    │    🌐    │
     * └──────────┴──────────┴──────────┘
     *
     * @return string|null Rich Menu ID if created successfully
     */
    public function createDefaultRichMenu(): ?string
    {
        try {
            $richMenuRequest = new RichMenuRequest([
                'size' => new RichMenuSize([
                    'width' => self::MENU_WIDTH,
                    'height' => self::MENU_HEIGHT,
                ]),
                'selected' => true,
                'name' => 'JodTung Default Menu',
                'chatBarText' => 'เมนู',
                'areas' => $this->getDefaultAreas(),
            ]);

            $response = $this->messagingApi->createRichMenu($richMenuRequest);
            $richMenuId = $response->getRichMenuId();

            Log::info('Rich menu created', ['rich_menu_id' => $richMenuId]);

            return $richMenuId;
        } catch (\Exception $e) {
            Log::error('Failed to create rich menu', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get the areas (buttons) for the default rich menu.
     *
     * @return RichMenuArea[]
     */
    private function getDefaultAreas(): array
    {
        return [
            // Row 1
            // สรุปยอด (Summary)
            new RichMenuArea([
                'bounds' => new RichMenuBounds([
                    'x' => 0,
                    'y' => 0,
                    'width' => self::CELL_WIDTH,
                    'height' => self::CELL_HEIGHT,
                ]),
                'action' => [
                    'type' => 'message',
                    'text' => '/ยอดเดือนนี้',
                ],
            ]),
            // สถิติ (Stats)
            new RichMenuArea([
                'bounds' => new RichMenuBounds([
                    'x' => self::CELL_WIDTH,
                    'y' => 0,
                    'width' => self::CELL_WIDTH,
                    'height' => self::CELL_HEIGHT,
                ]),
                'action' => [
                    'type' => 'message',
                    'text' => '/สถิติ',
                ],
            ]),
            // บันทึก+ (Record)
            new RichMenuArea([
                'bounds' => new RichMenuBounds([
                    'x' => self::CELL_WIDTH * 2,
                    'y' => 0,
                    'width' => self::MENU_WIDTH - (self::CELL_WIDTH * 2),
                    'height' => self::CELL_HEIGHT,
                ]),
                'action' => [
                    'type' => 'message',
                    'text' => '/บันทึก',
                ],
            ]),

            // Row 2
            // คำสั่งลัด (Shortcuts)
            new RichMenuArea([
                'bounds' => new RichMenuBounds([
                    'x' => 0,
                    'y' => self::CELL_HEIGHT,
                    'width' => self::CELL_WIDTH,
                    'height' => self::MENU_HEIGHT - self::CELL_HEIGHT,
                ]),
                'action' => [
                    'type' => 'message',
                    'text' => '/คำสั่ง',
                ],
            ]),
            // คู่มือ (Help)
            new RichMenuArea([
                'bounds' => new RichMenuBounds([
                    'x' => self::CELL_WIDTH,
                    'y' => self::CELL_HEIGHT,
                    'width' => self::CELL_WIDTH,
                    'height' => self::MENU_HEIGHT - self::CELL_HEIGHT,
                ]),
                'action' => [
                    'type' => 'message',
                    'text' => '/help',
                ],
            ]),
            // เปิดเว็บ (Open Web)
            new RichMenuArea([
                'bounds' => new RichMenuBounds([
                    'x' => self::CELL_WIDTH * 2,
                    'y' => self::CELL_HEIGHT,
                    'width' => self::MENU_WIDTH - (self::CELL_WIDTH * 2),
                    'height' => self::MENU_HEIGHT - self::CELL_HEIGHT,
                ]),
                'action' => [
                    'type' => 'uri',
                    'uri' => config('app.url') . '/dashboard',
                ],
            ]),
        ];
    }

    /**
     * Upload an image to a rich menu.
     *
     * @param string $richMenuId
     * @param string $imagePath Path to the image file
     * @return bool
     */
    public function uploadRichMenuImage(string $richMenuId, string $imagePath): bool
    {
        try {
            // Read the image file
            if (!file_exists($imagePath)) {
                Log::error('Rich menu image not found', ['path' => $imagePath]);
                return false;
            }

            $imageContent = file_get_contents($imagePath);
            $contentType = mime_content_type($imagePath);

            // The LINE SDK's setRichMenuImage expects a SplFileObject
            $splFile = new \SplFileObject($imagePath);

            $this->blobApi->setRichMenuImage($richMenuId, $splFile);

            Log::info('Rich menu image uploaded', ['rich_menu_id' => $richMenuId]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to upload rich menu image', [
                'error' => $e->getMessage(),
                'rich_menu_id' => $richMenuId,
            ]);
            return false;
        }
    }

    /**
     * Set the default rich menu for all users.
     */
    public function setDefaultRichMenu(string $richMenuId): bool
    {
        try {
            $this->messagingApi->setDefaultRichMenu($richMenuId);
            Log::info('Default rich menu set', ['rich_menu_id' => $richMenuId]);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to set default rich menu', [
                'error' => $e->getMessage(),
                'rich_menu_id' => $richMenuId,
            ]);
            return false;
        }
    }

    /**
     * Link a rich menu to a specific user.
     */
    public function linkRichMenuToUser(string $userId, string $richMenuId): bool
    {
        try {
            $this->messagingApi->linkRichMenuIdToUser($userId, $richMenuId);
            Log::info('Rich menu linked to user', [
                'user_id' => $userId,
                'rich_menu_id' => $richMenuId,
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to link rich menu to user', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'rich_menu_id' => $richMenuId,
            ]);
            return false;
        }
    }

    /**
     * Unlink a rich menu from a user.
     */
    public function unlinkRichMenuFromUser(string $userId): bool
    {
        try {
            $this->messagingApi->unlinkRichMenuIdFromUser($userId);
            Log::info('Rich menu unlinked from user', ['user_id' => $userId]);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to unlink rich menu from user', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);
            return false;
        }
    }

    /**
     * Get all rich menus.
     *
     * @return array
     */
    public function getRichMenuList(): array
    {
        try {
            $response = $this->messagingApi->getRichMenuList();
            return $response->getRichmenus() ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to get rich menu list', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Delete a rich menu.
     */
    public function deleteRichMenu(string $richMenuId): bool
    {
        try {
            $this->messagingApi->deleteRichMenu($richMenuId);
            Log::info('Rich menu deleted', ['rich_menu_id' => $richMenuId]);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete rich menu', [
                'error' => $e->getMessage(),
                'rich_menu_id' => $richMenuId,
            ]);
            return false;
        }
    }

    /**
     * Cancel the default rich menu.
     */
    public function cancelDefaultRichMenu(): bool
    {
        try {
            $this->messagingApi->cancelDefaultRichMenu();
            Log::info('Default rich menu cancelled');
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to cancel default rich menu', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Create and set up the complete default rich menu.
     * This is a convenience method that creates the menu and sets it as default.
     *
     * @param string|null $imagePath Optional path to custom image
     * @return string|null Rich Menu ID if successful
     */
    public function setupDefaultRichMenu(?string $imagePath = null): ?string
    {
        // Create the rich menu
        $richMenuId = $this->createDefaultRichMenu();
        if (!$richMenuId) {
            return null;
        }

        // Upload image if provided
        if ($imagePath && file_exists($imagePath)) {
            if (!$this->uploadRichMenuImage($richMenuId, $imagePath)) {
                // Image upload failed, but menu was created
                Log::warning('Rich menu created but image upload failed', [
                    'rich_menu_id' => $richMenuId,
                ]);
            }
        }

        // Set as default
        if (!$this->setDefaultRichMenu($richMenuId)) {
            Log::warning('Rich menu created but setting default failed', [
                'rich_menu_id' => $richMenuId,
            ]);
        }

        return $richMenuId;
    }

    /**
     * Create a rich menu alias for easier reference.
     */
    public function createRichMenuAlias(string $richMenuId, string $aliasId): bool
    {
        try {
            $request = new CreateRichMenuAliasRequest([
                'richMenuAliasId' => $aliasId,
                'richMenuId' => $richMenuId,
            ]);

            $this->messagingApi->createRichMenuAlias($request);
            Log::info('Rich menu alias created', [
                'rich_menu_id' => $richMenuId,
                'alias_id' => $aliasId,
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to create rich menu alias', [
                'error' => $e->getMessage(),
                'rich_menu_id' => $richMenuId,
                'alias_id' => $aliasId,
            ]);
            return false;
        }
    }
}
