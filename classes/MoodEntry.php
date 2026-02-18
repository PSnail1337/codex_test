<?php

class MoodEntry
{
    public string $date;
    public string $time;
    public string $mood;
    public string $reflection;

    public function __construct(string $date, string $time, string $mood, string $reflection)
    {
        $this->date = $date;
        $this->time = $time;
        $this->mood = $mood;
        $this->reflection = $reflection;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['date'] ?? date('Y-m-d')),
            (string) ($data['time'] ?? date('H:i')),
            (string) ($data['mood'] ?? '🙂'),
            (string) ($data['reflection'] ?? '')
        );
    }

    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'time' => $this->time,
            'mood' => $this->mood,
            'reflection' => $this->reflection,
        ];
    }
}
