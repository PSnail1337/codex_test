<?php

class Project
{
    public string $id;
    public string $name;
    public string $description;
    public string $priority;

    public function __construct(string $id, string $name, string $description, string $priority = 'medium')
    {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->priority = $priority;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['id'] ?? uniqid('project_', true)),
            (string) ($data['name'] ?? 'Untitled Project'),
            (string) ($data['description'] ?? ''),
            (string) ($data['priority'] ?? 'medium')
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'priority' => $this->priority,
        ];
    }
}
