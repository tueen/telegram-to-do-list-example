<?php

declare(strict_types=1);

use Tueen\Telegram\Example\ToDoList\Flow\MainMenuFlow;

return [
    'token' => $_ENV['TELEGRAM_BOT_TOKEN'] ?? $_ENV['BOT_TOKEN'] ?? '',
    'secret_token' => $_ENV['TELEGRAM_SECRET_TOKEN'] ?? null,
    'webhook_url' => $_ENV['TELEGRAM_WEBHOOK_URL'] ?? null,

    'mode' => $_ENV['BOT_RUNNING_MODE'] ?? 'auto', // 'auto', 'polling', or 'webhook'
    'timeout' => 30.0,
    'connect_timeout' => 10.0,
    'retry_count' => 3,

    // Storage
    'storage_path' => __DIR__ . '/storage',
    'flow_storage' => __DIR__ . '/storage/flow',

    // Root Interactive Flow
    'root_flow' => MainMenuFlow::class,

    // Controllers directory for auto-discovery
    'controllers' => [
        __DIR__ . '/src/Controller',
    ],

    // Web Dashboard / Setup Wizard
    'setup' => [
        'enabled' => true,
        'title' => 'Tueen To-Do List Bot Dashboard',
    ],
];
