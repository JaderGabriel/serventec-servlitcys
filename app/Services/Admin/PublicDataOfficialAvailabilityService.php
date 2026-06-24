<?php

namespace App\Services\Admin;

use App\Models\CadunicoMunicipioSnapshot;
use App\Models\FundebMunicipioReference;
use App\Models\InepCensoMunicipioMatricula;
use App\Models\MunicipalTransferSnapshot;
use App\Models\SaebIndicatorPoint;
use App\Services\Cadunico\CadunicoOpenDataImportService;
use App\Services\Cadunico\CadunicoRemoteCsvFetcher;
use App\Services\Cadunico\CadunicoSagiMisocialClient;
use App\Services\Fundeb\FundebOfficialSourcesService;
use App\Services\Fundeb\FundebOpenDataImportService;
use App\Services\Inep\SaebHistoricoDatabase;
use App\Support\Admin\PublicDataImportCatalog;
use App\Support\Fundeb\FundebFndePortariaCatalog;
use App\Support\Http\SafeOutboundUrl;
use App\Support\InepMicrodadosCadastroEscolasPath;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Verifica (read-only) se fontes oficiais publicaram dados ainda não importados localmente.
 */
final class PublicDataOfficialAvailabilityService
{
    public function __construct(
        private FundebOfficialSourcesService $fundebOfficial,
        private CadunicoSagiMisocialClient $misocial,
        private CadunicoRemoteCsvFetcher $cadunicoCsv,
    ) {}

