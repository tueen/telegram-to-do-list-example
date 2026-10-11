<?php

declare(strict_types=1);

use Tueen\Telegram\App;
use Tueen\Telegram\Telegram;
use Tueen\Telegram\Example\ToDoList\Flow\MainMenuFlow;
use Tueen\Telegram\Example\ToDoList\Middleware\ChatSyncMiddleware;

/**
 * Global routes and middleware configuration for Tueen Telegram Bot.
 *
 * @param Telegram $bot
 * @param App $app
 */
return function (Telegram $bot, App $app): void {
    // 1. Attach ChatSyncMiddleware to ensure user profiles are updated on every request
    $bot->middleware(new ChatSyncMiddleware());

    // 2. Set root conversation flow for zero-config fallback and /start integration
    $bot->setRootFlow(MainMenuFlow::class);
};
