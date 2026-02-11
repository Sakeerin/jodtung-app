<?php

namespace App\Console\Commands;

use App\Services\Line\RichMenuService;
use Illuminate\Console\Command;

class SetupRichMenuCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'line:setup-rich-menu 
                            {--image= : Path to the rich menu image (2500x1686 pixels)}
                            {--delete-existing : Delete all existing rich menus first}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create and set up the default LINE Rich Menu for JodTung';

    /**
     * Execute the console command.
     */
    public function handle(RichMenuService $richMenuService): int
    {
        $this->info('Setting up LINE Rich Menu...');

        // Delete existing menus if requested
        if ($this->option('delete-existing')) {
            $this->info('Deleting existing rich menus...');
            
            $menus = $richMenuService->getRichMenuList();
            foreach ($menus as $menu) {
                $menuId = $menu->getRichMenuId();
                if ($richMenuService->deleteRichMenu($menuId)) {
                    $this->line("  Deleted: {$menuId}");
                }
            }
            
            $this->info('Existing menus deleted.');
        }

        // Create new rich menu
        $imagePath = $this->option('image');
        
        if ($imagePath && !file_exists($imagePath)) {
            $this->error("Image file not found: {$imagePath}");
            return Command::FAILURE;
        }

        $this->info('Creating rich menu...');
        $richMenuId = $richMenuService->setupDefaultRichMenu($imagePath);

        if (!$richMenuId) {
            $this->error('Failed to create rich menu.');
            return Command::FAILURE;
        }

        $this->info("Rich menu created successfully!");
        $this->info("Rich Menu ID: {$richMenuId}");

        if (!$imagePath) {
            $this->warn('');
            $this->warn('Note: No image was uploaded. The rich menu will be created without a background image.');
            $this->warn('To upload an image later, use the LINE Developers Console or run this command with --image option.');
            $this->warn('');
            $this->warn('Image requirements:');
            $this->warn('  - Size: 2500 x 1686 pixels');
            $this->warn('  - Format: JPEG or PNG');
            $this->warn('  - Max file size: 1 MB');
        }

        return Command::SUCCESS;
    }
}
