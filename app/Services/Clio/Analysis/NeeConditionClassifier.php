<?php

namespace App\Services\Clio\Analysis;

/**
 * Classifica marcadores de educação especial nas Relações de alunos:
 * deficiências (DEF-*) × transtornos (TRS-*) × altas habilidades (AH),
 * e sinaliza possíveis subnotificações / comorbidades esperadas.
 *
 * Heurísticas — não substituem laudo clínico nem a validação da rede.
 */
final class NeeConditionClassifier
{
    public const KIND_DEFICIENCY = 'deficiency';

    public const KIND_DISORDER = 'disorder';

    public const KIND_AH = 'ah';

    public const KIND_OTHER = 'other';

    /** @var list<array{code: string, kind: string, label: string, patterns: list<string>}> */
    private const COLUMN_MAP = [
        [
            'code' => 'DEF-CEG',
            'kind' => self::KIND_DEFICIENCY,
            'label' => 'Cegueira',
            'patterns' => ['/^cegueira$/iu', '/\bcegueira\b/iu'],
        ],
        [
            'code' => 'DEF-BV',
            'kind' => self::KIND_DEFICIENCY,
            'label' => 'Baixa visão',
            'patterns' => ['/baixa\s*vis[aã]o/iu'],
        ],
        [
            'code' => 'DEF-VM',
            'kind' => self::KIND_DEFICIENCY,
            'label' => 'Visão monocular',
            'patterns' => ['/vis[aã]o\s*monocular|monocular/iu'],
        ],
        [
            'code' => 'DEF-SUR',
            'kind' => self::KIND_DEFICIENCY,
            'label' => 'Surdez',
            'patterns' => ['/^surdez$/iu', '/\bsurdez\b/iu'],
        ],
        [
            'code' => 'DEF-AUD',
            'kind' => self::KIND_DEFICIENCY,
            'label' => 'Deficiência auditiva',
            'patterns' => ['/defici[eê]ncia\s*auditiva/iu'],
        ],
        [
            'code' => 'DEF-SC',
            'kind' => self::KIND_DEFICIENCY,
            'label' => 'Surdocegueira',
            'patterns' => ['/surdocegueira|surdo[\-\s]?cegueira/iu'],
        ],
        [
            'code' => 'DEF-FIS',
            'kind' => self::KIND_DEFICIENCY,
            'label' => 'Deficiência física',
            'patterns' => ['/defici[eê]ncia\s*f[ií]sica/iu'],
        ],
        [
            'code' => 'DEF-INT',
            'kind' => self::KIND_DEFICIENCY,
            'label' => 'Deficiência intelectual',
            'patterns' => ['/defici[eê]ncia\s*intelectual/iu'],
        ],
        [
            'code' => 'DEF-MUL',
            'kind' => self::KIND_DEFICIENCY,
            'label' => 'Deficiência múltipla',
            'patterns' => ['/defici[eê]ncia\s*m[uú]ltipla/iu'],
        ],
        [
            'code' => 'TRS-TEA',
            'kind' => self::KIND_DISORDER,
            'label' => 'Transtorno do espectro autista (TEA)',
            'patterns' => ['/transtorno\s+do\s+espectro|autis|\btea\b/iu'],
        ],
        [
            'code' => 'AH',
            'kind' => self::KIND_AH,
            'label' => 'Altas habilidades / superdotação',
            'patterns' => ['/altas\s*habil|superdota/iu'],
        ],
    ];

