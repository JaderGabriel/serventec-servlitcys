<?php

namespace App\Services\Inep;

use App\Models\City;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Importa séries SAEB a partir de CSV tabular (exportações INEP, dados abertos ou folhas próprias).
 *
 * Formato mínimo (cabeçalho na 1.ª linha; nomes reconhecidos em várias variantes):
 * - IBGE do município (7 dígitos): municipio_ibge, co_municipio, ibge, …
 * - Ano da aplicação: ano, ano_aplicacao, nu_ano, …
 * - Disciplina (LP/MAT ou texto): disciplina, disc, …
 * - Etapa (anos iniciais/finais, EM, …): etapa, etapa_ensino, …
 * - Valor numérico (% proficientes ou escala conforme a fonte): valor, percentual, pc_proficientes, …
 * - Opcional — escola (INEP): inep_escola, co_escola, cod_escola_inep, … (--resolve-inep mapeia para cod_escola)
 * - Opcional — escola (i-Educar): escola_id ou cod_escola_ie (usa o id interno directamente, sem INEP)
 * - Opcional: status (final|preliminar), unidade (ex.: %)
 */
final class SaebCsvPedagogicalImportService
{
    /** @var list<string> */
    private const IBGE_KEYS = ['municipio_ibge', 'co_municipio', 'ibge', 'id_municipio', 'cod_municipio'];

    /** @var list<string> */
    private const YEAR_KEYS = ['ano', 'ano_aplicacao', 'nu_ano', 'year', 'ano_referencia'];

    /** @var list<string> */
    private const DISC_KEYS = ['disciplina', 'disc', 'sg_disciplina', 'componente'];

    /** @var list<string> */
    private const ETAPA_KEYS = ['etapa', 'etapa_ensino', 'ds_etapa', 'fase'];

    /** @var list<string> */
    private const VAL_KEYS = ['valor', 'v', 'percentual', 'pc_proficientes', 'nu_media', 'media'];

    /** @var list<string> */
    private const STATUS_KEYS = ['status', 'tipo', 'tp_resultado', 'natureza'];

    /** @var list<string> */
    private const UNIDADE_KEYS = ['unidade', 'unit'];

    /** @var list<string> */
    private const INEP_KEYS = ['inep_escola', 'co_escola', 'id_escola', 'cod_escola_inep', 'nu_inep', 'codigo_inep'];

    /** @var list<string> */
    private const COD_ESCOLA_IE_KEYS = ['escola_id', 'cod_escola_ie', 'cod_escola_ieducar'];

    public function __construct(
        private SaebPedagogicalImportService $writer,
        private SaebInepToEscolaIdResolver $inepResolver,
    ) {}

