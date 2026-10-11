<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Controller;

use Tueen\Telegram\Routing\Attributes\OnUpdate;
use Tueen\Telegram\Telegram;
use Tueen\Telegram\Types\Update;
use Tueen\Telegram\Example\ToDoList\Service\DatabaseService;

class ChatMemberController
{
    private DatabaseService $db;

    public function __construct()
    {
        $this->db = DatabaseService::getInstance();
    }

    #[OnUpdate('my_chat_member', priority: true)]
    public function onMyChatMember(Update $update, Telegram $bot): void
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

        // If the user has blocked or kicked the bot:
        if (in_array($status, ['kicked', 'left'], true)) {
            $this->db->chats()->setAccessible($chatId, false);
            return;
        }

        // If the user unblocked or rejoined the bot:
        if (in_array($status, ['member', 'administrator', 'creator'], true)) {
            $this->db->chats()->setAccessible($chatId, true);
        }
    }
}
