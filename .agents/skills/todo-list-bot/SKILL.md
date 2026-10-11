---
name: todo-list-bot
description: >-
  The official agent skill for Tueen Telegram To-Do List Bot.
  Provides architecture references, state machines, domain models,
  PHP 8.4+ property hooks, and interactive flow patterns.
---

# Tueen Telegram To-Do List Bot Skill

Welcome to the **todo-list-bot** skill. This document outlines the architectural specifications, conversational flows, and relational repositories powering the Telegram To-Do List bot.

---

## 🏛️ Architecture Overview

The bot is structured under the `Tueen\Telegram\Example\ToDoList` namespace, combining:
1. **Tueen Telegram SDK (`tueen/telegram`):**
   - High-level application bootstrapper: `App`
   - Conversational state machine: `InteractiveFlow`
   - Zero-flicker UI builder: `Screen` and `InlineKeyboard`
   - Declarative routing attributes: `#[OnCommand]`, `#[OnUpdate]`, `#[Action]`, `#[Input]`
2. **Tueen Database (`tueen/database`):**
   - Zero-config declarative schema auto-migration (`AutoMigrator`)
   - Signed 64-bit BigInt Telegram chat storage (`Chat`)
   - Domain entity modeling with PHP 8.4 property hooks and asymmetric visibility

---

## 🔄 Core Conversational Flows

| Flow Class | Description |
|---|---|
| `MainMenuFlow` | Interactive home screen with live statistics (total, pending, completed, overdue, trash) and quick navigation. |
| `TaskCreateFlow` | 7-step wizard (`withStepper`) capturing title, description, category, priority, deadline, reminder, and recurrence. |
| `TaskListFlow` | In-place paginated list (`withPagination`) with checkbox-simulated filters (`✅` / `☑️`), sorting, and search. |
| `TaskDetailFlow` | Detailed task view with status toggles (`⏳`, `🔄`, `✅`), Snooze menu, and trash confirmation. |
| `TaskEditFlow` | In-place field editor for title, description, priority, category, deadline, and recurrence. |
| `CategoryManageFlow` | Lists active categories with task counts and allows adding new custom categories. |
| `TrashFlow` | Recycle bin listing soft-deleted tasks with restore and permanent delete options. |
| `SettingsFlow` | Timezone selection (`Asia/Tehran`, `UTC`, `Asia/Dubai`, etc.) and notification toggle. |

---

## 🧩 Middleware & Updates

- **`ChatSyncMiddleware`:**
  Automatically intercepts every incoming Telegram update (`message`, `callback_query`, `my_chat_member`, etc.) and synchronizes the chat profile in the `chats` table via `ChatRepository::findOrCreate()` while updating `activeAt`.
- **`ChatMemberController`:**
  Listens for `my_chat_member` updates. When a user blocks the bot (`kicked` or `left`), it marks `accessible = false` in the database. When unblocked (`member`), it restores `accessible = true`.

---

## ⏰ Reminder Engine

The `ReminderService` processes scheduled alerts, approaching deadlines, and overdue warnings:
```bash
# Run one pass
php bin/console reminder:run

# Run daemon watcher
php bin/console reminder:watch 15
```
Users can snooze any reminder directly from Telegram by 15 minutes, 1 hour, or 1 day.
