<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Tueen\Telegram\Example\ToDoList\Flow\MainMenuFlow;
use Tueen\Telegram\Example\ToDoList\Flow\TaskCreateFlow;
use Tueen\Telegram\Example\ToDoList\Service\DatabaseService;
use Tueen\Telegram\Flow\FlowManager;
use Tueen\Telegram\Flow\FlowState;
use Tueen\Telegram\Telegram;
use Tueen\Telegram\Types\Update;

class TaskCreateFlowTest extends TestCase
{
    private DatabaseService $db;
    private int $testChatId = 99887766;

    #[\Override]
    protected function setUp(): void
    {
        $this->db = DatabaseService::getInstance();
        $this->db->syncSchema();
        $this->db->chats()->findOrCreate($this->testChatId, username: 'test_creator');
    }

    public function testCancelWhenPushedFromMainMenuResumesParent(): void
    {
        $bot = Telegram::fake();
        $manager = new FlowManager();
        $bot->setRootFlow(MainMenuFlow::class);

        $state = new FlowState(
            flowClass: TaskCreateFlow::class,
            currentStep: 'start',
            data: ['wizard_step' => 'title'],
            flowStack: [
                [
                    'flowClass' => MainMenuFlow::class,
                    'currentStep' => 'start',
                    'data' => [],
                    'messageId' => 200,
                    'breadcrumb' => '🏠 Home',
                ]
            ],
            messageId: 200
        );

        $update = new Update([
            'update_id' => 9001,
            'callback_query' => [
                'id' => 'cb_cancel_1',
                'from' => ['id' => $this->testChatId, 'is_bot' => false, 'first_name' => 'Tester'],
                'message' => [
                    'message_id' => 200,
                    'date' => time(),
                    'chat' => ['id' => $this->testChatId, 'type' => 'private'],
                    'text' => '📝 Step 1 of 7',
                ],
                'data' => 'create:cancel',
            ]
        ]);

        $flow = new TaskCreateFlow();
        $flow->init($bot, $update, $this->testChatId, $this->testChatId, $state, $manager);

        $handled = $flow->handleUpdate($update);
        $this->assertTrue($handled);

        // Verify toast was triggered in callback answer
        $bot->assertSent('answerCallbackQuery', function (array $params) {
            return $params['callback_query_id'] === 'cb_cancel_1'
                && str_contains($params['text'] ?? '', 'Task creation cancelled');
        });

        // Verify parent screen (MainMenuFlow) was rendered in-place via editMessageText
        $bot->assertSent('editMessageText', function (array $params) {
            return (int)$params['chat_id'] === $this->testChatId
                && (int)$params['message_id'] === 200
                && str_contains($params['text'], 'Task Manager Dashboard');
        });
    }

    public function testCancelWhenFlowStackIsEmptyTransitionsToHome(): void
    {
        $bot = Telegram::fake();
        $manager = new FlowManager();
        $manager->rootFlow = MainMenuFlow::class;
        $bot->setRootFlow(MainMenuFlow::class);

        $state = new FlowState(
            flowClass: TaskCreateFlow::class,
            currentStep: 'start',
            data: ['wizard_step' => 'title'],
            flowStack: [],
            messageId: 205
        );

        $update = new Update([
            'update_id' => 9002,
            'callback_query' => [
                'id' => 'cb_cancel_2',
                'from' => ['id' => $this->testChatId, 'is_bot' => false, 'first_name' => 'Tester'],
                'message' => [
                    'message_id' => 205,
                    'date' => time(),
                    'chat' => ['id' => $this->testChatId, 'type' => 'private'],
                    'text' => '📝 Step 1 of 7',
                ],
                'data' => 'create:cancel',
            ]
        ]);

        $flow = new TaskCreateFlow();
        $flow->init($bot, $update, $this->testChatId, $this->testChatId, $state, $manager);

        $handled = $flow->handleUpdate($update);
        $this->assertTrue($handled);

        // Verify toast was sent
        $bot->assertSent('answerCallbackQuery', function (array $params) {
            return $params['callback_query_id'] === 'cb_cancel_2'
                && str_contains($params['text'] ?? '', 'Task creation cancelled');
        });

        // Verify home screen (MainMenuFlow) was rendered in-place via editMessageText
        $bot->assertSent('editMessageText', function (array $params) {
            return (int)$params['chat_id'] === $this->testChatId
                && (int)$params['message_id'] === 205
                && str_contains($params['text'], 'Task Manager Dashboard');
        });
    }
}
