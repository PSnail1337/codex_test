<?php

declare(strict_types=1);

require_once __DIR__ . '/Project.php';
require_once __DIR__ . '/TimeEntry.php';
require_once __DIR__ . '/MoodEntry.php';

class DataStore
{
    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;

        if (!file_exists($path)) {
            $this->seedDefaults();
        }
    }

    public function all(): array
    {
        $raw = json_decode((string) file_get_contents($this->path), true);

        if (!is_array($raw)) {
            $this->seedDefaults();
            $raw = json_decode((string) file_get_contents($this->path), true) ?? [];
        }

        return [
            'projects' => array_map(static fn(array $item) => Project::fromArray($item), $raw['projects'] ?? []),
            'timeEntries' => array_map(static fn(array $item) => TimeEntry::fromArray($item), $raw['timeEntries'] ?? []),
            'moodEntries' => array_map(static fn(array $item) => MoodEntry::fromArray($item), $raw['moodEntries'] ?? []),
        ];
    }

    public function save(array $projects, array $timeEntries, array $moodEntries): void
    {
        $payload = [
            'projects' => array_map(static fn(Project $project) => $project->toArray(), $projects),
            'timeEntries' => array_map(static fn(TimeEntry $entry) => $entry->toArray(), $timeEntries),
            'moodEntries' => array_map(static fn(MoodEntry $entry) => $entry->toArray(), $moodEntries),
        ];

        file_put_contents($this->path, json_encode($payload, JSON_PRETTY_PRINT));
    }

    private function seedDefaults(): void
    {
        $today = date('Y-m-d');
        $defaults = [
            'projects' => [
                ['id' => 1, 'name' => 'Song A', 'description' => 'Compose and refine the hook + verses.', 'priority' => 'high'],
                ['id' => 2, 'name' => 'Workshop B', 'description' => 'Prepare slides and hands-on exercises.', 'priority' => 'medium'],
                ['id' => 3, 'name' => 'Daily Tasks', 'description' => 'Email triage, planning, and follow-ups.', 'priority' => 'low'],
            ],
            'timeEntries' => [
                ['projectId' => 1, 'date' => $today, 'startTime' => '09:00', 'endTime' => '10:30', 'notes' => 'Melody sketching'],
                ['projectId' => 2, 'date' => $today, 'startTime' => '11:00', 'endTime' => '12:15', 'notes' => 'Workshop outline'],
            ],
            'moodEntries' => [
                ['date' => $today, 'mood' => 'Focused', 'details' => 'Strong progress after a quick walk and coffee.'],
            ],
        ];

        file_put_contents($this->path, json_encode($defaults, JSON_PRETTY_PRINT));
    }
}
