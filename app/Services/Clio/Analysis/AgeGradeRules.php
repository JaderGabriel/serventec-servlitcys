<?php

namespace App\Services\Clio\Analysis;

/**
 * Regras aproximadas INEP para idade esperada na série (Matrícula inicial).
 * Distorção idade-série: atraso ≥ 2 anos em relação à idade de referência (31/03 do exercício).
 *
 * Escopo clássico: Ensino Fundamental e Médio regulares. EJA, AEE, AC e etapas sem série
 * ficam fora do denominador oficial (marcados como excluídos).
 */
final class AgeGradeRules
{
    public const STATUS_ON_TRACK = 'adequado';

    public const STATUS_EARLY = 'adiantado';

    public const STATUS_DELAY_1 = 'atraso_1';

    public const STATUS_DISTORTION = 'distorcao';

    public const STATUS_UNKNOWN = 'indefinido';

    public const STATUS_EXCLUDED = 'excluido';

    /**
     * Idade esperada (anos completos em 31/03) para a etapa, ou null se fora do escopo.
     */
    public function expectedAge(string $etapaEnsino): ?int
    {
        $e = mb_strtolower(trim($etapaEnsino));
        if ($e === '' || $e === mb_strtolower(__('Não informado'))) {
            return null;
        }

        // Fora do indicador clássico
        if (
            str_contains($e, 'eja')
            || str_contains($e, 'educação de jovens')
            || str_contains($e, 'educacao de jovens')
            || str_contains($e, 'atividade complementar')
            || preg_match('/\baee\b|atendimento educacional/i', $e) === 1
            || str_contains($e, 'multietapa')
            || str_contains($e, 'não seriada')
            || str_contains($e, 'nao seriada')
        ) {
            return null;
        }

        // Ensino Médio
        if (str_contains($e, 'médio') || str_contains($e, 'medio')) {
            if (preg_match('/1[ºo°]\s*ano|primeiro\s*ano/u', $e) === 1) {
                return 15;
            }
            if (preg_match('/2[ºo°]\s*ano|segundo\s*ano/u', $e) === 1) {
                return 16;
            }
            if (preg_match('/3[ºo°]\s*ano|terceiro\s*ano/u', $e) === 1) {
                return 17;
            }
            if (preg_match('/4[ºo°]\s*ano|quarto\s*ano/u', $e) === 1) {
                return 18;
            }

            return null;
        }

        // Ensino Fundamental 9 anos — 1º…9º
        if (str_contains($e, 'fundamental') || preg_match('/\d+[ºo°]\s*ano/u', $e) === 1) {
            for ($ano = 1; $ano <= 9; $ano++) {
                if (preg_match('/\b'.$ano.'[ºo°]\s*ano\b/u', $e) === 1) {
                    return 5 + $ano; // 1º→6 … 9º→14
                }
            }
        }

        // Pré-escola / educação infantil (referência suave)
        if (str_contains($e, 'infantil') || str_contains($e, 'pré') || str_contains($e, 'pre-escola') || str_contains($e, 'pré-escola')) {
            if (preg_match('/\b5\b|cinco/u', $e) === 1) {
                return 5;
            }
            if (preg_match('/\b4\b|quatro/u', $e) === 1) {
                return 4;
            }
        }

        return null;
    }

    public function ageAtReference(?\DateTimeImmutable $birth, int $referenceYear): ?int
    {
        if ($birth === null) {
            return null;
        }
        $ref = \DateTimeImmutable::createFromFormat('!Y-m-d', sprintf('%d-03-31', $referenceYear));
        if (! $ref instanceof \DateTimeImmutable) {
            return null;
        }
        $age = (int) $birth->diff($ref)->y;
        if ($age < 0 || $age > 120) {
            return null;
        }

        return $age;
    }

    public function parseBirthDate(string $raw): ?\DateTimeImmutable
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $m) === 1) {
            $dt = \DateTimeImmutable::createFromFormat('!d/m/Y', sprintf('%s/%s/%s', $m[1], $m[2], $m[3]));

            return $dt instanceof \DateTimeImmutable ? $dt : null;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw) === 1) {
            $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);

            return $dt instanceof \DateTimeImmutable ? $dt : null;
        }

        return null;
    }

    /**
     * @return array{status: string, delay: int|null, age: int|null, expected: int|null}
     */
    public function classify(string $etapaEnsino, string $birthRaw, int $referenceYear): array
    {
        $expected = $this->expectedAge($etapaEnsino);
        if ($expected === null) {
            return [
                'status' => self::STATUS_EXCLUDED,
                'delay' => null,
                'age' => null,
                'expected' => null,
            ];
        }

        $birth = $this->parseBirthDate($birthRaw);
        $age = $this->ageAtReference($birth, $referenceYear);
        if ($age === null) {
            return [
                'status' => self::STATUS_UNKNOWN,
                'delay' => null,
                'age' => null,
                'expected' => $expected,
            ];
        }

        $delay = $age - $expected;
        if ($delay >= 2) {
            $status = self::STATUS_DISTORTION;
        } elseif ($delay === 1) {
            $status = self::STATUS_DELAY_1;
        } elseif ($delay < 0) {
            $status = self::STATUS_EARLY;
        } else {
            $status = self::STATUS_ON_TRACK;
        }

        return [
            'status' => $status,
            'delay' => $delay,
            'age' => $age,
            'expected' => $expected,
        ];
    }
}
