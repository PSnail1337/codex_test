<?php

class TimeEntry
{
    public string $projectId;
    public string $date;
    public string $startTime;
    public string $endTime;
    public string $note;

    public function __construct(string $projectId, string $date, string $startTime, string $endTime, string $note = '')
    {
        $this->projectId = $projectId;
        $this->date = $date;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->note = $note;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['projectId'] ?? ''),
            (string) ($data['date'] ?? date('Y-m-d')),
            (string) ($data['startTime'] ?? '09:00'),
            (string) ($data['endTime'] ?? '10:00'),
            (string) ($data['note'] ?? '')
        );
    }

    public function getHours(): float
    {
        $start = strtotime($this->date . ' ' . $this->startTime);
        $end = strtotime($this->date . ' ' . $this->endTime);

        if (!$start || !$end || $end <= $start) {
            return 0.0;
        }

        return round(($end - $start) / 3600, 2);
    }

    public function toArray(): array
    {
        return [
            'projectId' => $this->projectId,
            'date' => $this->date,
            'startTime' => $this->startTime,
            'endTime' => $this->endTime,
            'note' => $this->note,
        ];
    }
}
