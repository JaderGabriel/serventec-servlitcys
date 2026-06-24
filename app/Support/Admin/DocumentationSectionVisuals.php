<?php

namespace App\Support\Admin;

/**
 * Identidade visual das secções do menu lateral da documentação (ícone, cor, analogia).
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
                'tone' => 'teal',
                'analogy' => __('Porta de entrada — versão, perfis e hub'),
            ],
            'architecture' => [
                'icon' => 'squares-2x2',
                'tone' => 'indigo',
                'analogy' => __('Planta do sistema — camadas e decisões'),
            ],
            'consultoria' => [
                'icon' => 'chart-bar',
                'tone' => 'sky',
                'analogy' => __('Painel municipal — analytics e Horizonte'),
            ],
            'funding' => [
                'icon' => 'banknotes',
                'tone' => 'rose',
                'analogy' => __('Fluxo FUNDEB — VAAF, repasses e extratos'),
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
            return 'consultoria';
        }
        if (preg_match('/^4\b/u', $title)) {
            return 'funding';
        }
        if (preg_match('/^5\b/u', $title)) {
            return 'integrations';
        }
        if (preg_match('/^6\b/u', $title)) {
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
