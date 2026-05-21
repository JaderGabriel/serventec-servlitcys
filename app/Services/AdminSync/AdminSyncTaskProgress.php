<?php

namespace App\Services\AdminSync;

use App\Models\AdminSyncTask;

/**
 * Registo de andamento persistido em admin_sync_tasks.output_log (fila admin-sync).
 */
class AdminSyncTaskProgress
{
    /** @var list<array{level: string, message: string, at: string}> */
    private array $entries = [];

    public function __construct(
        private readonly ?\Closure $listener = null,
    ) {}

    public static function forTask(AdminSyncTask $task): self
    {
        return new self(function (string $level, string $message) use ($task): void {
            $line = '['.now()->format('H:i:s').'] ['.$level.'] '.$message;
            $existing = (string) ($task->output_log ?? '');
            $task->output_log = $existing === '' ? $line : $existing."\n".$line;
            $task->saveQuietly();
        });
    }

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

    /** Explicação do passo (o que está a acontecer e porquê). */
    public function explain(string $message): void
    {
        $this->line('nota', '→ '.$message);
    }

    /** Marco numerado (ex.: passo 2 de 5). */
    public function step(int $current, int $total, string $message): void
    {
        $total = max(1, $total);
        $current = max(1, min($current, $total));
        $this->line('passo', __('Passo :c/:t — :msg', [
            'c' => (string) $current,
            't' => (string) $total,
            'msg' => $message,
        ]));
    }

    /** Saída técnica (linha de comando, HTTP, etc.). */
    public function detail(string $message): void
    {
        $trimmed = trim($message);
        if ($trimmed === '') {
            return;
        }
        $this->line('detalhe', $trimmed);
    }

    /**
     * Anexa várias linhas de uma só vez (ex.: saída Artisan).
     */
    public function appendBlock(string $block, string $level = 'detalhe'): void
    {
        foreach (preg_split('/\r\n|\r|\n/', $block) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $this->line($level, $line);
            }
        }
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

    public function formatForDisplay(): string
    {
        $lines = [];
        foreach ($this->entries as $entry) {
            $lines[] = '['.($entry['at'] ?? '').'] ['.($entry['level'] ?? 'info').'] '.($entry['message'] ?? '');
        }

        return implode("\n", $lines);
    }
}
