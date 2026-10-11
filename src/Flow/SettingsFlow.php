<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Flow;

use DateTimeImmutable;
use DateTimeZone;
use Tueen\Telegram\Flow\Attributes\Action;
use Tueen\Telegram\Flow\Attributes\Breadcrumb;
use Tueen\Telegram\Flow\Attributes\SingleScreen;
use Tueen\Telegram\Flow\InteractiveFlow;
use Tueen\Telegram\Flow\Screen;
use Tueen\Telegram\Keyboards\InlineKeyboard;
use Tueen\Telegram\Example\ToDoList\Service\DatabaseService;
use Tueen\Telegram\Example\ToDoList\Service\UserSettingsService;
use Tueen\Telegram\Example\ToDoList\Support\DateTimeHelper;

#[Breadcrumb('⚙️ Settings')]
#[SingleScreen]
class SettingsFlow extends InteractiveFlow
{
    private DatabaseService $db;
    private UserSettingsService $settings;

    public function __construct(
        ?DatabaseService $db = null,
        ?UserSettingsService $settings = null,
    ) {
        $this->db = $db ?? DatabaseService::getInstance();
        $this->settings = $settings ?? new UserSettingsService($this->db);
    }

    #[\Override]
    public function render(): Screen
    {
        $currentTz = $this->settings->getTimezone($this->chatId);
        $notificationsEnabled = $this->settings->areNotificationsEnabled($this->chatId);

        $now = new DateTimeImmutable('now', new DateTimeZone($currentTz));
        $nowStr = $now->format('Y-m-d H:i:s');

        $tzTitle = DateTimeHelper::SUPPORTED_TIMEZONES[$currentTz] ?? $currentTz;

        $text = "⚙️ <b>User Settings & Timezone</b>\n\n"
            . "🌍 <b>Current Timezone:</b> {$tzTitle}\n"
            . "🕒 <b>Your Current Time:</b> <code>{$nowStr}</code>\n"
            . "🔔 <b>Notifications:</b> " . ($notificationsEnabled ? '✅ Enabled' : '☑️ Disabled') . "\n\n"
            . "Tap an option below to update your timezone or toggle reminders:";

        return Screen::make($text)
            ->parseMode('HTML')
            ->inline(function (InlineKeyboard $k) use ($currentTz, $notificationsEnabled) {
                // Timezones with checkbox representation
                foreach (DateTimeHelper::SUPPORTED_TIMEZONES as $tzKey => $tzLabel) {
                    $isActive = ($currentTz === $tzKey);
                    $icon = $isActive ? '✅ ' : '☑️ ';
                    $k->row()->action($icon . $tzLabel, "tz:set_{$tzKey}");
                }

                // Notification toggle switch
                $notifLabel = $notificationsEnabled
                    ? '✅ Notifications & Reminders Enabled'
                    : '☑️ Notifications Disabled (Muted)';
                $k->row()->action($notifLabel, 'settings:toggle_notif');

                $k->row()
                    ->action('🔙 Back', 'flow:back')
                    ->action('🏠 Home', 'flow:home');
            });
    }

    #[Action('tz:set_{tz}')]
    public function onSetTimezone(string $tz): void
    {
        if (array_key_exists($tz, DateTimeHelper::SUPPORTED_TIMEZONES)) {
            $this->settings->setTimezone($this->chatId, $tz);
            $this->toast("Timezone updated successfully! 🌍");
        }
        $this->refresh();
    }

    #[Action('settings:toggle_notif')]
    public function onToggleNotifications(): void
    {
        $current = $this->settings->areNotificationsEnabled($this->chatId);
        $new = !$current;
        $this->settings->setNotificationsEnabled($this->chatId, $new);

        $msg = $new ? "Notifications enabled ✅" : "Notifications disabled ☑️";
        $this->toast($msg);
        $this->refresh();
    }
}