    /**
     * @param  array<string, string>  $row
     * @return array{
     *   conditions: list<array{code: string, kind: string, label: string}>,
     *   deficiencies: list<array{code: string, kind: string, label: string}>,
     *   disorders: list<array{code: string, kind: string, label: string}>,
     *   ah: list<array{code: string, kind: string, label: string}>,
     *   other: list<array{code: string, kind: string, label: string}>,
     *   codes: list<string>,
     *   tags: list<string>,
     *   flagged: bool,
     *   has_specific_deficiency: bool,
     *   has_generic_deficiency: bool,
     *   has_granular_def_columns: bool
     * }
     */
    public function classifyRow(array $row): array
    {
        $found = [];
        $hasGranularDef = false;

        foreach ($row as $key => $value) {
            if (! $this->isPositiveMarker((string) $value)) {
                continue;
            }
            $k = (string) $key;
            $mapped = $this->mapColumn($k);
            if ($mapped === null) {
                if ($this->isGenericNeeColumn($k)) {
                    $found['NEE'] = [
                        'code' => 'NEE',
                        'kind' => self::KIND_OTHER,
                        'label' => __('NEE (marcador genérico)'),
                    ];
                }

                continue;
            }

            if ($mapped['code'] === 'DEF-GENERIC') {
                $found['DEF'] = [
                    'code' => 'DEF',
                    'kind' => self::KIND_DEFICIENCY,
                    'label' => __('Deficiência (tipo não discriminado)'),
                ];

                continue;
            }

            if ($mapped['kind'] === self::KIND_DEFICIENCY) {
                $hasGranularDef = true;
            }

            $found[$mapped['code']] = [
                'code' => $mapped['code'],
                'kind' => $mapped['kind'],
                'label' => __($mapped['label']),
            ];
        }

        foreach (array_keys($row) as $key) {
            $mapped = $this->mapColumn((string) $key);
            if ($mapped !== null && $mapped['kind'] === self::KIND_DEFICIENCY && $mapped['code'] !== 'DEF-GENERIC') {
                $hasGranularDef = true;
                break;
            }
        }

        $specificDefs = array_values(array_filter(
            $found,
            static fn (array $c): bool => $c['kind'] === self::KIND_DEFICIENCY && $c['code'] !== 'DEF',
        ));
        if (count($specificDefs) >= 2 && ! isset($found['DEF-MUL'])) {
            $found['DEF-MUL*'] = [
                'code' => 'DEF-MUL*',
                'kind' => self::KIND_DEFICIENCY,
                'label' => __('Deficiência múltipla (derivada)'),
            ];
        }

        $conditions = array_values($found);
        $deficiencies = array_values(array_filter($conditions, static fn (array $c): bool => $c['kind'] === self::KIND_DEFICIENCY));
        $disorders = array_values(array_filter($conditions, static fn (array $c): bool => $c['kind'] === self::KIND_DISORDER));
        $ah = array_values(array_filter($conditions, static fn (array $c): bool => $c['kind'] === self::KIND_AH));
        $other = array_values(array_filter($conditions, static fn (array $c): bool => $c['kind'] === self::KIND_OTHER));

        $tags = [];
        foreach ($deficiencies as $c) {
            if (in_array($c['code'], ['DEF', 'DEF-MUL*'], true)) {
                $tags['Deficiência'] = __('Deficiência');
            } else {
                $tags[$c['code']] = $c['label'];
            }
        }
        foreach ($disorders as $c) {
            $tags[$c['code'] === 'TRS-TEA' ? 'TEA' : $c['code']] = $c['code'] === 'TRS-TEA' ? 'TEA' : $c['label'];
        }
        foreach ($ah as $c) {
            $tags['AH'] = 'AH';
        }
        foreach ($other as $c) {
            $tags[$c['code']] = $c['label'];
        }

        return [
            'conditions' => $conditions,
            'deficiencies' => $deficiencies,
            'disorders' => $disorders,
            'ah' => $ah,
            'other' => $other,
            'codes' => array_column($conditions, 'code'),
            'tags' => array_values($tags),
            'flagged' => $conditions !== [],
            'has_specific_deficiency' => $specificDefs !== [],
            'has_generic_deficiency' => isset($found['DEF']),
            'has_granular_def_columns' => $hasGranularDef,
        ];
    }

    /**
     * @param  array<string, mixed>  $classified
     * @return list<array{code: string, label: string, severity: string, hint: string}>
     */
    public function assessUnderreporting(array $classified, bool $hasAee = false): array
    {
        $flags = [];
        $codes = array_flip(is_array($classified['codes'] ?? null) ? $classified['codes'] : []);
        $hasTea = isset($codes['TRS-TEA']);
        $hasDi = isset($codes['DEF-INT']);
        $hasGenericDef = ! empty($classified['has_generic_deficiency']);
        $hasSpecificDef = ! empty($classified['has_specific_deficiency']);
        $hasGranular = ! empty($classified['has_granular_def_columns']);
        $hasAny = ! empty($classified['flagged']);
        $hasSc = isset($codes['DEF-SC']);
        $hasVisual = isset($codes['DEF-CEG']) || isset($codes['DEF-BV']) || isset($codes['DEF-VM']);
        $hasHearing = isset($codes['DEF-SUR']) || isset($codes['DEF-AUD']);
        $hasCegueira = isset($codes['DEF-CEG']);
        $hasSurdez = isset($codes['DEF-SUR']);
        $hasMulDeclared = isset($codes['DEF-MUL']);
        $specificDefCount = count(array_filter(
            is_array($classified['deficiencies'] ?? null) ? $classified['deficiencies'] : [],
            static fn (array $c): bool => ! in_array($c['code'], ['DEF', 'DEF-MUL', 'DEF-MUL*'], true),
        ));

        if ($hasAee && ! $hasAny) {
            $flags[] = $this->flag(
                'SUB-AEE-SEM-NEE',
                'warning',
                __('AEE sem condição declarada'),
                __('Matrícula em AEE sem deficiência, TEA ou AH marcados — possível subnotificação no cadastro do aluno.'),
            );
        }

        if ($hasGenericDef && $hasGranular && ! $hasSpecificDef) {
            $flags[] = $this->flag(
                'SUB-DEF-SEM-TIPO',
                'warning',
                __('Deficiência sem tipo'),
                __('Há marcador genérico de deficiência, mas nenhum tipo específico (visual, auditiva, física, intelectual…) — revise o preenchimento.'),
            );
        }

        if ($hasMulDeclared && $specificDefCount < 2) {
            $flags[] = $this->flag(
                'SUB-MUL-INCOMPLETA',
                'warning',
                __('Múltipla incompleta'),
                __('Deficiência múltipla exige associação de duas ou mais deficiências; no export há menos de dois tipos específicos.'),
            );
        }

        if ($hasSc && (! $hasVisual || ! $hasHearing)) {
            $flags[] = $this->flag(
                'SUB-SC-COMPONENTES',
                'info',
                __('Surdocegueira sem componentes'),
                __('Surdocegueira normalmente implica compromisso visual e auditivo — confira se os tipos componentes foram declarados.'),
            );
        }

        if ($hasCegueira && $hasSurdez && ! $hasSc) {
            $flags[] = $this->flag(
                'SUB-SC-AUSENTE',
                'info',
                __('Possível surdocegueira'),
                __('Cegueira e surdez juntas costumam corresponder a surdocegueira no Censo — verifique se o campo adequado foi usado.'),
            );
        }

        if ($hasTea && ! $hasDi && ! $hasGenericDef) {
            $flags[] = $this->flag(
                'SUB-TEA-DI',
                'info',
                __('TEA · possível DI'),
                __('TEA frequentemente coexiste com deficiência intelectual. Se houver evidência pedagógica/clínica, declare a DI; caso contrário, ignore o alerta.'),
            );
        }

        if ($hasTea && $hasGenericDef && ! $hasSpecificDef && $hasGranular) {
            $flags[] = $this->flag(
                'SUB-TEA-DEF-GEN',
                'warning',
                __('TEA + deficiência sem tipo'),
                __('TEA com deficiência genérica e sem tipo específico — priorize discriminar a deficiência associada (ex.: intelectual).'),
            );
        }

        return $flags;
    }

