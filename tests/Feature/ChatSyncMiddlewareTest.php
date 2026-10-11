<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Tueen\Telegram\Telegram;
use Tueen\Telegram\Types\Update;
use Tueen\Telegram\Example\ToDoList\Middleware\ChatSyncMiddleware;
use Tueen\Telegram\Example\ToDoList\Service\DatabaseService;

class ChatSyncMiddlewareTest extends TestCase
{
    private DatabaseService $db;

    #[\Override]
    protected function setUp(): void
    {
        $this->db = DatabaseService::getInstance();
        $this->db->syncSchema();
    }

    public function testChatProfileUpdatedOnUserRequest(): void
    {
        $chatId = 777666555;
        $middleware = new ChatSyncMiddleware($this->db);
        $fakeBot = Telegram::fake();

        $updateData = [
            'update_id' => 1001,
            'message' => [
                'message_id' => 1,
                'date' => time(),
                'chat' => [
                    'id' => $chatId,
                    'type' => 'private',
                    'username' => 'alice_smith',
                    'first_name' => 'Alice',
                    'last_name' => 'Smith',
                ],
                'from' => [
                    'id' => $chatId,
                    'is_bot' => false,
                    'first_name' => 'Alice',
                    'last_name' => 'Smith',
                    'username' => 'alice_smith',
                    'language_code' => 'en',
                ],
                'text' => '/start',
            ],
        ];

        $update = new Update($updateData);

        // Run middleware
        $middleware($update, $fakeBot, function ($up, $b) {
            return 'processed';
        });

        // Verify record in database
        $chat = $this->db->chats()->find($chatId);
        $this->assertNotNull($chat);
        $this->assertSame($chatId, $chat->id);
        $this->assertSame('alice_smith', $chat->username);
        $this->assertSame('Alice', $chat->firstName);
        $this->assertSame('Smith', $chat->lastName);
        $this->assertSame('en', $chat->languageCode);
        $this->assertTrue($chat->accessible);
    }

    public function testMyChatMemberTracksBlockedAndUnblocked(): void
    {
        $chatId = 888777666;
        $middleware = new ChatSyncMiddleware($this->db);
        $fakeBot = Telegram::fake();

        // 1. Initial register
        $this->db->chats()->findOrCreate($chatId, username: 'bob_user', firstName: 'Bob');
        $chat = $this->db->chats()->find($chatId);
        $this->assertTrue($chat->accessible);

        // 2. User blocks the bot (kicked)
        $blockedUpdate = new Update([
            'update_id' => 1002,
            'my_chat_member' => [
                'chat' => [
                    'id' => $chatId,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => $chatId,
                    'is_bot' => false,
                    'first_name' => 'Bob',
                ],
                'date' => time(),
                'old_chat_member' => ['status' => 'member'],
                'new_chat_member' => ['status' => 'kicked'],
            ],
        ]);

        $middleware($blockedUpdate, $fakeBot, fn($u, $b) => null);

        $chatRefreshed = $this->db->chats()->find($chatId);
        $this->assertFalse($chatRefreshed->accessible);

        // 3. User unblocks the bot (member)
        $unblockedUpdate = new Update([
            'update_id' => 1003,
            'my_chat_member' => [
                'chat' => [
                    'id' => $chatId,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => $chatId,
                    'is_bot' => false,
                    'first_name' => 'Bob',
                ],
                'date' => time(),
                'old_chat_member' => ['status' => 'kicked'],
                'new_chat_member' => ['status' => 'member'],
            ],
        ]);

        $middleware($unblockedUpdate, $fakeBot, fn($u, $b) => null);

        $chatReactivated = $this->db->chats()->find($chatId);
        $this->assertTrue($chatReactivated->accessible);
    }
}
