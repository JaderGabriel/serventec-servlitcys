#!/usr/bin/env php
<?php

/**
 * Gera arquivo Educacenso sintético (pipe-delimited) para teste de carga/timeout.
 *
 * Uso:
 *   php tests/fixtures/educacenso/generate_load_test.php
 *   php tests/fixtures/educacenso/generate_load_test.php --schools=120 --matriculas=1000 --out=custom.txt
 */

declare(strict_types=1);

$opts = getopt('', ['schools::', 'turmas::', 'matriculas::', 'out::', 'help']);

if (isset($opts['help'])) {
    fwrite(STDOUT, "Opções: --schools=100 --turmas=25 --matriculas=600 --out=stage1_2026_load_test.txt\n");
    exit(0);
}

$schools = max(1, (int) ($opts['schools'] ?? 100));
$turmasPerSchool = max(1, (int) ($opts['turmas'] ?? 25));
$matriculasPerSchool = max(1, (int) ($opts['matriculas'] ?? 600));
$outName = (string) ($opts['out'] ?? 'stage1_2026_load_test.txt');
$outPath = __DIR__.'/'.$outName;

$handle = fopen($outPath, 'wb');
if ($handle === false) {
    fwrite(STDERR, "Não foi possível criar {$outPath}\n");
    exit(1);
}

$lines = 0;
$year = 2026;

for ($s = 1; $s <= $schools; $s++) {
    $inep = str_pad((string) (10_000_000 + $s), 8, '0', STR_PAD_LEFT);
    $schoolName = 'Escola Municipal Simulada '.$s;

    fwrite($handle, "00|{$inep}|{$year}|{$schoolName}\n");
    $lines++;

    for ($t = 1; $t <= $turmasPerSchool; $t++) {
        fwrite($handle, "20|{$inep}|{$t}|Turma {$t}\n");
        $lines++;
    }

    for ($m = 1; $m <= $matriculasPerSchool; $m++) {
        $cpf = str_pad((string) ($s * 1_000_000 + $m), 11, '0', STR_PAD_LEFT);
        $turma = (($m - 1) % $turmasPerSchool) + 1;

        fwrite($handle, "30|{$inep}|{$cpf}|Aluno {$s}-{$m}\n");
        $lines++;

        fwrite($handle, "60|{$inep}|{$cpf}|{$turma}\n");
        $lines++;
    }
}

fclose($handle);

$size = filesize($outPath);
$sizeMb = $size !== false ? round($size / 1024 / 1024, 2) : 0;

fwrite(STDOUT, "Gerado: {$outPath}\n");
fwrite(STDOUT, "Escolas: {$schools} · Turmas/escola: {$turmasPerSchool} · Matrículas/escola: {$matriculasPerSchool}\n");
fwrite(STDOUT, "Linhas: ".number_format($lines).' · Tamanho: '.$sizeMb." MB\n");
