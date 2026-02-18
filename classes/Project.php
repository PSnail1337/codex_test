<?php

declare(strict_types=1);

class Project
{
    public function __construct(
        public int $id,
        public string $name,
        public string $description,
        public string $priority
    ) {
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

    public static function fromArray(array $data): self
    {
        return new self(
            (int) $data['id'],
            (string) $data['name'],
            (string) $data['description'],
            (string) $data['priority']
        );
    }
}
