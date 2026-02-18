<?php

declare(strict_types=1);

class TimeEntry
{
    public function __construct(
        public int $projectId,
        public string $date,
        public string $startTime,
        public string $endTime,
        public string $notes
    ) {
    }

    public function durationMinutes(): int
    {
        $start = strtotime($this->date . ' ' . $this->startTime);
        $end = strtotime($this->date . ' ' . $this->endTime);

        if ($start === false || $end === false || $end <= $start) {
            return 0;
        }

        return (int) round(($end - $start) / 60);
    }

    public function toArray(): array
    {
        return [
            'projectId' => $this->projectId,
            'date' => $this->date,
            'startTime' => $this->startTime,
            'endTime' => $this->endTime,
            'notes' => $this->notes,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (int) $data['projectId'],
            (string) $data['date'],
            (string) $data['startTime'],
            (string) $data['endTime'],
            (string) ($data['notes'] ?? '')
        );
    }
}
