<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Agents;

/**
 * Agent Schedule Configuration
 *
 * Configures recurring tasks and scheduling for agents.
 */
class AgentScheduleConfig
{
    /**
     * Create a new schedule configuration.
     *
     * @param bool $enabled Whether scheduling is enabled
     * @param string $timezone Timezone for scheduled tasks
     * @param array $recurringTasks Array of recurring task configurations
     */
    public function __construct(
        public bool $enabled = false,
        public string $timezone = 'UTC',
        public array $recurringTasks = [],
    ) {}

    /**
     * Create from existing schedule array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            enabled: $data['enabled'] ?? false,
            timezone: $data['timezone'] ?? 'UTC',
            recurringTasks: $data['recurring_tasks'] ?? $data['recurringTasks'] ?? [],
        );
    }

    /**
     * Convert to array for API submission.
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'timezone' => $this->timezone,
            'recurring_tasks' => $this->recurringTasks,
        ];
    }

    /**
     * Enable scheduling.
     */
    public function enable(): self
    {
        $this->enabled = true;
        return $this;
    }

    /**
     * Disable scheduling.
     */
    public function disable(): self
    {
        $this->enabled = false;
        return $this;
    }

    /**
     * Set timezone.
     */
    public function withTimezone(string $timezone): self
    {
        $this->timezone = $timezone;
        return $this;
    }

    /**
     * Add a recurring task.
     *
     * @param array{
     *     name: string,
     *     time: string,
     *     message: string,
     *     channels?: array,
     *     frequency?: string
     * } $task Task configuration
     */
    public function addTask(array $task): self
    {
        // Validate required fields
        if (!isset($task['name']) || !isset($task['time']) || !isset($task['message'])) {
            throw new \InvalidArgumentException('Task must have name, time, and message');
        }

        // Validate time format (HH:MM)
        if (!preg_match('/^\d{2}:\d{2}$/', $task['time'])) {
            throw new \InvalidArgumentException('Time must be in HH:MM format');
        }

        $this->recurringTasks[] = array_merge([
            'channels' => ['sms', 'email'],
            'frequency' => 'daily',
        ], $task);

        return $this;
    }

    /**
     * Add multiple recurring tasks.
     */
    public function addTasks(array $tasks): self
    {
        foreach ($tasks as $task) {
            $this->addTask($task);
        }
        return $this;
    }

    /**
     * Clear all recurring tasks.
     */
    public function clearTasks(): self
    {
        $this->recurringTasks = [];
        return $this;
    }

    /**
     * Create a medication reminder schedule.
     *
     * @param array $times Array of times in HH:MM format
     * @param string $message Message to send
     * @param array $channels Channels to send on
     */
    public static function medicationReminders(
        array $times,
        string $message = 'Time to take your medication',
        array $channels = ['voice', 'sms']
    ): self {
        $schedule = new self();
        $schedule->enable();

        foreach ($times as $time) {
            $schedule->addTask([
                'name' => "Medication Reminder - {$time}",
                'time' => $time,
                'message' => $message,
                'channels' => $channels,
                'frequency' => 'daily',
            ]);
        }

        return $schedule;
    }

    /**
     * Create a daily check-in schedule.
     */
    public static function dailyCheckIn(
        string $time = '20:00',
        string $message = 'How was your day? Need anything?',
        array $channels = ['voice', 'sms']
    ): self {
        $schedule = new self();
        $schedule->enable();
        $schedule->addTask([
            'name' => 'Daily Check-in',
            'time' => $time,
            'message' => $message,
            'channels' => $channels,
            'frequency' => 'daily',
        ]);

        return $schedule;
    }
}
