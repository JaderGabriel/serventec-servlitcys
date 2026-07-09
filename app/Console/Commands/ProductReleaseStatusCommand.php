<?php

namespace App\Console\Commands;

use App\Support\Product\ProductReleasePublisher;
use Illuminate\Console\Command;

class ProductReleaseStatusCommand extends Command
{
    protected $signature = 'product:release-status
                            {tag? : Tag a verificar (default: config/documentation.php)}
                            {--product-version= : Versão esperada (default: config)}';

    protected $description = 'Verifica alinhamento entre config, RELEASE_*.md, tag Git e GitHub Release';

    public function handle(ProductReleasePublisher $publisher): int
    {
        $tag = trim((string) ($this->argument('tag') ?: config('documentation.product.release_tag', '')));
        $version = trim((string) ($this->option('product-version') ?: config('documentation.product.version', '')));

        if ($tag === '' || $version === '') {
            $this->error('Tag ou versão indefinidas.');

            return self::FAILURE;
        }

        try {
            $status = $publisher->status($tag, $version);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Verificação', 'Estado'],
            [
                ['RELEASE_*.md', $status['notes_exist'] ? 'OK · '.$status['notes'] : 'AUSENTE'],
                ['config/documentation.php', $status['config_ok'] ? 'OK' : 'DESALINHADO'],
                ['Tag Git (local)', $status['tag_local'] ? 'sim' : 'não'],
                ['Tag Git (origin)', $status['tag_remote'] ? 'sim' : 'não'],
                ['GitHub Release', $status['gh_release'] ? 'sim' : 'não'],
            ],
        );

        if ($status['mismatches'] !== []) {
            $this->newLine();
            $this->warn('Diferenças em config:');
            foreach ($status['mismatches'] as $line) {
                $this->line('  · '.$line);
            }
        }

        $ready = $status['notes_exist']
            && $status['config_ok']
            && ! $status['tag_local'];

        if ($ready) {
            $this->newLine();
            $this->info('Pronto para: php artisan product:release-publish '.$tag.' --product-version='.$version);
        }

        return self::SUCCESS;
    }
}
