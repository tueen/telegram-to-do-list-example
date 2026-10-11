<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Middleware;

use Tueen\Database\Enum\ChatType as DbChatType;
use Tueen\Telegram\Enums\ChatType as ApiChatType;
use Tueen\Telegram\Telegram;
use Tueen\Telegram\Types\Update;
use Tueen\Telegram\Example\ToDoList\Service\DatabaseService;

/**
 * Middleware executed on every incoming Telegram update.
 *
 * Automatically keeps the chat and user profile synchronized in the database,
 * updates active timestamps, and tracks blocked/unblocked state via my_chat_member.
 */
final class ChatSyncMiddleware
{
    public function __construct(
        private readonly DatabaseService $db = new DatabaseService()
    ) {}

    public function __invoke(Update $update, Telegram $bot, callable $next): mixed
    {
        $this->syncChatFromUpdate($update);
        $this->handleMyChatMemberUpdate($update);

        return $next($update, $bot);
    }

    private function syncChatFromUpdate(Update $update): void
    {
        $chat = $update->findChat();
        $user = $update->findUser();

        if ($chat === null && $user === null) {
            return;
        }

        $chatId = $chat?->id ?? $user?->id;
        if ($chatId === null) {
            return;
        }

        $username = $chat?->username ?? $user?->username;
        $firstName = $chat?->firstName ?? $user?->firstName;
        $lastName = $chat?->lastName ?? $user?->lastName;
        $languageCode = $user?->languageCode;
        $isBot = $user?->isBot ?? false;
        $isPremium = $user?->isPremium ?? false;

        $type = match ($chat?->type?->value ?? $chat?->type ?? 'private') {
            'group' => DbChatType::Group,
            'supergroup' => DbChatType::Supergroup,
            'channel' => DbChatType::Channel,
            default => DbChatType::Private,
        };

        // Automatically updates or creates the chat record and marks activeAt
        $this->db->chats()->findOrCreate(
            chatId: $chatId,
            username: $username,
            name: $chat?->title,
            type: $type,
            languageCode: $languageCode,
            firstName: $firstName,
            lastName: $lastName,
            isBot: $isBot,
            isPremium: $isPremium,
        );
    }

    private function handleMyChatMemberUpdate(Update $update): void
    {
        $myChatMember = $update->myChatMember;
        if ($myChatMember === null) {
            return;
        }

        $chatId = $myChatMember->chat?->id;
        if ($chatId === null) {
            return;
        }

        $newMember = $myChatMember->newChatMember;
        $status = is_object($newMember) && isset($newMember->status)
            ? (is_string($newMember->status) ? $newMember->status : ($newMember->status->value ?? ''))
            : '';

        // Blocked / kicked / left
        if (in_array($status, ['kicked', 'left'], true)) {
            $this->db->chats()->setAccessible($chatId, false);
            return;
        }

        // Active member or administrator
        if (in_array($status, ['member', 'administrator', 'creator'], true)) {
            $this->db->chats()->setAccessible($chatId, true);
        }
    }
}
