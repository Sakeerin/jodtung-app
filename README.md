# 📊 JodTung (จดตังค์) - LINE Expense Bot

**JodTung** is a seamless expense tracking application that integrates with LINE Messenger. It allows users to record income and expenses directly through LINE chat using natural language or shortcuts, while providing a comprehensive web dashboard for visualization and management.

## 🚀 Key Features

- **LINE Chat Interface**: Record transactions easily (e.g., "Food 150", "Salary 50000").
- **Rich Menu Integration**: Quick access to summary, stats, and manual recording.
- **Web Dashboard**: Visual charts, transaction history, and category management.
- **Group Expense Tracking**: Track shared expenses in LINE groups.
- **Smart Shortcuts**: Emoji-based shortcuts for quick categorization.
- **Auto Login**: Seamless login from LINE to Web Dashboard.

## 🛠 Tech Stack

- **Framework**: [Laravel 11](https://laravel.com)
- **Database**: MySQL 8
- **Frontend**: Vue.js 3 + Tailwind CSS (Planned for Phase 5)
- **API**: LINE Messaging API (Reply Mode)
- **Authentication**: Laravel Sanctum

## 📂 Project Structure

- `app/Http/Controllers/Line/WebhookController.php`: Handles LINE Webhook events.
- `app/Services/Line/`: LINE API services and message parsing (Phase 2).
- `app/Models/`: 
    - `User`: Application users linked to LINE accounts.
    - `Transaction`: Income and expense records.
    - `Category`: Transaction categories (income/expense).
    - `LineConnection`: Manages linking between LINE and Web accounts.
- `database/migrations/`: Database schema definitions.

## ⚙️ Installation & Setup

1. **Clone the repository**
   ```bash
   git clone https://github.com/Sakeerin/jodtung-app.git
   cd jodtung-app
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Environment Configuration**
   Copy `.env.example` to `.env` and configure your database and LINE API credentials:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   
   **Required .env variables:**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=jodtung
   DB_USERNAME=root
   DB_PASSWORD=

   LINE_CHANNEL_ACCESS_TOKEN=your_token
   LINE_CHANNEL_SECRET=your_secret
   ```

4. **Run Migrations**
   ```bash
   php artisan migrate --seed
   ```

5. **Serve the Application**
   ```bash
   php artisan serve
   ```

## 🧪 Testing

Run the automated test suite:
```bash
php artisan test
```

## 📅 Development Roadmap

- [x] **Phase 1: Foundation** - Project setup, Database, Auth, Webhook
- [ ] **Phase 2: LINE Bot Core** - Message Parsing, Flex Messages, Rich Menu
- [ ] **Phase 3: Transaction System** - CRUD Transactions, Shortcuts
- [ ] **Phase 4: Group Support** - Group tracking, Member roles
- [ ] **Phase 5: Web Dashboard** - Vue.js frontend, Charts
- [ ] **Phase 6: Polish & Deploy** - Refinement, Deployment

## 📄 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
