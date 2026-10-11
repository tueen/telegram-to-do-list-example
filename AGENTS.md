# AGENTS.md — Tueen Telegram To-Do List Bot

This document outlines the architecture, coding standards, domain model, and operating procedures for AI agents developing, inspecting, or extending the **Tueen Telegram To-Do List Bot**.

---

## 🏛️ Project Identity & Architecture

- **Namespace Root:** `Tueen\Telegram\Example\ToDoList`
- **Target Runtime:** PHP 8.4+ / PHP 8.5+ (strict types, property hooks, asymmetric visibility `public private(set)`).
- **Core Frameworks:**
  - `tueen/telegram`: *The Royal Telegram Bot SDK for Modern PHP* (`App`, `InteractiveFlow`, `Screen`, `InlineKeyboard`, `UpdateDispatcher`, `Router`).
  - `tueen/database`: *Zero-Config Relational Engine for Telegram Bots* (`DatabaseManager`, `AutoMigrator`, `ChatRepository`, Doctrine ORM).
- **Domain Responsibilities:**
  1. Multi-step conversational creation wizard (`TaskCreateFlow`).
  2. Zero-flicker in-place interactive screen transitions (`TaskListFlow`, `TaskDetailFlow`, `TaskEditFlow`).
  3. Interactive multi-select checklist and filter state simulation using `✅` and `☑️` emojis.
  4. Automatic chat synchronization on every incoming request (`ChatSyncMiddleware`).
  5. Live detection of bot block/unblock state via `my_chat_member` updates (`ChatMemberController`).
  6. Soft-delete trash management with one-click restore and permanent purge (`TrashFlow`).
  7. Scheduled reminders, approaching deadline warnings, overdue alerts, and snooze actions (`ReminderService`).
  8. User-level timezone offsets and notification toggles (`UserSettingsService`).

---

## 💎 Core Coding Rules for AI Agents

1. **Strict Types & Language Standards:**
   - Every PHP file MUST start with `declare(strict_types=1);`.
   - Use asymmetric visibility `public private(set)` for immutable IDs and timestamps:
     ```php
     public private(set) ?int $id = null;
     public private(set) string $publicId;
     public private(set) DateTimeImmutable $createdAt;
     ```
   - Use PHP 8.4 property hooks on entities and classes for virtual states:
     ```php
     public bool $isOverdue {
         get => !$this->isDeleted
             && !$this->status->isFinished()
             && $this->deadline !== null
             && $this->deadline < new DateTimeImmutable();
     }
     ```
   - **Enums:** Enums in PHP cannot have instance properties; implement methods instead (e.g., `public function badge(): string`).

2. **Zero-Config Database Auto-Migration:**
   - Never write raw manual migration files or force users to run schema commands.
   - All entities are registered with `AutoMigrator` via `DatabaseService`. The schema is automatically inspected and diffed on boot.

3. **Telegram Chat ID Sign:**
   - Chat IDs are signed 64-bit BigInts (users > 0, groups/supergroups/channels < 0). Always map them as `Types::BIGINT`.

4. **Zero-Flicker Screen In-Place Editing:**
   - Interactive flows inherit from `Tueen\Telegram\Flow\InteractiveFlow`.
   - Modifying data or navigating steps updates the active Telegram message in-place using `editMessageText` or `editMessageCaption` without creating duplicate messages.
   - User free-text messages are handled via `resendAtBottom()`.

5. **Simulated Checkboxes:**
   - In filter and toggle buttons, use `✅` (active/selected) and `☑️` (inactive/unselected) to provide clean visual toggling.

---

## 📂 Directory Layout

```
telegram-to-do-list-example/
├── .agents/
│   └── skills/
│       ├── todo-list-bot/SKILL.md             # Bot-specific agent skill
│       ├── tueen-database/SKILL.md            # Tueen database ecosystem skill
│       └── tueen-telegram-bot-api-sdk/SKILL.md # Tueen telegram SDK skill
├── bin/
│   ├── bot                                    # CLI polling, webhook and doctor runner
│   └── console                                # Reminders worker and DB console
├── public/
│   ├── .htaccess                              # Apache/LiteSpeed front controller rules
│   └── index.php                              # Webhook & Web dashboard entrypoint
├── src/
│   ├── Controller/
│   │   ├── MainController.php                 # /start, /help, /new, /tasks, /today
│   │   └── ChatMemberController.php           # my_chat_member updates (blocked/unblocked)
│   ├── Entity/
│   │   ├── Category.php                       # User task categories
│   │   ├── Task.php                           # Main task model (status, priority, deadline, recurrence)
│   │   └── TaskReminder.php                   # Task reminders and snooze tracking
│   ├── Enum/
│   │   ├── ReminderType.php                   # Scheduled, DeadlineApproaching, OverdueAlert
│   │   ├── TaskPriority.php                   # Low, Medium, High, Urgent
│   │   ├── TaskRecurrence.php                 # None, Daily, Weekly, Monthly
│   │   └── TaskStatus.php                     # Pending, InProgress, Completed, Cancelled
│   ├── Flow/
│   │   ├── MainMenuFlow.php                   # Home dashboard and summary stats
│   │   ├── TaskCreateFlow.php                 # 7-step creation wizard
│   │   ├── TaskListFlow.php                   # Interactive list, filters, sort, search, pagination
│   │   ├── TaskDetailFlow.php                 # Full details, status toggles, snooze, trash
│   │   ├── TaskEditFlow.php                   # In-place field editor
│   │   ├── CategoryManageFlow.php             # Category list and creator
│   │   ├── TrashFlow.php                      # Recycle bin (restore & permanent delete)
│   │   └── SettingsFlow.php                   # Timezone and notifications settings
│   ├── Middleware/
│   │   └── ChatSyncMiddleware.php             # Automatic chat record touch on every update
│   ├── Repository/
│   │   ├── CategoryRepository.php
│   │   ├── TaskReminderRepository.php
│   │   └── TaskRepository.php
│   ├── Service/
│   │   ├── DatabaseService.php                # DatabaseManager singleton
│   │   ├── ReminderService.php                # Notification dispatcher and snooze engine
│   │   └── UserSettingsService.php            # ChatMeta settings accessor
│   └── Support/
│       └── DateTimeHelper.php                 # Timezone and relative date formatter
├── storage/
│   ├── database.sqlite                        # SQLite relational database
│   └── flow/                                  # Conversation state file storage
├── tests/
│   ├── Feature/
│   │   ├── ChatSyncMiddlewareTest.php
│   │   └── TaskRepositoryTest.php
│   └── Unit/
│       ├── CategoryEntityTest.php
│       ├── EnumTest.php
│       └── TaskEntityTest.php
├── AGENTS.md                                  # This document
├── composer.json
├── config.php                                 # Tueen application configuration
├── phpunit.xml
├── README.md
└── routes.php                                 # Global route bindings & middleware
```

---

## 🛠️ Operating Commands

### 1. Running Automated Tests
```bash
vendor/bin/phpunit
```

### 2. Running the Bot in Polling Mode (Development & Testing)
```bash
php bin/bot
```

### 3. Running System Diagnostics (Doctor)
```bash
php bin/bot doctor
```

### 4. Running Reminders Worker
```bash
# Execute once:
php bin/console reminder:run

# Run as background daemon (every 15s):
php bin/console reminder:watch 15
```

### 5. Inspecting Database Status
```bash
php bin/console stats
```
