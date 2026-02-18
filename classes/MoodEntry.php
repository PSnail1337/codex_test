<?php

declare(strict_types=1);

class MoodEntry
{
    public function __construct(
        public string $date,
        public string $mood,
        public string $details
    ) {
    }

    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'mood' => $this->mood,
            'details' => $this->details,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['date'],
            (string) $data['mood'],
            (string) $data['details']
        );
    }
}
