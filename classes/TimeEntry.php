<?php

class TimeEntry
{
    public string $id;
    public string $projectId;
    public string $date;
    public string $startTime;
    public string $endTime;
    public string $note;
    public string $calendarFile;

    public function __construct(
        string $id,
        string $projectId,
        string $date,
        string $startTime,
        string $endTime,
        string $note = '',
        string $calendarFile = ''
    ) {
        $this->id = $id;
        $this->projectId = $projectId;
        $this->date = $date;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->note = $note;
        $this->calendarFile = $calendarFile;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['id'] ?? uniqid('entry_', true)),
            (string) ($data['projectId'] ?? ''),
            (string) ($data['date'] ?? date('Y-m-d')),
            (string) ($data['startTime'] ?? '09:00'),
            (string) ($data['endTime'] ?? '10:00'),
            (string) ($data['note'] ?? ''),
            (string) ($data['calendarFile'] ?? '')
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
            'id' => $this->id,
            'projectId' => $this->projectId,
            'date' => $this->date,
            'startTime' => $this->startTime,
            'endTime' => $this->endTime,
            'note' => $this->note,
            'calendarFile' => $this->calendarFile,
        ];
    }
}
