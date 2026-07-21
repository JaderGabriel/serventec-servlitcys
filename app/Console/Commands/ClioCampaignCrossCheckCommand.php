<?php

namespace App\Console\Commands;

use App\Models\Clio\ClioCampaign;
use App\Services\Clio\CrossCheck\IeducarGapAnalyzer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('clio:campaign-cross-check {uuid : UUID da campanha}')]
#[Description('Clio — INF-GAP: cruza escolas da campanha com i-Educar (somente leitura).')]
final class ClioCampaignCrossCheckCommand extends Command
{
    public function handle(IeducarGapAnalyzer $analyzer): int
    {
        $uuid = (string) $this->argument('uuid');
        $campaign = ClioCampaign::query()->where('uuid', $uuid)->first();
        if ($campaign === null) {
            $this->error(__('Campanha Clio não encontrada.'));

            return self::FAILURE;
        }

        $result = $analyzer->analyze($campaign);
        if (! $result['ok']) {
            $this->error($result['message'] ?? __('Falha no cruzamento.'));

            return self::FAILURE;
        }

        $this->info(__('INF-GAP · matched=:m · só Clio=:c · só i-Educar=:i · matrículas i-Educar=:mat', [
            'm' => $result['matched'],
            'c' => $result['only_in_clio'],
            'i' => $result['only_in_ieducar'],
            'mat' => $result['ieducar_matriculas'],
        ]));

        return self::SUCCESS;
    }
}
