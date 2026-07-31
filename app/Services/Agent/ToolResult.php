<?php

namespace App\Services\Agent;

class ToolResult
{
    public function __construct(
        public readonly bool $success,
        public readonly array $data = [],
        public readonly string $message = '',
    ) {}

    public static function ok(string $message, array $data = []): self
    {
        return new self(true, $data, $message);
    }

    public static function fail(string $message, array $data = []): self
    {
        return new self(false, $data, $message);
    }

    public function toArray(): array
    {
        return ['success' => $this->success, 'data' => $this->data, 'message' => $this->message];
    }
}
