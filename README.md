# Telegram To-Do List Bot Example

A sample To-Do list bot for Telegram built with **PHP 8.4+**, demonstrating how to build interactive conversational applications using the **Tueen Telegram SDK** (`tueen/telegram`) and **Tueen Database** (`tueen/database`).

> **Note:** This repository is an example project created to demonstrate Tueen SDK features and patterns. It is not an active public service.

---

## Features

- **Task Management:** Create, view, edit, complete, cancel, and soft-delete tasks.
- **Multi-Step Creation Flow:** Guided task creation wizard capturing title, description, category, priority, deadline, reminder, and recurrence.
- **In-Place Updates:** Interactive screens update active messages in-place via inline keyboards, keeping the chat clean.
- **Organization & Priorities:** Preloaded and custom categories with emoji icons, plus 4 priority levels (Low, Medium, High, Urgent).
- **Filters, Search & Sorting:** Filter tasks by status, priority, or category, search titles/descriptions by keyword, and sort by deadline, priority, or creation date.
- **Recycle Bin (Trash):** Soft-deleted tasks can be restored or permanently removed.
- **Reminders & Snooze:** Scheduled alerts, approaching deadline warnings, and overdue notifications, with quick snooze options (15m, 30m, 1h, 2h, 1d).
- **Recurring Tasks:** Automatic rescheduling for daily, weekly, or monthly tasks upon completion.
- **User Timezones:** Per-user timezone configuration so deadlines and reminders match the user's local clock.
- **Chat Sync & Membership Tracking:** Middleware automatically creates and updates user profiles in the database on incoming messages, and tracks bot block/unblock events (`my_chat_member`).
- **Zero-Config Database:** Entity schemas are automatically checked and migrated on boot via Tueen Database.

---

## Bot Commands

| Command | Description |
|---|---|
| `/start` | Open the main dashboard with task summary statistics |
| `/new` | Start the task creation wizard |
| `/tasks` | View the interactive task list with filters and search |
| `/today` | View tasks due today in your configured timezone |
| `/trash` | Open the recycle bin to restore or purge deleted tasks |
| `/settings` | Configure timezone and notification preferences |
| `/help` | Display command guide and help information |

---

## Project Structure

```text
├── bin/
│   ├── bot                     # CLI runner for polling, webhook management, and diagnostics
│   └── console                 # Console tools for reminders worker and database stats
├── config.php                  # Bot application configuration
├── public/
│   ├── .htaccess               # Apache/LiteSpeed front controller rewrite rules
│   └── index.php               # Webhook and web dashboard entrypoint
├── routes.php                  # Route definitions and middleware bindings
├── src/
│   ├── Controller/             # Command and update controllers (MainController, ChatMemberController)
│   ├── Entity/                 # Doctrine ORM entities (Task, Category, TaskReminder)
│   ├── Enum/                   # Enums (TaskPriority, TaskStatus, TaskRecurrence, ReminderType)
│   ├── Flow/                   # Interactive UI flows (MainMenu, TaskCreate, TaskList, TaskDetail, etc.)
│   ├── Middleware/             # Chat profile sync middleware (ChatSyncMiddleware)
│   ├── Repository/             # Repositories for database operations
│   ├── Service/                # Services (DatabaseService, ReminderService, UserSettingsService)
│   └── Support/                # Date and timezone helpers (DateTimeHelper)
├── storage/                    # Local SQLite database and flow session storage
└── tests/                      # PHPUnit unit and feature tests
```

---

## Requirements

- **PHP 8.4** or higher
- Required PHP extensions: `pdo_sqlite` (or `pdo_mysql`), `curl`, `mbstring`, `json`, `openssl`
- **Composer** 2+

---

## Installation & Setup

1. **Install dependencies:**
   ```bash
   composer install
   ```

2. **Configure environment:**
   Copy `.env.example` to `.env` and provide your bot token:
   ```env
   TELEGRAM_BOT_TOKEN=123456789:ABCdefGHIjklMNOpqrSTUvwxYZ
   BOT_RUNNING_MODE=auto
   DB_PATH=storage/database.sqlite
   ```

3. **Verify system requirements:**
   ```bash
   php bin/bot doctor
   ```

---

## Running the Bot

### 1. Polling Mode (Local Development)
Runs the bot locally without needing a public domain or SSL:
```bash
php bin/bot
```

### 2. Webhook Mode (Production)
Configure a webhook over HTTPS:
```bash
# Set webhook
php bin/bot webhook:set https://yourdomain.com/index.php

# Check webhook status
php bin/bot webhook:info

# Remove webhook
php bin/bot webhook:delete
```

### 3. Background Reminders Worker
Process scheduled reminders, approaching deadlines, and overdue tasks:
```bash
# Run a single check pass (suitable for cron):
php bin/console reminder:run

# Run continuously as a background worker:
php bin/console reminder:watch 15
```

To view database statistics:
```bash
php bin/console stats
```

---

## Shared Hosting Setup (cPanel / DirectAdmin / Apache)

1. **PHP Version:** In cPanel, go to **MultiPHP Manager** and select **PHP 8.4+**. Ensure `pdo_sqlite` (or `pdo_mysql`), `curl`, `mbstring`, and `openssl` are enabled.

2. **Deployment Options:**

   - **Method A — Subdomain / Custom Document Root (Recommended & Zero Code Changes):**  
     Upload the entire project folder to your hosting account (e.g., `/home/username/bot_app`). In cPanel **Domains / Subdomains**, set the **Document Root** directly to `bot_app/public`.  
     *No files need to be moved and no code changes are required.*

   - **Method B — Main Web Root (`public_html`):**  
     If your hosting doesn't allow changing the document root, keep the project files secure outside the public web root:
     ```text
     /home/username/
     ├── bot_app/            <-- Project files (src, storage, vendor, .env, etc.)
     └── public_html/        <-- Web root (contents of public/)
         ├── .htaccess
         └── index.php
     ```
     Update `public_html/index.php` using a clean relative path (no hardcoded usernames needed):
     ```php
     $basePath = dirname(__DIR__) . '/bot_app';
     require $basePath . '/vendor/autoload.php';

     $app = \Tueen\Telegram\App::create($basePath);
     $app->run();
     ```

3. **Permissions:** Ensure the `storage/` directory is writable (`chmod -R 775 storage/`).
4. **Database:** SQLite works out of the box with `storage/database.sqlite`. If using MySQL, create a database in cPanel and set `DB_DRIVER=pdo_mysql` with host, database name, user, and password in `.env`.
5. **Set Webhook:** Run `php bin/bot webhook:set https://yourdomain.com/index.php` via terminal or open `https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://yourdomain.com/index.php` in your browser.
6. **Cron Job for Reminders:** Since shared hosts do not allow continuous daemons, add a cron job in cPanel to run once per minute (`* * * * *`):
   ```bash
   /usr/local/bin/ea-php84 /home/username/bot_app/bin/console reminder:run >/dev/null 2>&1
   ```

---

## Testing

Run the test suite using PHPUnit:

```bash
vendor/bin/phpunit
```

---

## License

This project is licensed under the [MIT License](LICENSE).
