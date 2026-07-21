<?php

namespace App\Services\Clio\Ingest;

/**
 * Classifica arquivos da coleta Educacenso 1ª etapa pelo nome.
 */
final class ArtifactClassifier
{
    /**
     * @return array{kind: string, inep_code: ?string, ignored: bool}
     */
    public function classify(string $filename, ?string $relativePath = null): array
    {
        $base = basename(str_replace('\\', '/', $filename));

        if (str_starts_with($base, '.~lock.') || str_starts_with($base, '.')) {
            return ['kind' => 'unknown', 'inep_code' => null, 'ignored' => true];
        }

        $inep = $this->extractInepFromPath($relativePath ?? $filename);

        if (preg_match('/Relatorio_Acomp_Coleta_1Etapa_/i', $base) === 1) {
            return ['kind' => 'acomp_coleta_1etapa', 'inep_code' => $inep, 'ignored' => false];
        }

        if (preg_match('/RelacaoAlunoEscola_/i', $base) === 1) {
            return ['kind' => 'relacao_aluno_escola', 'inep_code' => $inep, 'ignored' => false];
        }

        if (preg_match('/RelacaoTurmaEscola_/i', $base) === 1) {
            return ['kind' => 'relacao_turma_escola', 'inep_code' => $inep, 'ignored' => false];
        }

        if (preg_match('/RelacaoProfissionalEscola_/i', $base) === 1) {
            return ['kind' => 'relacao_profissional_escola', 'inep_code' => $inep, 'ignored' => false];
        }

        if (preg_match('/\.zip$/i', $base) === 1) {
            return ['kind' => 'pacote_zip', 'inep_code' => null, 'ignored' => false];
        }

        if (preg_match('/\.txt$/i', $base) === 1) {
            return ['kind' => 'migracao_txt', 'inep_code' => $inep, 'ignored' => false];
        }

        return ['kind' => 'unknown', 'inep_code' => $inep, 'ignored' => false];
    }

    public function extractInepFromPath(?string $path): ?string
    {
        $label = $this->schoolLabelFromPath($path);

        return $label['inep'] ?? null;
    }

    /**
     * Pasta tipo `29174651 - ESCOLA X` ou `29157714 EE - Nome`.
     *
     * @return array{inep: ?string, name: ?string}
     */
    public function schoolLabelFromPath(?string $path): array
    {
        if ($path === null || $path === '') {
            return ['inep' => null, 'name' => null];
        }

        $normalized = str_replace('\\', '/', $path);
        $segments = array_values(array_filter(explode('/', $normalized), static fn (string $s): bool => $s !== ''));

        foreach ($segments as $segment) {
            if (preg_match('/^(\d{8})\s*[-–]\s*(.+)$/u', $segment, $m) === 1) {
                return [
                    'inep' => $m[1],
                    'name' => trim($m[2]),
                ];
            }

            // Ex.: «29157714 EE - Nome da escola»
            if (preg_match('/^(\d{8})\s+(.+)$/u', $segment, $m) === 1) {
                return [
                    'inep' => $m[1],
                    'name' => trim($m[2]),
                ];
            }

            if (preg_match('/^(\d{8})$/', $segment, $m) === 1) {
                return ['inep' => $m[1], 'name' => null];
            }
        }

        if (preg_match('/(?:^|\/)(\d{8})\s*[-– ]/', $normalized, $m) === 1) {
            return ['inep' => $m[1], 'name' => null];
        }

        return ['inep' => null, 'name' => null];
    }
}
