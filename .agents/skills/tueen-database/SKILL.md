---
name: tueen-database
description: >-
  The official agent skill for tueen/database (The Zero-Config, High-Throughput Relational Engine for Modern Telegram Bots).
  Provides complete architectural guidelines, PHP 8.4+ property hooks, asymmetric visibility,
  zero-config auto-migrations, multi-currency ledger, domain repositories, and complete offline documentation.
---

# Tueen Database Skill & Development Guide

Welcome to the **tueen/database** agent skill. This skill packages the complete offline documentation, architectural rules, and coding standards for developing Telegram bots and standalone applications using modern PHP 8.4+.

---

## 🏛️ Library Identity & Tenets

- **Package Name:** `tueen/database`
- **GitHub Repository:** [tueen/database on GitHub](https://github.com/tueen/database)
- **Slogan:** *The Zero-Config, High-Throughput Relational Engine for Modern Telegram Bots.*
- **Target Runtime:** PHP 8.4+ (strict types, property hooks `get => ...` / `set => ...`, asymmetric visibility `public private(set)`).
- **Zero-Config Auto-Migration:** Declarative lazy schema synchronization on boot / first access via `AutoMigrator` and `SchemaSynchronizer`.
- **Financial Precision:** `DECIMAL(36, 18)` or `DECIMAL(40, 18)` string math using `bcadd()`, `bcsub()`, `bccomp()`, `bcmul()`, `bcdiv()`. Never native floats!
- **Telegram Domain Requirements:** Signed 64-bit BigInt for chat IDs (positive user IDs, negative supergroup/channel IDs), UTF-8 / utf8mb4 emojis, automatic `@` stripping via property hooks.
- **Multi-Database Agnostic:** SQLite (`:memory:` and file), MySQL 8+, MariaDB, and PostgreSQL.
- **Online Documentation Base URL:** https://tueen.github.io/database/

> **🔎 Deep Source Inspection & Repository Fallback:**
> If the bundled documentation guides or offline references are not sufficient for your use case, or if you need to inspect raw class implementations, method signatures, test suites, or latest features, refer directly to the official GitHub repository:
> **https://github.com/tueen/database**

---

## 💎 Core Architectural Rules for AI Agents

When writing, refactoring, or generating code using `tueen/database`, AI assistants MUST adhere to these rules:

1. **Strict Types & PHP 8.4+ Standards:**
   - Always declare `declare(strict_types=1);` at the top of every PHP file.
   - Use asymmetric visibility `public private(set)` for entity IDs and immutable timestamps:
     ```php
     public private(set) int $id;
     public private(set) string $publicId;
     public private(set) \DateTimeImmutable $createdAt;
     ```
   - Use property hooks for normalization, validation, and derived states:
     ```php
     public ?string $username {
         set (?string $value) => $value !== null && ($val = strtolower(ltrim(trim($value), '@'))) !== '' ? $val : null;
     }

     public bool $isActive {
         get => $this->expiresAt > new \DateTimeImmutable();
     }
     ```
   - Never use parentheses when chaining new instances: `new DateTimeImmutable()->modify(...)`.

2. **Declarative Zero-Config Schema Auto-Sync:**
   - The engine automatically inspects and synchronizes schema diffs on boot/first access via `AutoMigrator` and `SchemaSynchronizer`.
   - End-users must never be forced to run manual CLI migration commands to get a bot up and running.

3. **Telegram Chat ID Sign:**
   - Chat IDs are **signed 64-bit BigInts** (user IDs > 0, group/supergroup/channel IDs < 0). Always map them as `Types::BIGINT` and never unsigned.

4. **Multi-Currency Ledger & Financial Math:**
   - All balances and monetary values are stored as strings using `DECIMAL(36, 18)` or `DECIMAL(40, 18)`.
   - Never perform floating-point math on currencies. Always use BCMath (`bcadd`, `bcsub`, `bccomp`, `bcmul`, `bcdiv`).
   - Use `WalletRepository::credit()`, `debit()`, or `transfer()` inside transactional wrappers.

5. **Repository & Source Inspection Fallback:**
   - If the documentation or guides do not cover a specific edge case or internal implementation details, inspect the source code, unit tests, and examples directly on GitHub: https://github.com/tueen/database

---

## 📚 Complete Offline Documentation Map

All documentation guides are bundled alongside this skill in the `./docs/` folder:


### 🚀 Getting Started
- [Getting Started with Tueen Database](./docs/guide/getting-started.md): Welcome to Tueen Database, the modern, zero-config relational database component tailored specifically for high-throughput Telegram bots.
  - 📦 Requirements
  - 🚀 Installation
  - ⚡ Quick Initialization
    * 1. Zero-Config In-Memory Database (Ideal for Tests & Prototyping)
    * 2. File-Based SQLite Database (Small to Medium Bots)
    * 3. Production MySQL / MariaDB (High Concurrency Bots)
  - 💬 Basic Usage Example
  - 🧭 Next Steps
- [Architecture & Engine Overview](./docs/guide/architecture.md): Tueen Database is architected around the principles of high concurrency, zero-maintenance operations, and strict domain boundaries.
  - 🏛️ Core Architecture Diagram
  - 🧩 Key Architectural Principles
    * 1. Unified Engine Facade (`DatabaseManager`)
    * 2. PHP 8.4 Language Advancements
    * 3. Telegram Domain Invariants
  - 🧭 Next Steps
- [Declarative Zero-Config Auto-Migrations](./docs/guide/zero-config-migrations.md): One of the defining innovations of Tueen Database is its Declarative Zero-Config Schema Engine.
  - ⚡ The Zero-Config Concept
  - 🔄 How the Lazy Synchronizer Works
  - 🛡️ Safety Guarantees
  - ⚙️ Configuration
  - 🧭 Next Steps
- [Multi-Database Setup](./docs/guide/multi-database.md): Tueen Database is completely database-agnostic. It abstracts dialect differences so that your bot code, queries, and repositories run identically across SQLite, MySQL, MariaDB, and PostgreSQL.
  - 🗄️ Supported Database Platforms
  - 💻 Configuration Examples
    * SQLite (In-Memory & File)
    * MySQL / MariaDB
    * PostgreSQL
  - 🔐 Environment Variables (`.env`)
  - 🧭 Next Steps

### 💬 Bot Domain & Essentials
- [Chats & Contacts Management](./docs/guide/chats-and-contacts.md): The Chat and Contact entities represent the primary actors in the Telegram bot ecosystem, while ChatXp, ChatLike, and ChatRating power social engagement and gamification.
  - 💬 The `Chat` Entity
    * Chat ID Sign Rule
  - 🛠️ The `findOrCreate` Pattern
    * Automatic Normalization & Touch
  - 🪝 PHP 8.4 Property Hooks on `Chat`
  - 📇 User Contacts & Phone Verification (`ContactRepository`)
    * Using `ContactRepository`
  - 🎮 Gamification & Experience Points (`ChatXpRepository`)
  - ⭐ Social Feedback (`ChatLike` & `ChatRating`)
  - 🏷️ Chat Metadata (`ChatMeta`)
  - 🧭 Next Steps
- [Wallets & Treasury Ledger](./docs/guide/wallets-and-treasury.md): Modern Telegram bots frequently handle digital payments, in-bot credits, Telegram Stars, cryptocurrencies (TON, USDT, BTC), or local fiat currencies.
  - 💰 Arbitrary-Precision Math
  - 💳 Wallet Operations (`WalletRepository`)
    * 1. Checking Balances
    * 2. Crediting Funds (Deposits, Rewards)
    * 3. Debiting Funds (Purchases, Fees)
    * 4. Atomic Peer-to-Peer Transfers
    * 5. Balance Freezing & Unfreezing
  - 💸 Payout Requests & Withdrawals (`WithdrawalRepository`)
    * 1. Requesting a Withdrawal
    * 2. Processing & Approval Workflow
    * 3. Lifecycle Statuses (`WithdrawalStatus`)
  - 🏛️ Bot Treasury (`TreasuryRepository`)
  - 🧭 Next Steps
- [Orders, Billing & Coupons](./docs/guide/orders-and-coupons.md): Tueen Database provides a comprehensive billing and commerce engine for Telegram bots selling digital subscriptions, physical goods, services, or VIP access.
  - 🛍️ The `Order` Lifecycle
  - 📦 Creating & Managing Orders
    * 1. Creating an Order
    * 2. Marking as Paid
  - 🎟️ Coupon & Discount Engine
    * 1. Creating a Coupon
    * 2. Applying a Coupon to an Order
  - 🧭 Next Steps
- [Subscriptions & VIP Access](./docs/guide/subscriptions.md): Many Telegram bots offer premium memberships, private channel access, or tiered VIP features based on time-limited memberships.
  - 💎 The `Subscription` Model
  - 🛠️ Managing Subscriptions
    * 1. Checking Active Status
    * 2. Granting or Extending Time
  - 📜 Subscription Audit Trail (`SubscriptionTransaction`)
  - 🧭 Next Steps
- [Telegram Mini App (TMA) Sessions & Auth 🌐](./docs/guide/mini-app-sessions.md): Tueen Database provides a built-in, production-grade session management and authentication layer tailored specifically for Telegram Mini Apps (TMAs) and WebApps.
  - 🏛️ Architecture Overview
  - ⚡ Creating a TMA Session
  - 🔐 Authenticating Requests
  - 🚪 Session Revocation & Logout
    * Revoking a Single Session (Logout)
    * Revoking All Sessions (Logout Everywhere)
    * Listing Active Devices/Sessions
  - 🧹 Maintenance & Housekeeping
  - 🪝 PHP 8.4 Virtual Property Hooks
- [High-Volume Broadcast Engine](./docs/guide/broadcast-engine.md): Broadcasting messages to thousands or millions of Telegram chats requires reliable chunking, offset tracking, chat type filtering, and real-time delivery telemetry.
  - 📢 The `BroadcastMessage` Entity
    * Key Attributes:
  - 🛠️ Executing a Broadcast Campaign
    * 1. Creating the Broadcast
    * 2. Chunked Delivery Loop (Worker Job)
  - 🧭 Next Steps
- [Content Entities, CMS & Message Relays](./docs/guide/content-entities.md): Beyond user profiles and payments, Telegram bots often manage structured content catalogs (shop products, FAQs, blog articles, support tickets) and cross-chat communication relays.
  - 🗃️ The `EntityItem` Model & Repository
    * 1. Creating and Querying Content Items
    * 2. Social Feedback (`like` & `rate`)
  - 📬 Cross-Chat Message Relays (`MessageRelayRepository`)
    * Code Example: Implementing the Relay
  - 🧭 Next Steps
- [Options, Cache & Data Store](./docs/guide/options-and-cache.md): Telegram bots manage three distinct categories of auxiliary state:
  - ⚙️ Global Bot Settings (`OptionRepository`)
    * 1. Reading and Writing Options
    * 2. Built-in In-Memory Caching
  - 📦 PSR-16 SimpleCache (`CacheRepository`)
  - 🗄️ Scoped & Binary Data Store (`DataRepository`)
  - 🧭 Next Steps
- [Audit Trails & Histories](./docs/guide/audit-histories.md): In multi-user bots and financial systems, maintaining a verifiable audit trail of every administrative action, balance adjustment, and state transition is essential.
  - 📜 The `History` Model
  - 🛠️ Logging Audit Events
    * Retrieving Timeline History
  - 🧭 Next Steps

### 🛡️ Advanced & Operations
- [Caching & Performance Tuning](./docs/guide/caching-and-performance.md): High-throughput Telegram bots process hundreds of webhooks per second during viral events, marketing campaigns, or active chat group discussions.
  - 🏎️ Multi-Tier Caching Architecture
    * Tier 1: Process-Level In-Memory Cache
    * Tier 2: Doctrine ORM Metadata & Query Cache
  - 🗄️ Database-Specific Hardening
    * SQLite Concurrency Hardening
    * MySQL Index Optimizations
  - 🧭 Next Steps
- [CLI Tooling (`tueen-db`)](./docs/guide/cli-tooling.md): Tueen Database ships with a built-in command-line console executable: bin/tueen-db.
  - 🛠️ Available Commands
  - 💻 Command Reference & Usage
    * Validating Mappings & Schema (`tueen:db:validate`)
    * Inspecting Engine Status (`tueen:db:status`)
    * Synchronizing Schema (`tueen:db:sync`)
  - 🧭 Next Steps
- [Extending Entities & Custom Migrations](./docs/guide/extending.md): Tueen Database is built for modular bot ecosystems. Third-party packages, plugins, or your own bot application can easily register new entities, custom tables, and custom migrations.
  - 🧩 Registering Custom Entity Paths
  - 📜 Registering Custom Migration Paths
  - ✍️ Example Custom Migration
  - 🧭 Next Steps
- [Symfony DI & Events](./docs/guide/symfony-integration.md): Tueen Database integrates seamlessly with Symfony components, including DependencyInjection, EventDispatcher, Validator, Serializer, and Security.
  - 🔌 Symfony DependencyInjection
  - 📢 Domain Events (`EventDispatcher`)
    * Listening to Events
  - 🧭 Next Steps

### 📖 API Catalog & Reference
- [Entity Models Catalog](./docs/reference/entities.md): Tueen Database provides 22+ specialized domain models optimized for Telegram bots.
  - 🏛️ Entity & Table Summary
  - 🪝 PHP 8.4 Property Hooks Reference
- [Domain Repositories Reference](./docs/reference/repositories.md): Tueen Database provides rich repository classes accessible via fluent methods on DatabaseManager.
  - 🏛️ Repositories Overview
  - 💻 Essential Method Signatures
    * `ChatRepository`
    * `ContactRepository`
    * `ChatXpRepository`
    * `WalletRepository`
    * `WithdrawalRepository`
    * `TreasuryRepository`
    * `OrderRepository`
    * `CouponRepository`
    * `SubscriptionRepository`
    * `BroadcastRepository`
    * `ReferralRepository`
    * `EntityItemRepository` (CMS)
    * `MessageRelayRepository`
    * `OptionRepository`
    * `CacheRepository` (PSR-16)
    * `DataRepository`
    * `TmaSessionRepository`
    * `HistoryRepository`
- [Enums & Value Objects Reference](./docs/reference/enums.md): Tueen Database utilizes native backed string enums for type safety, validation, and database storage.
  - 🏛️ Enum Catalog
    * 1. `ChatType` (`Tueen\Database\Enum\ChatType`)
    * 2. `OrderStatus` (`Tueen\Database\Enum\OrderStatus`)
    * 3. `BroadcastStatus` (`Tueen\Database\Enum\BroadcastStatus`)
    * 4. `CouponType` & `CouponStatus`
    * 5. `TaxMode` (`Tueen\Database\Enum\TaxMode`)
    * 6. `ReferralStatus` (`Tueen\Database\Enum\ReferralStatus`)
    * 7. `WithdrawalStatus` (`Tueen\Database\Enum\WithdrawalStatus`)

---
*Generated automatically from the official tueen/database documentation suite.*
