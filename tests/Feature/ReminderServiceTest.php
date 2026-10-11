<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Tests\Feature;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tueen\Telegram\Telegram;
use Tueen\Telegram\Example\ToDoList\Enum\ReminderType;
use Tueen\Telegram\Example\ToDoList\Enum\TaskPriority;
use Tueen\Telegram\Example\ToDoList\Service\DatabaseService;
use Tueen\Telegram\Example\ToDoList\Service\ReminderService;
use Tueen\Telegram\Example\ToDoList\Service\UserSettingsService;

class ReminderServiceTest extends TestCase
{
    private DatabaseService $db;
    private UserSettingsService $settings;
    private ReminderService $reminders;
    private int $testChatId = 99887766;

    #[\Override]
    protected function setUp(): void
    {
        $this->db = DatabaseService::getInstance();
        $this->db->syncSchema();
        $this->settings = new UserSettingsService($this->db);
        $this->reminders = new ReminderService($this->db, $this->settings);

        // Ensure chat exists and is accessible
        $this->db->chats()->findOrCreate($this->testChatId, username: 'tester', firstName: 'Test User');
    }

    public function testProcessDueRemindersSendsAndMarksSent(): void
    {
        $fakeBot = Telegram::fake();

        $task = $this->db->tasks()->createTask(
            chatId: $this->testChatId,
            title: 'Critical server maintenance',
            priority: TaskPriority::Urgent,
        );

        $reminder = $this->db->reminders()->createReminder(
            task: $task,
            remindAt: new DateTimeImmutable('-5 minutes'),
            type: ReminderType::Scheduled,
        );

        $this->assertFalse($reminder->isSent);

        $dispatched = $this->reminders->processDueReminders($fakeBot);
        $this->assertSame(1, $dispatched);

        $fakeBot->assertSent('sendMessage', function (array $params) {
            $markup = $params['replyMarkup'] ?? null;
            if ($markup !== null && isset($markup->inlineKeyboard)) {
                foreach ($markup->inlineKeyboard as $row) {
                    if (count($row) > 2) {
                        return false;
                    }
                }
            }

            return (int)$params['chat_id'] === $this->testChatId
                && str_contains($params['text'], 'Critical server maintenance');
        });
        $fakeBot->assertSentCount(1, 'sendMessage');

        $refreshed = $this->db->reminders()->find($reminder->id);
        $this->assertTrue($refreshed->isSent);
        $this->assertNotNull($refreshed->sentAt);
    }

    public function testProcessDueRemindersRespectsDisabledNotifications(): void
    {
        $fakeBot = Telegram::fake();
        $this->settings->setNotificationsEnabled($this->testChatId, false);

        $task = $this->db->tasks()->createTask(
            chatId: $this->testChatId,
            title: 'Silent task notification',
        );

        $reminder = $this->db->reminders()->createReminder(
            task: $task,
            remindAt: new DateTimeImmutable('-10 minutes'),
        );

        $dispatched = $this->reminders->processDueReminders($fakeBot);
        $this->assertSame(0, $dispatched);
        $fakeBot->assertNothingSent();

        $refreshed = $this->db->reminders()->find($reminder->id);
        $this->assertTrue($refreshed->isSent);
    }

    public function testSnoozeReminder(): void
    {
        $task = $this->db->tasks()->createTask(
            chatId: $this->testChatId,
            title: 'Snoozable meeting',
        );

        $reminder = $this->db->reminders()->createReminder(
            task: $task,
            remindAt: new DateTimeImmutable('-1 minute'),
        );
        $reminder->markSent();
        $this->db->reminders()->save($reminder);

        $snoozed = $this->reminders->snoozeReminder($reminder->id, 30);
        $this->assertNotNull($snoozed);
        $this->assertFalse($snoozed->isSent);
        $this->assertSame(1, $snoozed->snoozeCount);
        $this->assertNotNull($snoozed->snoozedUntil);
    }

    public function testViewDetailsFromNotificationDeletesOldScreenAndReminderAndSendsNewFlow(): void
    {
        $fakeBot = Telegram::fake();
        $manager = new \Tueen\Telegram\Flow\FlowManager();
        $fakeBot->setRootFlow(\Tueen\Telegram\Example\ToDoList\Flow\MainMenuFlow::class);

        $task = $this->db->tasks()->createTask(
            chatId: $this->testChatId,
            title: 'Urgent Notification Task',
        );

        // State has old flow screen at message 200
        $state = new \Tueen\Telegram\Flow\FlowState(
            flowClass: \Tueen\Telegram\Example\ToDoList\Flow\MainMenuFlow::class,
            currentStep: 'start',
            data: [],
            messageId: 200
        );

        // User clicks View Details on reminder notification at message 500
        $update = new \Tueen\Telegram\Types\Update([
            'update_id' => 9500,
            'callback_query' => [
                'id' => 'cb_notif_view',
                'from' => ['id' => $this->testChatId, 'is_bot' => false, 'first_name' => 'Tester'],
                'message' => [
                    'message_id' => 500,
                    'date' => time(),
                    'chat' => ['id' => $this->testChatId, 'type' => 'private'],
                    'text' => '⏰ Task Reminder',
                ],
                'data' => "task:view_{$task->id}",
            ]
        ]);

        $flow = new \Tueen\Telegram\Example\ToDoList\Flow\MainMenuFlow();
        $flow->init($fakeBot, $update, $this->testChatId, $this->testChatId, $state, $manager);

        $handled = $flow->handleUpdate($update);
        $this->assertTrue($handled);

        // Old screen (200) was deleted
        $fakeBot->assertSent('deleteMessage', function (array $params) {
            return (int)$params['chat_id'] === $this->testChatId
                && (int)$params['message_id'] === 200;
        });

        // Reminder notification (500) was kept intact (not deleted)
        $fakeBot->assertNotSent('deleteMessage', function (array $params) {
            return (int)$params['chat_id'] === $this->testChatId
                && (int)$params['message_id'] === 500;
        });

        // Fresh TaskDetailFlow screen was sent via sendMessage at the bottom
        $fakeBot->assertSent('sendMessage', function (array $params) {
            return (int)$params['chat_id'] === $this->testChatId
                && str_contains($params['text'], 'Urgent Notification Task');
        });
    }
}