    /**
     * @return array{code: string, label: string, severity: string, hint: string}
     */
    private function flag(string $code, string $severity, string $label, string $hint): array
    {
        return [
            'code' => $code,
            'label' => $label,
            'severity' => $severity,
            'hint' => $hint,
        ];
    }

    private function isPositiveMarker(string $raw): bool
    {
        $v = mb_strtolower(trim($raw));
        if ($v === '') {
            return false;
        }

        // Mojibake comum: UTF-8 «Não» interpretado como Latin-1 → «NÃ£o».
        $v = str_replace(
            ['Ã£', 'Ã¡', 'Ã©', 'Ã­', 'Ã³', 'Ãº', 'Ã§', 'Ãƒ', 'Ã'],
            ['ã', 'á', 'é', 'í', 'ó', 'ú', 'ç', 'ã', ''],
            $v,
        );

        // Negação por prefixo («Não», «Não possui deficiência», «Não se aplica»…).
        if (preg_match('/^(n[aã]o)\b/u', $v) === 1) {
            return false;
        }
        if (preg_match('/^sem\s+/u', $v) === 1) {
            return false;
        }
        if (in_array($v, [
            'n', '0', 'false', 'f', 'ni', '-',
            'n/a', 'na', 'n.a.', 'n.a',
        ], true)) {
            return false;
        }

        // Whitelist: só Sim/códigos claros ou nome da condição como valor.
        if (preg_match('/^(sim|s|yes|true|1|x)$/u', $v) === 1) {
            return true;
        }

        return preg_match(
            '/cegueira|baixa\s*vis|vis[aã]o\s*monocular|surdez|surdocegueira|defici[eê]ncia|autis|\btea\b|espectro\s+autista|superdo|altas\s*habil/u',
            $v,
        ) === 1;
    }

    /**
     * @return array{code: string, kind: string, label: string}|null
     */
    private function mapColumn(string $header): ?array
    {
        $h = trim($header);
        if ($h === '') {
            return null;
        }

        if ($this->isGenericDeficiencyColumn($h)) {
            return [
                'code' => 'DEF-GENERIC',
                'kind' => self::KIND_DEFICIENCY,
                'label' => 'Deficiência (tipo não discriminado)',
            ];
        }

        foreach (self::COLUMN_MAP as $item) {
            foreach ($item['patterns'] as $pattern) {
                if (preg_match($pattern, $h) === 1) {
                    return [
                        'code' => $item['code'],
                        'kind' => $item['kind'],
                        'label' => $item['label'],
                    ];
                }
            }
        }

        return null;
    }

    private function isGenericDeficiencyColumn(string $header): bool
    {
        $h = mb_strtolower(trim($header));
        if (preg_match('/defici/iu', $h) !== 1) {
            return false;
        }
        if (preg_match('/f[ií]sica|intelectual|auditiva|visual|m[uú]ltipla|cegueira|surdez|baixa|monocular|surdo/iu', $h) === 1) {
            return false;
        }

        return true;
    }

    private function isGenericNeeColumn(string $header): bool
    {
        $h = mb_strtolower(trim($header));

        return preg_match('/\bnee\b/iu', $h) === 1
            && preg_match('/turma|tipo|aee/iu', $h) !== 1;
    }
}
