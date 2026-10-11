<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Tueen\Telegram\Telegram;
use Tueen\Telegram\Types\Update;
use Tueen\Telegram\Example\ToDoList\Controller\ChatMemberController;
use Tueen\Telegram\Example\ToDoList\Service\DatabaseService;

class ChatMemberControllerTest extends TestCase
{
    private DatabaseService $db;
    private int $testChatId = 66554433;

    #[\Override]
    protected function setUp(): void
    {
        $this->db = DatabaseService::getInstance();
        $this->db->syncSchema();
        $this->db->chats()->findOrCreate($this->testChatId, username: 'testuser');
    }

    public function testBlockAndUnblockTogglesAccessibility(): void
    {
        $controller = new ChatMemberController();
        $fakeBot = Telegram::fake();

        // 1. Initial state is accessible
        $chat = $this->db->chats()->find($this->testChatId);
        $this->assertTrue($chat->accessible);

        // 2. User blocks the bot (kicked)
        $blockedUpdate = new Update([
            'update_id' => 2001,
            'my_chat_member' => [
                'chat' => ['id' => $this->testChatId, 'type' => 'private'],
                'from' => ['id' => $this->testChatId, 'is_bot' => false, 'first_name' => 'Test'],
                'date' => time(),
                'old_chat_member' => ['status' => 'member'],
                'new_chat_member' => ['status' => 'kicked'],
            ],
        ]);

        $controller->onMyChatMember($blockedUpdate, $fakeBot);

        $chatRefreshed = $this->db->chats()->find($this->testChatId);
        $this->assertFalse($chatRefreshed->accessible);

        // 3. User unblocks the bot (member)
        $unblockedUpdate = new Update([
            'update_id' => 2002,
            'my_chat_member' => [
                'chat' => ['id' => $this->testChatId, 'type' => 'private'],
                'from' => ['id' => $this->testChatId, 'is_bot' => false, 'first_name' => 'Test'],
                'date' => time(),
                'old_chat_member' => ['status' => 'kicked'],
                'new_chat_member' => ['status' => 'member'],
            ],
        ]);

        $controller->onMyChatMember($unblockedUpdate, $fakeBot);

        $chatReactivated = $this->db->chats()->find($this->testChatId);
        $this->assertTrue($chatReactivated->accessible);
    }
}
