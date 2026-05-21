<?php

namespace App\Support\Ieducar;

use App\Models\City;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;

/**
 * Resolve a tabela de utilizadores do i-Educar e filtros para excluir perfis administrativos.
 */
final class IeducarUsuarioScope
{
    /**
     * @return ?array{
     *   table: string,
     *   id_col: string,
     *   login_col: ?string,
     *   name_col: ?string,
     *   nivel_col: ?string
     * }
     */
    public static function resolve(Connection $db, City $city): ?array
    {
        $table = IeducarColumnInspector::findQualifiedTableByNames($db, ['usuario', 'usuarios'], $city);
        if ($table === null) {
            return null;
        }

        $idCol = IeducarColumnInspector::firstExistingColumn($db, $table, [
            'cod_usuario',
            'id',
            'id_usuario',
        ], $city);
        if ($idCol === null) {
            return null;
        }

        return [
            'table' => $table,
            'id_col' => $idCol,
            'login_col' => IeducarColumnInspector::firstExistingColumn($db, $table, ['login', 'usuario', 'user_login'], $city),
            'name_col' => IeducarColumnInspector::firstExistingColumn($db, $table, ['nome', 'nm_usuario', 'name'], $city),
            'nivel_col' => IeducarColumnInspector::firstExistingColumn($db, $table, ['nivel', 'ref_cod_tipo_usuario', 'tipo_usuario'], $city),
        ];
    }

    /**
     * @param  array{
     *   table: string,
     *   id_col: string,
     *   login_col: ?string,
     *   name_col: ?string,
     *   nivel_col: ?string
     * }  $usuario
     */
    public static function applyExclusions(Builder $q, array $usuario, string $alias = 'u'): void
    {
        $idCol = $usuario['id_col'];
        $excludeIds = config('ieducar.work_tracking.exclude_usuario_ids', []);
        if (is_array($excludeIds) && $excludeIds !== []) {
            $q->whereNotIn($alias.'.'.$idCol, array_map('intval', $excludeIds));
        }

        $excludeNivel = config('ieducar.work_tracking.exclude_nivel_usuario', []);
        if (
            is_array($excludeNivel)
            && $excludeNivel !== []
            && filled($usuario['nivel_col'] ?? null)
        ) {
            $q->whereNotIn($alias.'.'.$usuario['nivel_col'], array_map('intval', $excludeNivel));
        }

        $patterns = config('ieducar.work_tracking.exclude_login_patterns', []);
        if (
            is_array($patterns)
            && $patterns !== []
            && filled($usuario['login_col'] ?? null)
        ) {
            $loginCol = $alias.'.'.$usuario['login_col'];
            $q->where(function (Builder $inner) use ($loginCol, $patterns): void {
                foreach ($patterns as $pattern) {
                    $p = strtolower(trim((string) $pattern));
                    if ($p === '') {
                        continue;
                    }
                    $inner->whereRaw('LOWER(COALESCE('.$loginCol.', \'\')) NOT LIKE ?', ['%'.$p.'%']);
                }
            });
        }
    }

    /**
     * @return list<string>
     */
    public static function exclusionLabels(): array
    {
        $labels = [];
        $patterns = config('ieducar.work_tracking.exclude_login_patterns', []);
        if (is_array($patterns) && $patterns !== []) {
            $labels[] = __('Logins contendo: :p', ['p' => implode(', ', $patterns)]);
        }
        $ids = config('ieducar.work_tracking.exclude_usuario_ids', []);
        if (is_array($ids) && $ids !== []) {
            $labels[] = __('IDs de utilizador excluídos: :ids', ['ids' => implode(', ', $ids)]);
        }
        $niveis = config('ieducar.work_tracking.exclude_nivel_usuario', []);
        if (is_array($niveis) && $niveis !== []) {
            $labels[] = __('Níveis/tipos excluídos: :n', ['n' => implode(', ', $niveis)]);
        }

        return $labels;
    }
}
