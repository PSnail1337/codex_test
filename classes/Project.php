<?php

class Project
{
    public string $id;
    public string $name;
    public string $description;
    public string $priority;
    /** @var array<int, array{name:string,path:string,uploadedAt:string}> */
    public array $attachments;

    public function __construct(string $id, string $name, string $description, string $priority = 'medium', array $attachments = [])
    {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->priority = $priority;
        $this->attachments = $attachments;
    }

    public static function fromArray(array $data): self
    {
        $attachments = $data['attachments'] ?? [];
        if (!is_array($attachments)) {
            $attachments = [];
        }

        return new self(
            (string) ($data['id'] ?? uniqid('project_', true)),
            (string) ($data['name'] ?? 'Untitled Project'),
            (string) ($data['description'] ?? ''),
            (string) ($data['priority'] ?? 'medium'),
            $attachments
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'priority' => $this->priority,
            'attachments' => $this->attachments,
        ];
    }
}
