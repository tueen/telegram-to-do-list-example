<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Tests\Feature;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tueen\Telegram\Flow\FlowManager;
use Tueen\Telegram\Flow\FlowState;
use Tueen\Telegram\Telegram;
use Tueen\Telegram\Types\Update;
use Tueen\Telegram\Example\ToDoList\Flow\TaskDetailFlow;
use Tueen\Telegram\Example\ToDoList\Service\DatabaseService;
use Tueen\Telegram\Example\ToDoList\Service\UserSettingsService;

class TaskDetailFlowTest extends TestCase
{
    private DatabaseService $db;
    private UserSettingsService $settings;
    private int $testChatId = 99887766;

    #[\Override]
    protected function setUp(): void
    {
        $this->db = DatabaseService::getInstance();
        $this->db->syncSchema();
        $this->settings = new UserSettingsService($this->db);
        $this->db->chats()->findOrCreate($this->testChatId, username: 'flow_user');
    }

    public function testSnoozeInMinutesUpdatesTaskAndCreatesReminder(): void
    {
        $task = $this->db->tasks()->createTask(
            chatId: $this->testChatId,
            title: 'Prepare meeting slides',
        );

        $fakeBot = Telegram::fake();
        $manager = new FlowManager();

        $state = new FlowState(
            flowClass: TaskDetailFlow::class,
            currentStep: 'render',
            data: ['taskId' => $task->id, 'view_mode' => 'snooze_menu'],
            messageId: 100
        );

        $update = new Update([
            'update_id' => 7001,
            'callback_query' => [
                'id' => 'cb_snooze_15',
                'from' => ['id' => $this->testChatId, 'is_bot' => false, 'first_name' => 'Tester'],
                'message' => [
                    'message_id' => 100,
                    'chat' => ['id' => $this->testChatId, 'type' => 'private'],
                    'text' => 'Task Details',
                ],
                'data' => 'snooze:min:15',
            ],
        ]);

        $flow = new TaskDetailFlow();
        $flow->init($fakeBot, $update, $this->testChatId, $this->testChatId, $state, $manager);

        $handled = $flow->handleUpdate($update);
        $this->assertTrue($handled);

        // Check task reminder was set
        $refreshed = $this->db->tasks()->findForChat($this->testChatId, $task->id);
        $this->assertNotNull($refreshed);
        $this->assertNotNull($refreshed->reminderAt);

        // Check scheduled reminder was created
        $reminders = $this->db->reminders()->findUpcomingForChat($this->testChatId);
        $this->assertNotEmpty($reminders);
        $this->assertSame($task->id, $reminders[0]->task->id);
    }

    public function testSnoozeTomorrowMorningAndCancelReminder(): void
    {
        $task = $this->db->tasks()->createTask(
            chatId: $this->testChatId,
            title: 'Morning routine task',
        );

        $fakeBot = Telegram::fake();
        $manager = new FlowManager();

        $state = new FlowState(
            flowClass: TaskDetailFlow::class,
            currentStep: 'render',
            data: ['taskId' => $task->id, 'view_mode' => 'snooze_menu'],
            messageId: 101
        );

        // 1. Snooze tomorrow morning
        $updateTomorrow = new Update([
            'update_id' => 7002,
            'callback_query' => [
                'id' => 'cb_tmr_morning',
                'from' => ['id' => $this->testChatId, 'is_bot' => false, 'first_name' => 'Tester'],
                'message' => [
                    'message_id' => 101,
                    'chat' => ['id' => $this->testChatId, 'type' => 'private'],
                    'text' => 'Task Details',
                ],
                'data' => 'snooze:tomorrow_morning',
            ],
        ]);

        $flow = new TaskDetailFlow();
        $flow->init($fakeBot, $updateTomorrow, $this->testChatId, $this->testChatId, $state, $manager);
        $flow->handleUpdate($updateTomorrow);

        $refreshed = $this->db->tasks()->findForChat($this->testChatId, $task->id);
        $this->assertNotNull($refreshed->reminderAt);

        // 2. Cancel reminder
        $updateCancel = new Update([
            'update_id' => 7003,
            'callback_query' => [
                'id' => 'cb_cancel_remind',
                'from' => ['id' => $this->testChatId, 'is_bot' => false, 'first_name' => 'Tester'],
                'message' => [
                    'message_id' => 101,
                    'chat' => ['id' => $this->testChatId, 'type' => 'private'],
                    'text' => 'Task Details',
                ],
                'data' => 'snooze:cancel',
            ],
        ]);

        $flowCancel = new TaskDetailFlow();
        $flowCancel->init($fakeBot, $updateCancel, $this->testChatId, $this->testChatId, $state, $manager);
        $flowCancel->handleUpdate($updateCancel);

        $refreshedAfterCancel = $this->db->tasks()->findForChat($this->testChatId, $task->id);
        $this->assertNull($refreshedAfterCancel->reminderAt);
    }

    public function testUnhandledNotificationCallbacksPassThrough(): void
    {
        $task = $this->db->tasks()->createTask(
            chatId: $this->testChatId,
            title: 'Passive Task',
        );

        $fakeBot = Telegram::fake();
        $manager = new FlowManager();

        $state = new FlowState(
            flowClass: TaskDetailFlow::class,
            currentStep: 'render',
            data: ['taskId' => $task->id],
            messageId: 102
        );

        $update = new Update([
            'update_id' => 7004,
            'callback_query' => [
                'id' => 'cb_external_snooze',
                'from' => ['id' => $this->testChatId, 'is_bot' => false, 'first_name' => 'Tester'],
                'message' => [
                    'message_id' => 888,
                    'chat' => ['id' => $this->testChatId, 'type' => 'private'],
                    'text' => 'External Notification',
                ],
                'data' => 'remind:snooze_99_15',
            ],
        ]);

        $flow = new TaskDetailFlow();
        $flow->init($fakeBot, $update, $this->testChatId, $this->testChatId, $state, $manager);

        $handled = $flow->handleUpdate($update);

        // When passed through, handleUpdate returns false so Router can process it
        $this->assertFalse($handled);
        $this->assertTrue($flow->isPassedThrough);
    }
}
