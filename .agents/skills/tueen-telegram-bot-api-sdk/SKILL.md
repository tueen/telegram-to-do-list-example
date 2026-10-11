---
name: tueen-telegram-bot-api-sdk
description: >-
  The official agent skill for tueen/telegram (The Royal Telegram Bot SDK for Modern PHP).
  Provides complete architectural guidelines, PHP 8.4+ standards, property hooks,
  asymmetric visibility, flows, and complete offline documentation guides.
---

# Tueen Telegram Bot API SDK Skill

Welcome to the **tueen/telegram** agent skill. This skill packages the complete offline documentation and coding standards for developing Telegram bots using modern PHP 8.4+.

---

## 👑 Library Identity & Tenets

- **Package Name:** `tueen/telegram`
- **GitHub Repository:** [tueen/telegram on GitHub](https://github.com/tueen/telegram/)
- **Slogan:** *The Royal Telegram Bot SDK for Modern PHP*
- **Target Runtime:** PHP 8.4+ (strict types, property hooks, asymmetric visibility `private(set)`, pipeline pattern).
- **Coverage:** Complete Telegram Bot API (all methods, types, and property enums).
- **Bot API Version Constant:** `Telegram::BOT_API_VERSION`
- **Online Documentation Base URL:** https://tueen.github.io/telegram/

> **🔎 Deep Source Inspection & Repository Fallback:**
> If the bundled documentation guides or offline references are not sufficient for your use case, or if you need to inspect raw class implementations, method signatures, test suites, or latest features, refer directly to the official GitHub repository:
> **https://github.com/tueen/telegram/**

---

## 💎 Core Architectural Rules for AI Agents

When writing, refactoring, or generating code using `tueen/telegram`, AI assistants MUST adhere to these rules:

1. **Strict Types:** Every file must start with `declare(strict_types=1);`.
2. **Asymmetric Visibility:** Always use clean `private(set)` across response types (never `public private(set)`):
   ```php
   private(set) int $id;
   private(set) ?string $username;
   ```
3. **Property Hooks:** Prefer modern PHP 8.4 property hooks over verbose getters/setters:
   ```php
   public string $fullName {
       get => trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? ''));
   }
   ```
4. **Universal `ok()` Check:**
   All response objects inherit from base `Type` and supply `$result->ok(): bool` and `$result->isOk(): bool`.
5. **Dual Error Handling Modes:**
   - Default: Throws typed exceptions (`TelegramException`, `RequestException`, `RateLimitException`).
   - Non-throwing mode: Enabled via `Telegram::create($token)->withErrorObjectMode()->build()`, returns typed `Error` objects.
6. **Forward Compatibility:**
   Unknown future Telegram fields are captured in `$extra` and accessible via magic property access or `ArrayAccess`.
7. **Pipeline Pattern & Middleware:**
   All middlewares implement `MiddlewareInterface`. Handlers and updates are callable-friendly and support the PHP 8.5 Pipe Operator (`|>`).
8. **Repository & Source Inspection Fallback:**
   If the documentation or guides do not cover a specific edge case, advanced configuration, or internal implementation details, inspect the source code, unit tests, and examples directly on GitHub: https://github.com/tueen/telegram/

---

## 📚 Complete Offline Documentation Map

All documentation guides are bundled alongside this skill in the `./docs/` folder:


### Getting Started
- [Getting Started](./docs/getting-started.md): > The Royal Telegram Bot SDK for Modern PHP
  - Introduction
  - Requirements
  - Installation
  - Client Initialization
    * Direct Initialization
    * Fluent ConfigBuilder
  - Supported Bot API Version
  - First API Call: `getMe`
  - Sending a Message
  - Handling Incoming Updates
  - Next Steps
- [The Telegram Client](./docs/telegram-client.md): The Tueen\Telegram\Telegram class (conventionally instantiated as $bot) is the primary entry point and client facade of the tueen/telegram library. It provides a unified, strictly typed interface for sending Bot API requests, listening for updates, managing multi-step flows, and resolving contextual parameters.
  - 🚀 1. Instantiation
    * Basic Instantiation
    * Advanced Instantiation with `ConfigBuilder`
  - ⚡ 2. The Three Primary Roles of `$bot`
    * 1. Direct Bot API Client
    * 2. Update Router & Dispatcher
    * 3. Contextual Entity Resolver (PHP 8.4 Property Hooks)
  - 💡 3. Client Best Practices
    * 1. Always Use PHP Named Arguments
    * 2. Reusing a Single Client Instance
    * 3. Organizing Complex Bots with Controllers
  - 🏛️ 4. Architecture & Decoupled Subsystems
    * When to Access Subsystems Directly:
  - 🧭 5. Complete Method & Property Reference
    * 🏛️ Class Constants & Public Properties
    * A. Initialization & Testing Factory
    * B. Execution & Running Modes
    * C. Update Pipeline & Middleware
    * D. Exception & Error Handling
    * E. Update Routing & Controllers
    * F. Multi-Step Conversational Flows
    * G. Contextual Accessors & Property Hooks
    * H. Bot API Method Calls & File Operations
    * I. Underlying Subsystems & Dependency Injection
- [Configuration](./docs/configuration.md): tueen/telegram features an immutable configuration architecture paired with an expressive, fluent ConfigBuilder. Every parameter — from networking timeouts and persistent connection pooling to progress event hooks, running modes, and error handling strategies — is strictly typed and discoverable via IDE autocompletion.
  - 🛠️ 1. Creating Configuration
    * Fluent Builder via `Telegram::create()`
    * Direct Instantiation of `Config`
  - 🧭 2. Detailed Configuration Reference
    * 🌐 Server & Credentials
    * ⚡ HTTP Client, Networking & Transport
    * 📊 Progress Tracking Callbacks
    * 🛡️ Error Handling Strategy
    * 🚀 Running Modes & Resiliency
    * 🔄 Conversation Flow & Orchestration
    * 📋 Logging & Observability
    * 💉 Dependency Injection & Container
    * ⚡ Client Instantiation Shortcuts
  - 🧬 3. Immutability & Modern PHP 8.5 Patterns
  - 🌐 4. Standard URI & URL Resolution Properties
  - 💡 5. Real-World Configuration Recipes
    * Recipe 1: Production Webhook with Persistent cURL & Monolog
    * Recipe 2: High-Volume CLI Long-Polling Worker
    * Recipe 3: Tor / SOCKS5 Proxy for Restricted Environments
    * Recipe 4: Self-Hosted Local Bot API Server (2GB Uploads)
    * Recipe 5: Laravel & Symfony PSR-11 Container Integration
    * Recipe 6: Adaptive AutoMode Execution (Unified CLI & HTTP Webhook)

### Core Essentials
- [Calling Methods & Types](./docs/methods-and-types.md): tueen/telegram provides native, strictly typed support for Telegram Bot API methods and types with full IDE autocompletion, enums, computed property hooks, and forward-compatible fallbacks.
  - 🚀 1. Method Invocations
    * Dynamic Method Calls (Recommended)
    * Method Objects (For Custom Pipelines & Architecture)
    * Forward-Compatible Unknown / New Parameters
    * Passing Complex & Nested Types (Objects or Arrays)
  - 🏛️ 2. The Universal Base `Type` Class
    * Core Features of `Type`:
  - ⚙️ 3. The Base `Method` Class
    * Core Responsibilities of `Method`:
  - 🧩 4. Concerns & Computed Helpers
  - 📦 5. Custom Result Wrapper Types
  - 🧭 6. Methods & Types API Reference
    * 🏛️ Base `Type` Class (`Tueen\Telegram\Types\Type`)
    * ⚙️ Base `Method` Class (`Tueen\Telegram\Methods\Method`)
    * 🎯 Contextual Parameter Auto-Injection
    * 📦 Custom Wrapper Types (`Tueen\Telegram\Types\Custom\`)
- [Fluent Keyboard Builders](./docs/keyboards.md): tueen/telegram provides modern, fluent builders for creating both Inline Keyboards (InlineKeyboardMarkup) and Reply Keyboards (ReplyKeyboardMarkup / ReplyKeyboardRemove).
  - 🔘 1. Inline Keyboards (`InlineKeyboard`)
    * Basic Usage
    * Supported Button Types
    * Auto-Chunking Buttons (`chunk`)
  - ⌨️ 2. Reply Keyboards (`ReplyKeyboard`)
    * Basic Usage
    * Supported Features & Button Types
    * Dismissing Keyboards (`ReplyKeyboard::remove`)
  - 🧭 3. Keyboard Builders API Reference
    * 🔘 `InlineKeyboard` Builder (`Tueen\Telegram\Keyboards\InlineKeyboard`)
    * ⌨️ `ReplyKeyboard` Builder (`Tueen\Telegram\Keyboards\ReplyKeyboard`)
- [Text Formatting & Escaping](./docs/formatting.md): Telegram supports rich text formatting via HTML, MarkdownV2, and legacy Markdown. However, unescaped characters (such as , , [, <, >, &) frequently cause Telegram API errors like 400 Bad Request: can't parse entities.
  - 🎨 1. Fluent `Text` Builder
    * Direct API Integration
    * HTML Formatting (`Text::html`)
    * MarkdownV2 Formatting (`Text::markdownV2`)
  - 🔗 2. Nested Formatting Styles
  - 🛡️ 3. Low-Level Escaping with `Escape`
  - 🧭 4. Formatting API Catalog
    * 🎨 `Text` Builder (`Tueen\Telegram\Formatting\Text`)
    * 🛡️ `Escape` Helper (`Tueen\Telegram\Formatting\Escape`)
- [File Upload & Download](./docs/file-upload-download.md): tueen/telegram makes file transfers seamless using the InputFile wrapper, streaming HTTP transports, and real-time upload/download progress callbacks.
  - 📤 1. File Uploads with `InputFile`
    * From Local File Path
    * From Stream Resource
    * From Raw In-Memory String
  - 🖼️ 2. Sending Media Groups (Albums)
  - ⚡ 3. Real-Time Upload Progress
  - 📥 4. Streaming File Downloads with Progress
  - 🧭 5. File Transfer API Catalog
    * 📦 `InputFile` Wrapper (`Tueen\Telegram\Types\Custom\InputFile`)
    * 📥 Client Download APIs (`Telegram`)

### Updates & Routing
- [Running Modes](./docs/running-modes.md): tueen/telegram features an elegant execution strategy separating how updates are received from how they are processed.
  - 🚀 Unified Execution with `$bot->run()`
    * 1. Handler Signature
    * 2. Invokable Handler Classes
    * 3. Multiple Handlers & Middleware Chains
  - 📦 The `$bot->update` Property
  - 1. Webhook Mode (`WebhookMode`)
    * Basic Setup
    * Secret Token Verification
    * Fast Response (`safeResponse`)
    * Integration with Frameworks (Laravel, Symfony, PSR-7)
  - 2. Long-Polling Mode (`PollingMode`)
    * Continuous Listening
    * Multi-Process Concurrency (`forkProcess`)
    * Custom Process Dispatcher
    * Features of `PollingMode`:
    * Manual Generator Iteration
  - Native Telegram Bot API Method: `getUpdates`
  - Setting Running Mode via Config
  - ⚡ AutoMode: Adaptive Execution (CLI Polling & HTTP Webhook)
    * Key Features of `AutoMode`:
    * Example 1: Fluent Builder Configuration
    * Example 2: Quick `$bot->autoRun()` Execution
  - 🔀 PHP 8.5 Pipe Operator (`|>`) Pipelines
    * 1. Fundamental Syntax & First-Class Callables
    * 2. Production Webhook Ingestion Pipeline
    * 3. Multi-Stage Filter & Guard Pipeline
    * 4. Direct Action & Response Pipelines
    * 5. Architectural Benefits in Tueen
  - 🧭 6. Running Modes API Catalog
    * 🌐 `WebhookMode` Runner (`Tueen\Telegram\Running\WebhookMode`)
    * 🔄 `PollingMode` Runner (`Tueen\Telegram\Running\PollingMode`)
    * 🔀 `AutoMode` Runner (`Tueen\Telegram\Running\AutoMode`)
- [Update Routing & Attributes](./docs/routing.md): Instead of writing monolithic switch or if/else ladders inside your update handlers, tueen/telegram features an expressive, type-safe Update Router.
  - ⚡ 1. Fluent Routing
  - 🎯 2. Parameter Extraction in Routes
  - 🏛️ 3. Attribute-Driven Controllers
    * Example Controller
    * Registering Controllers
  - 🧭 4. Routing API Catalog
    * 🏷️ Routing Attributes (`Tueen\Telegram\Routing\Attributes\`)
  - 🚨 4. Priority Routes (Overriding Active Flows)
    * Registering Priority Routes
    * Flow Customization & Interception
    * 🛣️ `Router` Methods (`Tueen\Telegram\Routing\Router`)
- [Update & Message Helpers](./docs/update-and-message-helpers.md): tueen/telegram provides helper methods and computed properties on Update, Message, Chat, and User objects to simplify event processing and eliminate boilerplate null checks.
  - ⚡ 1. Update Helpers (`Update`)
    * Checking Update Types (`isType`)
    * Smart In-Memory Model Finders
    * ID & File Shortcut Finders
    * Modern PHP 8.4 Property Hooks
  - 🌐 2. Client-Level Contextual Shortcuts (`Telegram` & `Context`)
  - 💬 3. Message Helpers (`Message`)
    * Checking Message Types (`isType` & `isMessage`)
    * Checking Replies (`isRepliedToMessage`)
    * Extracting Any Text, Caption, or Rich Text (`findAnyText`)
    * Media & File ID Extraction (`findFileId` & `findLargestPhoto`)
    * Bot Command Parsing
  - 👤 4. Chat & User Helpers
    * Automatic `fullName`
  - 🧭 5. Complete Helpers API Catalog
    * ⚡ `Update` Helpers (`HasUpdateHelpers`)
    * 💬 `Message` Helpers (`HasMessageHelpers`)
    * 👤 `Chat` & `User` Helpers (`HasChatHelpers` & `HasUserHelpers`)

### Conversational Flows
- [Flow & Interactive Screen Ecosystem](./docs/flow/index.md): Multi-step dialogues and interactive bot screens (such as user onboarding wizards, settings panels, catalogs, e-commerce checkouts, surveys, and support ticket flows) are notoriously difficult to maintain when managed through scattered database flags, nested if/else statements, or naive polling handlers.
  - 🏛️ Two-Tier Architecture Comparison
  - 🧭 Flow Documentation Sitemap
  - ⚡ Quickstart: Interactive Menu
- [Conversational Flows (Linear Forms)](./docs/flow/conversational-flows.md): The base Tueen\Telegram\Flow\Flow class provides a linear state machine for multi-step textual dialogues, surveys, registration forms, and multi-prompt wizards.
  - 🏛️ Anatomy of a Conversational Flow
  - 🧭 Step Navigation Methods
    * `$this->to(string $stepMethod, array $data = []): static`
    * `$this->stay(?string $replyMessage = null, mixed $keyboard = null): static`
    * `$this->back(?string $replyMessage = null): static`
    * `$this->finish(): void`
    * `$this->cancel(?string $replyMessage = 'Operation cancelled.'): void`
  - 🎯 Filtering Allowed Updates (`allowedUpdates` & `#[AllowedUpdates]`)
    * Using the `#[AllowedUpdates]` Attribute
    * Using the `$allowedUpdates` Property or Method
    * Global Default via Configuration
  - 🕹️ External Flow Inspection & Control (`$bot->flow()`)
    * The Fluent `FlowSession` Interface
    * Controlling Flows from Outside
    * Direct Facade Shortcuts on `$bot` & `$app`
- [Interactive Screens & Zero-Flicker Transitions](./docs/flow/interactive-screens.md): While standard Flow classes handle linear textual forms, InteractiveFlow is built for rich, menu-driven bots, dashboards, e-commerce product catalogs, and interactive control panels.
  - ⚡ Zero-Flicker In-Place Editing
    * Automatic Fallback Recovery
  - 🖥️ The `Screen` Builder
    * Screen Builder Methods
  - 🔄 Refreshing Screens In-Place
  - 🔔 Native Telegram Alerts & Toasts
  - 📌 Sticky Screen / Pin to Bottom (`resendAtBottom`)
  - 🖼️ Media Screens (Photos, Videos, Animations)
  - ⚠️ Confirmation Dialogs
  - ☑️ Multi-Select Checklist Grid (`withChecklist`)
    * Toggling Checklist Items
  - 🪜 Step Wizard & Progress Stepper (`withStepper`)
    * Supported Stepper Styles
  - 📺 Single-Screen Support & `replaceFlow()`
    * The `#[SingleScreen]` Attribute
    * Dedicated `$bot->replaceFlow()` API
    * Overriding at Runtime via `startFlow()`
- [Lifecycle Hooks](./docs/flow/lifecycle-hooks.md): InteractiveFlow provides a predictable, component-based lifecycle. You can hook into each phase to load data, persist state, intercept exits, or update screens.
  - 🪝 Lifecycle Reference
    * `onCreate(): void`
    * `onStart(): void`
    * `onResume(mixed $result = null): void`
    * `onPause(): void`
    * `beforeRender(Screen $screen): void`
    * `afterRender(mixed $sentMessage): void`
    * `onUnhandled(Update $update): void`
    * `canInterrupt(Update $update): bool`
- [Keyboards & Action Binding](./docs/flow/keyboards-and-actions.md): InteractiveFlow decouples callback routing from tedious manual string checking using modern PHP 8 attributes: #[Action] and #[Input].
  - 🎯 The `#[Action]` Attribute
    * 1. Literal Action Patterns
    * 2. Parameterized Action Patterns
  - ✍️ Free-Text Inputs with `#[Input]`
  - 🎛️ Switching Keyboard Types Seamlessly
  - 📄 Automatic Pagination Bar
  - ⏱️ Action Rate-Limiting & Debouncing (`#[Debounce]`)
  - 🛡️ Channel Membership Guard (`#[RequireMember]`)
    * Guard Workflow:
- [Navigation Stack & Root Flow](./docs/flow/navigation-and-stack.md): InteractiveFlow features a hierarchical navigation stack that models menus and multi-layered screens just like modern UI frameworks.
  - 🥞 Stack Navigation: `push()` and `pop()`
    * `$this->push(string $childFlowClass, array $data = []): void`
    * `$this->pop(mixed $result = null): void`
  - 🧭 Navigation Buttons: `🔙` Back and `🏠` Home
  - 🏠 Configuring `rootFlow` & Handling `/start`
    * Global `/start` Behavior
  - 🍞 Breadcrumbs Trail (`withBreadcrumbs`)
  - 🛡️ Resilience & Missing Class Fallback
    * 1. Unwinding Navigation Stack to Parent Flows
    * 2. Fallback to `rootFlow` (Default Flow)
    * 3. Missing Step Recovery (`onMissingStep`)
    * 4. Graceful Router Fallthrough
- [State Storage Drivers](./docs/flow/state-storage.md): Flow sessions and interactive screen states are persisted between updates using pluggable state storage drivers.
  - 💾 Available Storage Drivers
    * 1. `FileStateStore` (Default for Webhooks)
    * 2. `MemoryStateStore` (Default for CLI Polling)
    * 3. `RedisStateStore` (High-Concurrency & Distributed)
    * 4. `Psr16StateStore` (Framework Adapters)

### Application & Production
- [Zero-Config App & Web Dashboard](./docs/app.md): Tueen\Telegram\App is the high-level application bootstrapper and orchestrator for tueen/telegram. It eliminates boilerplate configuration, automatically sets up file-based conversation flow storage, auto-discovers attribute controllers, and provides a built-in Web Setup Wizard and CLI tooling.
  - 🚀 1. The Single-File Entry Point
  - ⚡ 2. Adaptive Execution Environments
  - 🌐 3. Web Setup Wizard & Management Console
    * 1. First-Time Setup Wizard (When Token is Empty)
    * 2. Live Management Console (When Configured)
    * Securing the Dashboard
  - 💾 4. Automatic Flow State Storage (`storage/flow`)
  - 📁 5. Automatic Controller Discovery
  - 🩺 6. System Diagnostics (`doctor`)
  - 🧭 7. App & CLI API Reference
    * 🏛️ `App` Class (`Tueen\Telegram\App`)
    * 💻 CLI Administrative Commands
- [Pipeline & Middlewares](./docs/pipeline-middleware.md): Every outbound API request in tueen/telegram passes through an extensible onion middleware pipeline before reaching the underlying HTTP transport. This allows you to transparently handle retries, rate limits, request logging, tracing, and metric collection.
  - ⚡ 1. Native Built-In Middlewares
    * 1. `RetryMiddleware`
    * 2. `RateLimitMiddleware`
    * 3. `LoggingMiddleware`
  - 🛠️ 2. Authoring Custom Middleware
  - 🧭 3. Pipeline API Catalog
    * 🧱 Pipeline Middlewares (`Tueen\Telegram\Pipeline\`)
- [Error Handling & Lifecycle Hooks](./docs/error-handling-and-hooks.md): tueen/telegram provides a comprehensive, type-safe error handling architecture designed for PHP 8.4+ and PHP 8.5:
  - 1. Universal `ok()` / `isOk()` Check
  - 2. Strongly-Typed Exceptions Hierarchy
    * Catching Specific Exceptions
  - 3. Typed Error Reasons (`TelegramErrorCode` Enum)
  - 4. Error-Object Mode with Smart Helpers
  - 5. Catch All Errors (Including Network Errors)
  - 6. Declarative `#[ApiErrors]` & Method Metadata
  - 7. Lifecycle Event Hooks
  - 8. Incoming Update Error Handling (`catch` & `onUpdateError`)
    * Universal Catch
    * Typed Catch (Specific Exception Filtering)
    * Quick Reply Helper (`$bot->reply`)
  - 🧭 9. Error Handling & Hooks API Catalog
    * 🪝 Lifecycle Request Hooks (`Telegram`)
    * 🛡️ Incoming Update Exception Catchers (`Telegram`)
- [Testing & Fakes](./docs/testing.md): Testing Telegram bots often poses challenges: calling real Telegram API servers during automated tests is slow, rate-limited, and may inadvertently message real users.
  - 🧪 1. Quick Example
  - 🛠️ 2. Response Stubbing
    * Stubbing Successful Responses
    * Stubbing Telegram API Errors
  - 🔍 3. Inspecting Sent Requests
  - 🧭 4. Testing & Fakes API Catalog
    * 🛡️ Assertion Methods (`TelegramFake`)
    * 🎭 Stubbing & Inspection Methods (`TelegramFake`)
- [Enums Reference](./docs/enums.md): tueen/telegram provides Backed Enums for Telegram Bot API string constants, ensuring type safety and IDE autocompletion.
  - 1. Core Telegram Enums
    * `ParseMode`
    * `ChatAction`
    * `ChatType`
    * `ChatMemberStatus`
    * `DiceEmoji`
    * `ButtonStyle`
    * `BotCommandScopeType`
    * `PollType`
  - 2. Update & Message Enums
    * `UpdateType`
    * `MessageType`
  - 3. Complete Enums Reference

---
*Generated automatically from the official tueen/telegram documentation suite.*
