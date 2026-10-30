# How to Deploy JodTung (Laravel) on Hostinger Business Plan

This guide provides step-by-step instructions for deploying this Laravel application onto a Hostinger Business Web Hosting plan.

## 1. Preparation (Local Machine)

Before deploying, make sure your code is ready.

1. **Commit and Push:** Ensure all your latest changes are pushed to your Git repository (GitHub/GitLab/Bitbucket).
2. **Build Assets:** If you are using Vite/Mix, build your frontend assets locally and commit them, or plan to build them on the server if Node is supported. Hostinger Business plans support SSH, but building locally and committing the `public/build` directory is often easier to avoid out-of-memory issues on shared hosting.
   ```bash
   npm run build
   ```

## 2. Hostinger Initial Setup

1. **Log in to Hostinger hPanel.**
2. **Add Website:** Go to **Websites** -> **Create or migrate a website** -> **Skip, create an empty website**, and link your domain name.
3. **Database Setup:** 
   - Navigate to **Databases** -> **Management**.
   - Create a new MySQL Database.
   - **Important:** Save the Database Name, Database Username, and Password.

## 3. Uploading Code

Hostinger Business Plan includes SSH access. The best way to deploy is using Git via SSH.

### Step 3.1: SSH into Hostinger
1. Go to **Advanced** -> **SSH Access** in hPanel to get your SSH details.
2. Open your terminal and connect: `ssh u123456789@srv123.main-hosting.eu` (replace with your details).

### Step 3.2: Clone the Repository
It is a security best practice to place your Laravel core files *outside* the public web root.

1. Navigate to your domain's root folder:
   ```bash
   cd domains/yourdomain.com
   ```
2. Clone your repository into a folder (e.g., `jodtung-app`):
   ```bash
   git clone https://github.com/your-username/jodtung-app.git jodtung-app
   ```

### Step 3.3: Link the Public Folder
Hostinger serves files from the `public_html` directory. We need to link Laravel's `public` folder to it.

1. Backup or remove the default `public_html` folder:
   ```bash
   rm -rf public_html
   ```
2. Create a symbolic link pointing to your Laravel public folder:
   ```bash
   ln -s jodtung-app/public public_html
   ```

## 4. Environment Configuration

1. Go into your project folder:
   ```bash
   cd jodtung-app
   ```
2. Copy the example `.env` file:
   ```bash
   cp .env.example .env
   ```
3. Edit the `.env` file (you can use `nano .env` in SSH or the Hostinger File Manager).
4. Update the following critical variables:
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://yourdomain.com

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=your_hostinger_db_name
   DB_USERNAME=your_hostinger_db_user
   DB_PASSWORD=your_hostinger_db_password
   ```
   *Make sure to configure your LINE Messaging API credentials as well, since the app uses `LineCommandDispatcher.php`.*

## 5. Install Dependencies and Setup

Run the following commands via SSH in your project root (`domains/yourdomain.com/jodtung-app`):

1. **Install Composer Packages:**
   ```bash
   composer install --optimize-autoloader --no-dev
   ```
2. **Generate Application Key:**
   ```bash
   php artisan key:generate
   ```
3. **Run Migrations:**
   ```bash
   php artisan migrate --force
   ```
4. **Link Storage:**
   ```bash
   php artisan storage:link
   ```
5. **Cache Configurations for Performance:**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

## 6. Managing Queues (For LINE Events)

Since `jodtung-app` processes LINE events asynchronously (`ProcessLineEvent.php`), you need a queue worker running. Hostinger Shared/Business hosting doesn't provide Supervisor, so you must use Cron Jobs.

1. In hPanel, go to **Advanced** -> **Cron Jobs**.
2. **Add a Cron Job for the Laravel Scheduler:**
   Set it to run every minute (`* * * * *`):
   ```bash
   cd /home/u123456789/domains/yourdomain.com/jodtung-app && php artisan schedule:run >> /dev/null 2>&1
   ```
   *(Replace `u123456789` and `yourdomain.com` with your actual absolute path).*

3. **Running the Queue:**
   To process the queue automatically without Supervisor, you can schedule a command in your `routes/console.php` or `app/Console/Kernel.php` to run the queue worker. 
   Alternatively, add a second Cron Job in Hostinger to run every minute:
   ```bash
   cd /home/u123456789/domains/yourdomain.com/jodtung-app && php artisan queue:work --stop-when-empty >> /dev/null 2>&1
   ```

## 7. Final Checks

1. **Verify Website:** Visit `https://yourdomain.com` and verify the app loads.
2. **Test LINE Webhooks:** Send a message to your LINE bot and check if the webhook is received and jobs are processed successfully.
3. **Troubleshooting:** If you encounter a `500 Server Error`, check the logs in `storage/logs/laravel.log` via SSH or File Manager. Also, ensure that the `.htaccess` file inside your `public` directory is present.