    /**
     * @return array{
     *     checked_at: string,
     *     has_news: bool,
     *     news_count: int,
     *     attention_count: int,
     *     aligned_count: int,
     *     action_count: int,
     *     findings: list<array<string, mixed>>
     * }
     */
    public function scan(): array
    {
        $findings = [
            $this->probeFundeb(),
            $this->probeCadunico(),
            $this->probeCensoInep(),
            $this->probeRepasses(),
            $this->probeSaeb(),
        ];

        $news = array_values(array_filter(
            $findings,
            static fn (array $f): bool => ($f['status'] ?? '') === 'new_available',
        ));

        $attention = array_values(array_filter(
            $findings,
            static fn (array $f): bool => in_array($f['status'] ?? '', ['attention', 'unreachable', 'not_configured'], true),
        ));

        $aligned = array_values(array_filter(
            $findings,
            static fn (array $f): bool => ($f['status'] ?? '') === 'unchanged',
        ));

        $actionCount = count($news) + count($attention);

        return [
            'checked_at' => now()->toIso8601String(),
            'has_news' => $actionCount > 0,
            'news_count' => count($news),
            'attention_count' => count($attention),
            'aligned_count' => count($aligned),
            'action_count' => $actionCount,
            'findings' => $findings,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function probeFundeb(): array
    {
        $sourceId = 'fundeb_fnde';
        $routine = PublicDataImportCatalog::routineHint($sourceId);
        $localMaxAno = (int) (FundebMunicipioReference::query()->max('ano') ?? 0);
        $updates = $this->fundebOfficial->probeUpdates();

        $latestPortariaAno = 0;
        $portariaOk = false;
        $portariaMessage = '';

        foreach ($updates as $row) {
            $source = (string) ($row['source'] ?? '');
            if (! str_contains(mb_strtolower($source), 'portaria') && ! str_contains(mb_strtolower($source), 'receita')) {
                continue;
            }
            if (($row['status'] ?? '') === 'ok') {
                if (preg_match('/\b(20\d{2})\b/', $source, $m)) {
                    $year = (int) $m[1];
                    if ($year >= $latestPortariaAno) {
                        $latestPortariaAno = $year;
                        $portariaOk = true;
                        $portariaMessage = (string) ($row['message'] ?? '');
                    }
                }
            }
        }

        if ($latestPortariaAno === 0) {
            foreach (array_keys(config('ieducar.fundeb.open_data.portarias', [])) as $exercicio) {
                $year = (int) $exercicio;
                $pub = FundebFndePortariaCatalog::activePublication($year);
                if ($pub !== null) {
                    $latestPortariaAno = max($latestPortariaAno, $year);
                }
            }
        }

        $ckanRow = collect($updates)->first(static fn (array $r): bool => str_contains((string) ($r['source'] ?? ''), 'CKAN'));
        $ckanOk = ($ckanRow['status'] ?? '') === 'ok';

        if ($portariaOk && $latestPortariaAno > 0 && ($localMaxAno === 0 || $latestPortariaAno > $localMaxAno)) {
            return $this->finding(
                $sourceId,
                'new_available',
                __('Portaria FUNDEB :ano acessível — ainda não importada localmente (último ano local: :local).', [
                    'ano' => (string) $latestPortariaAno,
                    'local' => $localMaxAno > 0 ? (string) $localMaxAno : __('nenhum'),
                ]),
                $portariaMessage !== '' ? $portariaMessage : __('CSV de receita total por ente disponível na fonte oficial.'),
                $routine,
                __('php artisan fundeb:import-api --ano=:ano (ou hub Dados públicos → FUNDEB)', ['ano' => (string) $latestPortariaAno]),
            );
        }

        if (! $ckanOk && $localMaxAno === 0) {
            return $this->finding(
                $sourceId,
                'unreachable',
                __('CKAN FNDE inacessível e sem referências locais.'),
                (string) ($ckanRow['message'] ?? __('Verifique rede ou IEDUCAR_FUNDEB_CKAN_RESOURCE_ID.')),
                $routine,
                $routine['cli'] ?? 'fundeb:import-api',
            );
        }

        if ($localMaxAno > 0) {
            return $this->finding(
                $sourceId,
                'unchanged',
                __('FUNDEB — sem exercício novo detectado (local até :ano).', ['ano' => (string) $localMaxAno]),
                $ckanOk
                    ? __('CKAN FNDE acessível; portarias configuradas verificadas.')
                    : __('Base local presente; CKAN pode estar indisponível no momento.'),
                $routine,
                null,
            );
        }

        return $this->finding(
            $sourceId,
            'attention',
            __('FUNDEB — fonte acessível mas nenhuma referência importada.'),
            __('Execute importação CKAN ou CSV FNDE.'),
            $routine,
            $routine['cli'] ?? 'fundeb:import-api',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function probeCadunico(): array
    {
        $sourceId = 'cadunico_cecad';
        $routine = PublicDataImportCatalog::routineHint($sourceId);
        $suggested = CadunicoOpenDataImportService::suggestedImportYear();
        $localMax = (int) (CadunicoMunicipioSnapshot::query()->max('ano_referencia') ?? 0);

        $misocial = $this->misocial->probe();
        $csvCheck = $this->cadunicoCsv->ensureNationalCsv($suggested);
        $remoteOk = ($misocial['reachable'] ?? false) && ($misocial['ok'] ?? false);

        if ($remoteOk && ($localMax === 0 || $localMax < $suggested)) {
            return $this->finding(
                $sourceId,
                'new_available',
                __('CadÚnico/Cecad — dados oficiais disponíveis para :ano (local: :local).', [
                    'ano' => (string) $suggested,
                    'local' => $localMax > 0 ? (string) $localMax : __('nenhum'),
                ]),
                (string) ($misocial['message'] ?? ''),
                $routine,
                'php artisan cadunico:auto-sync --ano='.$suggested.' --queue',
            );
        }

        if (($csvCheck['ok'] ?? false) && ($localMax === 0 || $localMax < $suggested)) {
            return $this->finding(
                $sourceId,
                'new_available',
                __('CSV Cecad nacional disponível para :ano.', ['ano' => (string) $suggested]),
                (string) ($csvCheck['message'] ?? ''),
                $routine,
                'php artisan cadunico:auto-sync --ano='.$suggested.' --queue',
            );
        }

        if ($localMax >= $suggested && $localMax > 0) {
            return $this->finding(
                $sourceId,
                'unchanged',
                __('CadÚnico — cobertura local até :ano.', ['ano' => (string) $localMax]),
                (string) ($misocial['message'] ?? __('Sem novidade detectada na API Misocial.')),
                $routine,
                null,
            );
        }

        if (! ($misocial['reachable'] ?? false)) {
            return $this->finding(
                $sourceId,
                'unreachable',
                __('API Misocial/SAGI indisponível.'),
                (string) ($misocial['message'] ?? ''),
                $routine,
                $routine['cli'] ?? 'cadunico:import-misocial',
            );
        }

        return $this->finding(
            $sourceId,
            'attention',
            __('CadÚnico — sem snapshots locais para o ano sugerido (:ano).', ['ano' => (string) $suggested]),
            __('Confirme IEDUCAR_CADUNICO_NACIONAL_CSV_URL ou Misocial e execute sincronização.'),
            $routine,
            'php artisan cadunico:auto-sync --queue',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function probeCensoInep(): array
    {
        $sourceId = 'censo_inep_matriculas';
        $routine = PublicDataImportCatalog::routineHint($sourceId);
        $rel = (string) config('ieducar.inep_geocoding.microdados_cadastro_escolas_path', 'inep/microdados_ed_basica_*.csv');
        $mdPath = InepMicrodadosCadastroEscolasPath::resolve($rel);
        $readable = $mdPath !== null && is_readable($mdPath);

        $localMax = (int) (InepCensoMunicipioMatricula::query()->max('ano') ?? 0);
        $lastImport = InepCensoMunicipioMatricula::query()->max('imported_at');
        $suggested = max(2000, (int) date('Y') - 1);

        $fileNewer = false;
        if ($readable && $lastImport !== null) {
            $mtime = filemtime($mdPath);
            $fileNewer = $mtime !== false && $mtime > strtotime((string) $lastImport);
        }

        if ($readable && ($localMax === 0 || $fileNewer)) {
            return $this->finding(
                $sourceId,
                $localMax === 0 ? 'attention' : 'new_available',
                $localMax === 0
                    ? __('Microdados INEP presentes — indexação Censo ainda não executada.')
                    : __('Microdados INEP actualizados após a última indexação local.'),
                __('Ficheiro: :path', ['path' => basename((string) $mdPath)]),
                $routine,
                'php artisan inep:index-censo-geo-agg (ou pipeline geo passo 3)',
            );
        }

        if ($readable && $localMax > 0 && $localMax < $suggested) {
            return $this->finding(
                $sourceId,
                'attention',
                __('Censo indexado até :ano — verifique microdados mais recentes no INEP.', [
                    'ano' => (string) $localMax,
                ]),
                __('Descarregue novo Censo Escolar em storage/app/inep/ se disponível.'),
                $routine,
                'php artisan app:import-inep-microdados-cadastro-escolas-geo --fetch=1',
            );
        }

        if (! $readable) {
            return $this->finding(
                $sourceId,
                'attention',
                __('Microdados Educacenso não encontrados em storage.'),
                __('Coloque o CSV INEP ou execute download via pipeline geo.'),
                $routine,
                'php artisan app:import-inep-microdados-cadastro-escolas-geo --fetch=1',
            );
        }

        return $this->finding(
            $sourceId,
            'unchanged',
            __('Censo INEP — indexação local até :ano.', ['ano' => (string) $localMax]),
            __('Microdados legíveis; sem alteração detectada desde a última importação.'),
            $routine,
            null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function probeRepasses(): array
    {
        $sourceId = 'repasses_tesouro';
        $routine = PublicDataImportCatalog::routineHint($sourceId);
        $refYear = FundebOpenDataImportService::suggestedImportYear();
        $localMax = (int) (MunicipalTransferSnapshot::query()->max('ano') ?? 0);

        $tesouroTemplate = trim((string) config('ieducar.funding.transfers.tesouro_csv_url_template', ''));

        $remoteHint = '';
        if ($tesouroTemplate !== '') {
            $url = str_replace('{ano}', (string) $refYear, $tesouroTemplate);
            $probe = $this->probeHttpResource($url);
            $remoteHint = $probe['message'];
            if ($probe['ok'] && ($localMax === 0 || $localMax < $refYear)) {
                return $this->finding(
                    $sourceId,
                    'new_available',
                    __('Repasses Tesouro — fonte :ano acessível; snapshots locais até :local.', [
                        'ano' => (string) $refYear,
                        'local' => $localMax > 0 ? (string) $localMax : __('nenhum'),
                    ]),
                    $remoteHint,
                    $routine,
                    'Hub Dados públicos → Repasses → Importar todos os municípios (ano '.$refYear.')',
                );
            }
        }

        if ($localMax >= $refYear && $localMax > 0) {
            return $this->finding(
                $sourceId,
                'unchanged',
                __('Repasses — snapshots locais até :ano.', ['ano' => (string) $localMax]),
                $remoteHint !== '' ? $remoteHint : __('Sem novidade detectada para o exercício vigente.'),
                $routine,
                null,
            );
        }

        return $this->finding(
            $sourceId,
            'attention',
            __('Repasses — exercício :ano pode não estar importado (local: :local).', [
                'ano' => (string) $refYear,
                'local' => $localMax > 0 ? (string) $localMax : __('nenhum'),
            ]),
            $remoteHint !== ''
                ? $remoteHint
                : __('Verifique CKAN/SISWEB/BB no hub Repasses / Tempo Real.'),
            $routine,
            'php artisan funding:rebuild-finance-realtime --all-cities --ano='.$refYear,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function probeSaeb(): array
    {
        $sourceId = 'saeb_inep';
        $routine = PublicDataImportCatalog::routineHint($sourceId);
        $points = (int) app(SaebHistoricoDatabase::class)->pointsCount();
        $localMax = (int) (SaebIndicatorPoint::query()->max('ano') ?? 0);

        $csvUrl = trim((string) config('ieducar.saeb.microdados_opendata_csv_url', ''));
        $remoteOk = false;
        $remoteMessage = '';

        if ($csvUrl !== '') {
            $probe = $this->probeHttpResource($csvUrl);
            $remoteOk = $probe['ok'];
            $remoteMessage = $probe['message'];
        }

        if ($remoteOk && $points === 0) {
            return $this->finding(
                $sourceId,
                'new_available',
                __('SAEB — CSV oficial acessível; nenhum ponto importado localmente.'),
                $remoteMessage,
                $routine,
                'php artisan saeb:import-official (ou Sincronização pedagógica)',
            );
        }

        if ($points > 0) {
            return $this->finding(
                $sourceId,
                'unchanged',
                __('SAEB — :n pontos locais (até :ano).', [
                    'n' => (string) $points,
                    'ano' => $localMax > 0 ? (string) $localMax : '—',
                ]),
                $remoteOk ? $remoteMessage : __('Fonte remota não configurada (IEDUCAR_SAEB_OPENDATA_CSV_URL).'),
                $routine,
                null,
            );
        }

        if ($csvUrl === '') {
            return $this->finding(
                $sourceId,
                'not_configured',
                __('SAEB — URL de dados abertos não configurada.'),
                __('Defina IEDUCAR_SAEB_OPENDATA_CSV_URL ou importe via microdados.'),
                $routine,
                'php artisan saeb:sync-microdados',
            );
        }

        return $this->finding(
            $sourceId,
            $remoteOk ? 'attention' : 'unreachable',
            $remoteOk
                ? __('SAEB — fonte acessível; importação pendente.')
                : __('SAEB — fonte configurada mas inacessível.'),
            $remoteMessage,
            $routine,
            'php artisan saeb:import-official',
        );
    }

    /**
     * @param  array{hub_url: string, cli: ?string, label: ?string}  $routine
     * @return array<string, mixed>
     */
    private function finding(
        string $sourceId,
        string $status,
        string $headline,
        string $detail,
        array $routine,
        ?string $routineOverride,
    ): array {
        $source = PublicDataImportCatalog::findSource($sourceId);

        return [
            'source_id' => $sourceId,
            'source_title' => (string) ($source['title'] ?? $sourceId),
            'status' => $status,
            'headline' => $headline,
            'detail' => $detail,
            'routine_label' => $routine['label'],
            'routine_cli' => $routineOverride ?? $routine['cli'],
            'routine_hub_url' => $routine['hub_url'],
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function probeHttpResource(string $url): array
    {
        $url = trim($url);
        if ($url === '' || ! str_starts_with($url, 'http')) {
            return ['ok' => false, 'message' => __('URL não configurada.')];
        }

        if (! SafeOutboundUrl::isAllowedHttpUrl($url)) {
            return ['ok' => false, 'message' => __('URL não permitida para verificação de saída.')];
        }

        try {
            $timeout = max(5, (int) config('public_data_availability.http_timeout', 12));
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'User-Agent' => 'Servlitcys-PublicData/1.0',
                    'Range' => 'bytes=0-1023',
                    'Accept' => 'text/csv,text/plain,application/json,*/*',
                ])
                ->withOptions(['allow_redirects' => true])
                ->get($url);

            if (in_array($response->status(), [200, 206], true)) {
                return [
                    'ok' => true,
                    'message' => __('Fonte acessível (HTTP :status).', ['status' => (string) $response->status()]),
                ];
            }

            return [
                'ok' => false,
                'message' => __('HTTP :status ao verificar fonte.', ['status' => (string) $response->status()]),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
