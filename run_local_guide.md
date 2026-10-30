# How to Run JodTung (Laravel) Locally for Testing

This guide provides step-by-step instructions for setting up and running the JodTung project on your local machine for development and testing.

## Prerequisites

Ensure you have the following installed on your local machine:
- **PHP** (v8.2 or higher recommended)
- **Composer** (PHP package manager)
- **Node.js & npm** (Frontend package manager)
- **MySQL** (Database server, e.g., via XAMPP, Laragon, or Docker)
- **Ngrok** (Optional, but **highly recommended** for testing LINE Webhooks locally)

---

## 1. Initial Setup

1. **Clone the repository** (if you haven't already):
   ```bash
   git clone https://github.com/your-username/jodtung-app.git
   cd jodtung-app
   ```

2. **Install PHP Dependencies:**
   ```bash
   composer install
   ```

3. **Install Frontend Dependencies:**
   ```bash
   npm install
   ```

## 2. Environment Configuration

1. **Copy the environment file:**
   ```bash
   cp .env.example .env
   ```

2. **Generate Application Key:**
   ```bash
   php artisan key:generate
   ```

3. **Database Configuration:**
   Open your `.env` file and configure your local database connection:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=jodtung_local
   DB_USERNAME=root
   DB_PASSWORD=
   ```
   *(Make sure you create a database named `jodtung_local` in your MySQL server (via phpMyAdmin or terminal) before proceeding).*

4. **LINE API Configuration:**
   Since the app uses LINE Webhooks, make sure to add your LINE Channel Access Token and Channel Secret to the `.env` file (if you have them configured there).

## 3. Database Migration

Run the database migrations to create the necessary tables:
```bash
php artisan migrate
```
*(If you have database seeders for mock testing data, you can run `php artisan migrate --seed`)*.

## 4. Link Storage

Create a symbolic link to make your storage files publicly accessible (useful for file uploads):
```bash
php artisan storage:link
```

## 5. Running the Application

To run the application, you will need to open **three separate terminal tabs/windows**:

### Terminal 1: PHP Local Server
Start the Laravel backend server:
```bash
php artisan serve
```
*(By default, this runs on `http://127.0.0.1:8000`)*

### Terminal 2: Frontend Assets Build
Start the Vite development server to compile your frontend assets on the fly:
```bash
npm run dev
```

### Terminal 3: Queue Worker (Crucial for LINE Events)
Because this app processes LINE events (`ProcessLineEvent.php`) asynchronously, you must have a local queue worker running to process them in the background:
```bash
php artisan queue:work
```

---

## 6. Testing LINE Webhooks Locally (Using Ngrok)

LINE's servers cannot send webhooks to `http://127.0.0.1` or `http://localhost`. To test the LINE bot on your local machine, you need to expose your local server to the internet using **Ngrok**.

1. Download and install [Ngrok](https://ngrok.com/).
2. In a new terminal, run:
   ```bash
   ngrok http 8000
   ```
3. Ngrok will give you an **HTTPS Forwarding URL** (e.g., `https://abcdef123.ngrok-free.app`).
4. Go to your **LINE Developers Console**.
5. Update your Channel's **Webhook URL** to the ngrok URL (append your API route). For example: 
   `https://abcdef123.ngrok-free.app/api/line/webhook` *(Adjust the path according to your actual route)*.
6. Verify the webhook. When you message your LINE bot, the request will hit your local `php artisan serve` instance, and the event will be processed by your local `php artisan queue:work`.

## 7. Troubleshooting Local Environment

- **Jobs not processing?** Make sure you have Terminal 3 (`php artisan queue:work`) running.
- **500 Server Error?** Check your local logs located at `storage/logs/laravel.log`.
- **CSS/JS not loading?** Ensure you are running `npm run dev`.
