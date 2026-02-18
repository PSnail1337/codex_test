<?php

require_once __DIR__ . '/Project.php';
require_once __DIR__ . '/TimeEntry.php';
require_once __DIR__ . '/MoodEntry.php';

class DataStore
{
    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function load(): array
    {
        if (!file_exists($this->path)) {
            return $this->seed();
        }

        $raw = file_get_contents($this->path);
        if ($raw === false || trim($raw) === '') {
            return $this->seed();
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $this->seed();
        }

        return $decoded;
    }

    public function save(array $payload): void
    {
        $directory = dirname($this->path);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create data directory: {$directory}");
        }

        $encoded = json_encode($payload, JSON_PRETTY_PRINT);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode data payload as JSON.');
        }

        $result = file_put_contents($this->path, $encoded);
        if ($result === false) {
            throw new RuntimeException("Unable to write data file: {$this->path}");
        }
    }

    private function seed(): array
    {
        $projects = [
            (new Project('song-a', 'Song A', 'Composing + polishing arrangement ideas.', 'high'))->toArray(),
            (new Project('workshop-b', 'Workshop B', 'Build slides and hands-on exercises.', 'medium'))->toArray(),
            (new Project('daily-tasks', 'Daily Tasks', 'Admin tasks and quick maintenance.', 'low'))->toArray(),
        ];

        $moods = [
            (new MoodEntry(date('Y-m-d'), '🔥', 'Feeling focused and ready to ship.'))->toArray(),
        ];

        $entries = [
            (new TimeEntry('song-a', date('Y-m-d'), '09:00', '11:30', 'Drafted chorus and bass line.'))->toArray(),
            (new TimeEntry('workshop-b', date('Y-m-d'), '13:00', '15:00', 'Updated demo snippets.'))->toArray(),
        ];

        $seed = [
            'projects' => $projects,
            'moods' => $moods,
            'entries' => $entries,
        ];

        $this->save($seed);

        return $seed;
    }
}
