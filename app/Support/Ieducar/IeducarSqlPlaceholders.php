<?php

namespace App\Support\Ieducar;

use App\Models\City;

/**
 * Substitui placeholders em SQL personalizado (IEDUCAR_SQL_*) para bases iEducar com vários schemas PostgreSQL.
 *
 * Ordem: chaves mais longas primeiro (ex.: {matricula_turma} antes de {matricula}).
 */
final class IeducarSqlPlaceholders
{
    /**
     * @return array<string, string>
     */
    public static function placeholderMap(City $city): array
    {
        return [
            '{matricula_turma}' => IeducarSchema::resolveTable('matricula_turma', $city),
            '{matricula_situacao}' => IeducarSchema::resolveTable('matricula_situacao', $city),
            '{falta_aluno}' => IeducarSchema::resolveTable('falta_aluno', $city),
            '{ano_letivo}' => IeducarSchema::resolveTable('ano_letivo', $city),
            '{matricula}' => IeducarSchema::resolveTable('matricula', $city),
            '{escola}' => IeducarSchema::resolveTable('escola', $city),
            '{curso}' => IeducarSchema::resolveTable('curso', $city),
            '{turno}' => IeducarSchema::resolveTable('turno', $city),
            '{serie}' => IeducarSchema::resolveTable('serie', $city),
            '{turma}' => IeducarSchema::resolveTable('turma', $city),
            '{aluno}' => IeducarSchema::resolveTable('aluno', $city),
            '{pessoa}' => IeducarSchema::resolveTable('pessoa', $city),
            '{raca}' => IeducarSchema::resolveTable('raca', $city),
            '{schema_main}' => IeducarSchema::effectiveSchema($city),
            '{schema}' => IeducarSchema::effectiveSchema($city),
            '{cadastro}' => (string) config('ieducar.pgsql_schema_cadastro', 'cadastro'),
            '{relatorio}' => (string) config('ieducar.pgsql_schema_relatorio', 'relatorio'),
            '{modules}' => (string) config('ieducar.pgsql_schema_modules', 'modules'),
            '{public}' => 'public',
        ];
    }

    public static function interpolate(string $sql, City $city): string
    {
        $map = self::placeholderMap($city);

        return str_replace(array_keys($map), array_values($map), $sql);
    }
}
