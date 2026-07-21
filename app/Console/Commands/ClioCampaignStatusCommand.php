<?php

namespace App\Console\Commands;

use App\Models\Clio\ClioCampaign;
use App\Services\Clio\Parse\CampaignParseService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('clio:campaign-status {uuid : UUID da campanha} {--parse : Executar interpretação pendente antes do relatório} {--reparse : Reinterpretar todos os CSV} {--json}')]
#[Description('Clio — cobertura da campanha (tríade, status de interpretação, data de referência).')]
final class ClioCampaignStatusCommand extends Command
{
    public function handle(CampaignParseService $parser): int
    {
        $uuid = (string) $this->argument('uuid');
        $campaign = ClioCampaign::query()->where('uuid', $uuid)->first();
        if ($campaign === null) {
            $this->error(__('Campanha Clio não encontrada: :uuid', ['uuid' => $uuid]));

            return self::FAILURE;
        }

        if ($this->option('parse') || $this->option('reparse')) {
            $stats = $parser->parseCampaign($campaign, reparse: (bool) $this->option('reparse'));
            $this->info(__('Interpretação: :p · ok=:ok aviso=:w falha=:f', [
                'p' => $stats['parsed'],
                'ok' => $stats['ok'],
                'w' => $stats['warning'],
                'f' => $stats['failed'],
            ]));
            $campaign->refresh();
        }

        $coverage = $parser->coverage($campaign);

        if ($this->option('json')) {
            $this->line(json_encode($coverage, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info(__(':mun · :year · :status', [
            'mun' => $campaign->municipality_name,
            'year' => (string) $campaign->year,
            'status' => $coverage['status_label'],
        ]));
        $this->line(__('UUID: :u', ['u' => $campaign->uuid]));
        $this->line(__('Data ref.: :d', ['d' => $coverage['reference_date'] ?? '—']));
        $this->line(__('Acomp municipal: :a', ['a' => $coverage['has_acomp'] ? __('sim') : __('não')]));
        $this->line(__('Escolas: :t · tríade completa: :c (:p%)', [
            't' => $coverage['schools_total'],
            'c' => $coverage['schools_triade_complete'],
            'p' => $coverage['triade_coverage_pct'],
        ]));

        $this->newLine();
        $this->table(
            [__('Tipo'), __('Qtd')],
            collect($coverage['artifacts_by_kind'] ?? [])->map(fn ($n, $k) => [$k, $n])->values()->all()
        );

        $this->newLine();
        $this->table(
            [__('Interpretação'), __('Qtd')],
            collect($coverage['parse_stats'] ?? [])->map(fn ($n, $k) => [$k, $n])->values()->all()
        );

        if (($coverage['schools'] ?? []) !== []) {
            $this->newLine();
            $this->table(
                [__('INEP'), __('Nome'), __('Aluno'), __('Turma'), __('Prof'), __('Tríade')],
                collect($coverage['schools'])->map(fn (array $s) => [
                    $s['inep'],
                    \Illuminate\Support\Str::limit($s['name'], 40),
                    $s['aluno'] ? '✓' : '·',
                    $s['turma'] ? '✓' : '·',
                    $s['profissional'] ? '✓' : '·',
                    $s['triade'] ? '✓' : '·',
                ])->all()
            );
        }

        return self::SUCCESS;
    }
}
