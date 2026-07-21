<?php

namespace App\Support\Admin;

/**
 * Identidade visual das seções do menu lateral da documentação (ícone, cor, analogia).
 */
final class DocumentationSectionVisuals
{
    /**
     * @param  list<array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    public static function apply(array $sections): array
    {
        return array_map(static function (array $section): array {
            $key = (string) ($section['key'] ?? self::keyFromTitle((string) ($section['title'] ?? '')));
            $visual = self::forKey($key);

            return array_merge($section, [
                'key' => $key,
                'icon' => $section['icon'] ?? $visual['icon'],
                'tone' => $section['tone'] ?? $visual['tone'],
                'analogy' => $section['analogy'] ?? $visual['analogy'],
            ]);
        }, $sections);
    }

    /**
     * @return array{icon: string, tone: string, analogy: string}
     */
    public static function forKey(string $key): array
    {
        return self::catalog()[$key] ?? [
            'icon' => 'document-text',
            'tone' => 'slate',
            'analogy' => __('Documentação geral'),
        ];
    }

    /**
     * @return array<string, array{icon: string, tone: string, analogy: string}>
     */
    public static function catalog(): array
    {
        return [
            'entry' => [
                'icon' => 'home',
                'tone' => 'blue',
                'analogy' => __('Porta de entrada — versão, perfis e hub'),
            ],
            'architecture' => [
                'icon' => 'squares-2x2',
                'tone' => 'sky',
                'analogy' => __('Planta do sistema — camadas e decisões'),
            ],
            'modulos' => [
                'icon' => 'squares-2x2',
                'tone' => 'indigo',
                'analogy' => __('Mapa modular — espelha o menu da app'),
            ],
            'analytics' => [
                'icon' => 'chart-bar',
                'tone' => 'blue',
                'analogy' => __('Painel analítico — cinco áreas'),
            ],
            'horizonte' => [
                'icon' => 'globe-alt',
                'tone' => 'emerald',
                'analogy' => __('Mapa GIS — oportunidade municipal'),
            ],
            'cadunico' => [
                'icon' => 'users',
                'tone' => 'violet',
                'analogy' => __('Cadastro × CadÚnico — inclusão'),
            ],
            'pedagogia' => [
                'icon' => 'academic-cap',
                'tone' => 'sky',
                'analogy' => __('SAEB / IDEB — pedagogia'),
            ],
            'rx' => [
                'icon' => 'clipboard-document-list',
                'tone' => 'amber',
                'analogy' => __('RX — Educacenso e ritmo'),
            ],
            'clio' => [
                'icon' => 'academic-cap',
                'tone' => 'sky',
                'analogy' => __('Clio — coletas CSV 1ª etapa'),
            ],
            'funding' => [
                'icon' => 'banknotes',
                'tone' => 'rose',
                'analogy' => __('Fluxo FUNDEB — VAAF e repasses'),
            ],
            'integrations' => [
                'icon' => 'globe-alt',
                'tone' => 'violet',
                'analogy' => __('Pontes externas — importações e APIs'),
            ],
            'operations' => [
                'icon' => 'command-line',
                'tone' => 'amber',
                'analogy' => __('Sala de máquinas — deploy, CLI e testes'),
            ],
            'escalonadas' => [
                'icon' => 'arrow-path',
                'tone' => 'fuchsia',
                'analogy' => __('Linha do tempo — entregas mensais'),
            ],
            'archive' => [
                'icon' => 'clipboard-document-list',
                'tone' => 'slate',
                'analogy' => __('Arquivo — notas executivas antigas'),
            ],
            'outros' => [
                'icon' => 'document-text',
                'tone' => 'emerald',
                'analogy' => __('Acervo extra — releases e descobertas'),
            ],
        ];
    }

    private static function keyFromTitle(string $title): string
    {
        if (preg_match('/^1\b/u', $title)) {
            return 'entry';
        }
        if (preg_match('/^2\b/u', $title)) {
            return 'architecture';
        }
        if (preg_match('/^3\b/u', $title)) {
            return 'modulos';
        }
        if (preg_match('/^4\b/u', $title)) {
            return 'analytics';
        }
        if (preg_match('/^5\b/u', $title)) {
            return 'horizonte';
        }
        if (preg_match('/^6\b/u', $title)) {
            return 'cadunico';
        }
        if (preg_match('/^7\b/u', $title)) {
            return 'pedagogia';
        }
        if (preg_match('/^8\b/u', $title)) {
            return 'rx';
        }
        if (preg_match('/^9\b/u', $title)) {
            return 'funding';
        }
        if (preg_match('/^10\b/u', $title)) {
            return 'integrations';
        }
        if (preg_match('/^11\b/u', $title)) {
            return 'operations';
        }
        if (str_contains(mb_strtolower($title), 'escalonad')) {
            return 'escalonadas';
        }
        if (str_contains(mb_strtolower($title), 'arquivo')) {
            return 'archive';
        }
        if (str_contains(mb_strtolower($title), 'outros')) {
            return 'outros';
        }

        return 'outros';
    }
}