    /**
     * @return array{ok: bool, message: string, fonte_efetiva: ?string, path: string, avisos?: list<string>}
     */
    public function importFromCsvFile(
        string $absolutePath,
        bool $mergeExisting = true,
        bool $resolveInep = true,
    ): array {
        $rel = $this->relativePath();
        if (! is_readable($absolutePath)) {
            return [
                'ok' => false,
                'message' => __('Ficheiro inexistente ou ilegível: :path', ['path' => $absolutePath]),
                'fonte_efetiva' => null,
                'path' => $rel,
            ];
        }

        $raw = File::get($absolutePath);
        if ($raw === false || $raw === '') {
            return [
                'ok' => false,
                'message' => __('Ficheiro vazio.'),
                'fonte_efetiva' => null,
                'path' => $rel,
            ];
        }
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        $delimiter = $this->detectDelimiter((string) $raw);
        $lines = preg_split("/\r\n|\n|\r/", $raw) ?: [];
        $lines = array_values(array_filter($lines, static fn (string $l): bool => trim($l) !== ''));
        if ($lines === []) {
            return [
                'ok' => false,
                'message' => __('Sem linhas de dados.'),
                'fonte_efetiva' => null,
                'path' => $rel,
            ];
        }

        $headerLine = array_shift($lines);
        $headers = str_getcsv((string) $headerLine, $delimiter);
        $headers = array_map(static function (string $h): string {
            $h = strtolower(trim($h));
            $h = str_replace([' ', '-', '.'], '_', $h);

            return $h;
        }, $headers);

        $col = $this->mapColumns($headers);
        if ($col['ibge'] === null || $col['year'] === null || $col['disc'] === null || $col['etapa'] === null || $col['val'] === null) {
            return [
                'ok' => false,
                'message' => __(
                    'Cabeçalho incompleto. São obrigatórias colunas para: IBGE do município, ano, disciplina, etapa e valor. Encontrado: :h',
                    ['h' => implode(', ', $headers)]
                ),
                'fonte_efetiva' => null,
                'path' => $rel,
            ];
        }

        $ibgeToCity = $this->citiesByIbge();
        $pontosNovos = [];
        $avisos = [];
        $inepUnmapped = 0;

        foreach ($lines as $n => $line) {
            $row = str_getcsv($line, $delimiter);
            if (count($row) < 1) {
                continue;
            }
            $assoc = [];
            foreach ($headers as $i => $name) {
                $assoc[$name] = isset($row[$i]) ? trim((string) $row[$i]) : '';
            }

            $ibgeRaw = $col['ibge'] !== null ? ($assoc[$headers[$col['ibge']]] ?? '') : '';
            $ibge = $this->normalizeIbge($ibgeRaw);
            if ($ibge === null) {
                $avisos[] = __('Linha :n: IBGE inválido «:v».', ['n' => (string) ($n + 2), 'v' => $ibgeRaw]);

                continue;
            }

            $city = $ibgeToCity[$ibge] ?? null;
            if ($city === null) {
                $avisos[] = __('Linha :n: município IBGE :ibge não corresponde a nenhuma cidade activa com base configurada.', [
                    'n' => (string) ($n + 2),
                    'ibge' => $ibge,
                ]);

                continue;
            }

            $yearStr = $col['year'] !== null ? ($assoc[$headers[$col['year']]] ?? '') : '';
            $year = $this->parseInt($yearStr);
            if ($year === null || $year < 1990 || $year > 2100) {
                $avisos[] = __('Linha :n: ano inválido.', ['n' => (string) ($n + 2)]);

                continue;
            }

            $discRaw = $col['disc'] !== null ? ($assoc[$headers[$col['disc']]] ?? '') : '';
            $disc = $this->normalizeDisciplina($discRaw);
            $etapaRaw = $col['etapa'] !== null ? ($assoc[$headers[$col['etapa']]] ?? '') : '';
            $etapa = $this->normalizeEtapa($etapaRaw);

            $valStr = $col['val'] !== null ? ($assoc[$headers[$col['val']]] ?? '') : '';
            $valStr = str_replace(',', '.', $valStr);
            if (! is_numeric($valStr)) {
                $avisos[] = __('Linha :n: valor não numérico.', ['n' => (string) ($n + 2)]);

                continue;
            }

            $statusRaw = $col['status'] !== null ? ($assoc[$headers[$col['status']]] ?? '') : 'final';
            $status = $this->normalizeStatus($statusRaw);

            $unidade = '%';
            if ($col['unidade'] !== null && isset($headers[$col['unidade']])) {
                $u = trim((string) ($assoc[$headers[$col['unidade']]] ?? ''));
                if ($u !== '') {
                    $unidade = $u;
                }
            }

            $ponto = [
                'ano' => $year,
                'disciplina' => $disc,
                'etapa' => $etapa,
                'valor' => (float) $valStr,
                'status' => $status,
                'unidade' => $unidade,
                'city_ids' => [(int) $city->getKey()],
                'municipio_ibge' => $ibge,
            ];

            $inepVal = null;
            if ($col['inep'] !== null && isset($headers[$col['inep']])) {
                $inepVal = $this->parseInt($assoc[$headers[$col['inep']]] ?? '');
            }

            $codIe = null;
            if ($col['cod_escola_ie'] !== null && isset($headers[$col['cod_escola_ie']])) {
                $codIe = $this->parseInt($assoc[$headers[$col['cod_escola_ie']]] ?? '');
            }

            if ($codIe !== null && $codIe > 0) {
                $ponto['escola_id'] = $codIe;
            } elseif ($inepVal !== null && $inepVal > 0) {
                if ($resolveInep) {
                    $codEscola = $this->inepResolver->resolve($city, $inepVal);
                    if ($codEscola !== null) {
                        $ponto['escola_id'] = $codEscola;
                    } else {
                        $inepUnmapped++;
                        $avisos[] = __('INEP :inep (IBGE :ibge): não foi encontrado cod_escola no i-Educar; linha guardada só ao nível municipal (sem escola_id).', [
                            'inep' => (string) $inepVal,
                            'ibge' => $ibge,
                        ]);
                    }
                }
            }

            $pontosNovos[] = $ponto;
        }

        if ($pontosNovos === []) {
            return [
                'ok' => false,
                'message' => __('Nenhuma linha válida.')."\n".implode("\n", $avisos),
                'fonte_efetiva' => null,
                'path' => $rel,
                'avisos' => $avisos,
            ];
        }

        $payload = $this->mergePayload($pontosNovos, $mergeExisting, $absolutePath);
        $extra = [];
        if ($avisos !== []) {
            $extra[] = __('Avisos:')."\n".implode("\n", array_slice($avisos, 0, 80));
            if (count($avisos) > 80) {
                $extra[] = __('… e mais :n avisos.', ['n' => (string) (count($avisos) - 80)]);
            }
        }
        if ($inepUnmapped > 0) {
            $extra[] = __('Total de linhas com INEP não mapeado a cod_escola: :n.', ['n' => (string) $inepUnmapped]);
        }

        $fonte = 'saeb:csv:'.basename($absolutePath);

        $out = $this->writer->persistHistoricoJson(
            $payload,
            $fonte,
            [$absolutePath],
            $extra !== [] ? implode("\n\n", $extra) : null
        );
        $out['avisos'] = $avisos;

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $pontosNovos
     * @return array{pontos: list<array<string, mixed>>, meta?: array<string, mixed>}
     */
    private function mergePayload(array $pontosNovos, bool $mergeExisting, string $sourcePath): array
    {
        $rel = $this->relativePath();
        $disk = Storage::disk('public');
        $existingPontos = [];
        $meta = [
            'descricao' => __('Séries SAEB importadas por CSV (municípios e opcionalmente escolas).'),
            'fonte' => __('INEP / dados abertos ou folha própria; conferir colunas na origem.'),
            'csv_origem' => $sourcePath,
        ];

        if ($mergeExisting && $disk->exists($rel)) {
            $raw = $disk->get($rel);
            $decoded = json_decode((string) $raw, true);
            if (is_array($decoded)) {
                $prev = $decoded['pontos'] ?? $decoded['points'] ?? [];
                if (is_array($prev)) {
                    foreach ($prev as $p) {
                        if (is_array($p)) {
                            $existingPontos[] = $p;
                        }
                    }
                }
                if (isset($decoded['meta']) && is_array($decoded['meta'])) {
                    $meta = array_merge($decoded['meta'], $meta);
                }
            }
        }

        $signatures = [];
        foreach ($existingPontos as $p) {
            if (! is_array($p)) {
                continue;
            }
            $sig = $this->signatureFromPonto($p);
            if ($sig !== null) {
                $signatures[$sig] = true;
            }
        }

        $merged = $existingPontos;
        foreach ($pontosNovos as $p) {
            $sig = $this->signatureFromPonto($p);
            if ($sig === null) {
                $merged[] = $p;

                continue;
            }
            if (isset($signatures[$sig])) {
                $merged = array_values(array_filter($merged, static function (array $old) use ($sig): bool {
                    return $sig !== self::staticSignatureFromPonto($old);
                }));
            }
            $signatures[$sig] = true;
            $merged[] = $p;
        }

        return [
            'meta' => $meta,
            'pontos' => array_values($merged),
        ];
    }

    /**
     * @param  array<string, mixed>  $p
     */
    private function signatureFromPonto(array $p): ?string
    {
        return self::staticSignatureFromPonto($p);
    }

    /**
     * @param  array<string, mixed>  $p
     */
    private static function staticSignatureFromPonto(array $p): ?string
    {
        $ids = $p['city_ids'] ?? [];
        if (! is_array($ids) || $ids === []) {
            return null;
        }
        $cityId = (int) ($ids[0] ?? 0);
        $year = 0;
        if (isset($p['ano'])) {
            $year = (int) $p['ano'];
        } elseif (isset($p['year'])) {
            $year = (int) $p['year'];
        }
        $disc = strtolower((string) ($p['disciplina'] ?? $p['disc'] ?? ''));
        $etapa = strtolower((string) ($p['etapa'] ?? ''));
        $st = strtolower((string) ($p['status'] ?? 'final'));
        $eid = isset($p['escola_id']) && is_numeric($p['escola_id']) ? (int) $p['escola_id'] : 0;

        if ($year <= 0 || $disc === '' || $cityId <= 0) {
            return null;
        }

        return $cityId.'|'.$year.'|'.$disc.'|'.$etapa.'|'.$eid.'|'.$st;
    }

    private function relativePath(): string
    {
        return trim((string) config('ieducar.saeb.json_path', 'saeb/historico.json')) ?: 'saeb/historico.json';
    }

    /**
     * @return array<string, City>
     */
    private function citiesByIbge(): array
    {
        $out = [];
        foreach (City::query()->forAnalytics()->whereNotNull('ibge_municipio')->orderBy('id')->get() as $city) {
            $ibge = $this->normalizeIbge((string) $city->ibge_municipio);
            if ($ibge !== null) {
                $out[$ibge] = $city;
            }
        }

        return $out;
    }

    private function normalizeIbge(string $raw): ?string
    {
        $d = preg_replace('/\D/', '', $raw) ?? '';
        if ($d === '') {
            return null;
        }
        if (strlen($d) < 7) {
            $d = str_pad($d, 7, '0', STR_PAD_LEFT);
        }
        if (strlen($d) !== 7) {
            return null;
        }

        return $d;
    }

    private function parseInt(string $raw): ?int
    {
        $raw = trim($raw);
        if ($raw === '' || ! is_numeric($raw)) {
            return null;
        }

        return (int) $raw;
    }

    private function normalizeDisciplina(string $raw): string
    {
        $s = strtolower(trim($raw));
        if ($s === '' || str_contains($s, 'port') || $s === 'lp' || $s === 'lingua_portuguesa' || $s === '1') {
            return 'lp';
        }
        if (str_contains($s, 'mat') || $s === '2') {
            return 'mat';
        }

        return $s !== '' ? $s : 'lp';
    }

    private function normalizeEtapa(string $raw): string
    {
        $s = strtolower(trim($raw));
        if ($s === '') {
            return 'geral';
        }
        if (str_contains($s, 'inicia') || $s === 'efi' || $s === 'ef_i' || str_contains($s, 'anos_iniciais')) {
            return 'efi';
        }
        if (str_contains($s, 'finais') || $s === 'efaf' || $s === 'ef_ii' || str_contains($s, 'anos_finais')) {
            return 'efaf';
        }
        if (str_contains($s, 'médio') || str_contains($s, 'medio') || $s === 'em' || str_contains($s, 'ensino_medio')) {
            return 'em';
        }
        if (str_contains($s, 'infantil') || $s === 'ei') {
            return 'ei';
        }

        return $s;
    }

    private function normalizeStatus(string $raw): string
    {
        $s = strtolower(trim($raw));
        if ($s === '' || str_contains($s, 'final')) {
            return 'final';
        }
        if (str_contains($s, 'prelim')) {
            return 'preliminar';
        }

        return $s;
    }

    /**
     * @param  list<string>  $headers
     * @return array{ibge: ?int, year: ?int, disc: ?int, etapa: ?int, val: ?int, status: ?int, unidade: ?int, inep: ?int, cod_escola_ie: ?int}
     */
    private function mapColumns(array $headers): array
    {
        $find = static function (array $keys) use ($headers): ?int {
            foreach ($headers as $i => $h) {
                if (in_array($h, $keys, true)) {
                    return $i;
                }
            }

            return null;
        };

        return [
            'ibge' => $find(self::IBGE_KEYS),
            'year' => $find(self::YEAR_KEYS),
            'disc' => $find(self::DISC_KEYS),
            'etapa' => $find(self::ETAPA_KEYS),
            'val' => $find(self::VAL_KEYS),
            'status' => $find(self::STATUS_KEYS),
            'unidade' => $find(self::UNIDADE_KEYS),
            'inep' => $find(self::INEP_KEYS),
            'cod_escola_ie' => $find(self::COD_ESCOLA_IE_KEYS),
        ];
    }

    private function detectDelimiter(string $raw): string
    {
        $first = strtok($raw, "\r\n");
        $first = $first !== false ? (string) $first : '';
        $semi = substr_count($first, ';');
        $comma = substr_count($first, ',');

        return $semi >= $comma ? ';' : ',';
    }
}
