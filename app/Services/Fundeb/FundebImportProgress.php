<?php

namespace App\Services\Fundeb;

/**
 * Registo de andamento da importação FUNDEB (CLI, admin, observer).
 */
final class FundebImportProgress
{
    /** @var list<array{level: string, message: string, at: string}> */
    private array $entries = [];

    public function __construct(
        private readonly ?\Closure $listener = null,
    ) {}

    public function info(string $message): void
    {
        $this->line('info', $message);
    }

    public function success(string $message): void
    {
        $this->line('success', $message);
    }

    public function warn(string $message): void
    {
        $this->line('warn', $message);
    }

    public function error(string $message): void
    {
        $this->line('error', $message);
    }

    public function line(string $level, string $message): void
    {
        $entry = [
            'level' => $level,
            'message' => $message,
            'at' => now()->format('H:i:s'),
        ];
        $this->entries[] = $entry;

        if ($this->listener !== null) {
            ($this->listener)($level, $message, $entry);
        }
    }

    /**
     * @return list<array{level: string, message: string, at: string}>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }
}
