<?php

namespace App\Console\Commands;

use App\Support\Product\ProductReleasePublisher;
use Illuminate\Console\Command;

class ProductReleasePublishCommand extends Command
{
    protected $signature = 'product:release-publish
                            {tag : Tag YYYYMMDD[-letra]-Codename}
                            {--version= : Versão MAJOR.VERSÃO.MINOR (default: config/documentation.php)}
                            {--title= : Título do GitHub Release}
                            {--dry-run : Simula sem criar tag nem GitHub Release}
                            {--no-push : Não executa git push origin TAG}
                            {--no-github : Não cria GitHub Release (apenas tag Git)}';

    protected $description = 'Publica tag Git e GitHub Release alinhados à nota RELEASE_*.md';

    public function handle(ProductReleasePublisher $publisher): int
    {
        $tag = trim((string) $this->argument('tag'));
        $version = trim((string) ($this->option('version') ?: config('documentation.product.version', '')));

        if ($version === '') {
            $this->error('Informe --version= ou defina documentation.product.version.');

            return self::FAILURE;
        }

        try {
            $parsed = $publisher->parseTag($tag);
            $notesPath = $publisher->releaseNotesPath($tag);
            $publisher->assertReleaseNotesExist($notesPath);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $mismatches = $publisher->configMismatches($tag, $version);
        if ($mismatches !== []) {
            $this->error('config/documentation.php desalinhado:');
            foreach ($mismatches as $line) {
                $this->line('  · '.$line);
            }
            $this->newLine();
            $this->line('Checklist: docs/RELEASE_PUBLICACAO.md');

            return self::FAILURE;
        }

        $title = trim((string) $this->option('title'));
        if ($title === '') {
            $title = $publisher->defaultReleaseTitle($version, $tag);
        }

        $message = $title.' · commit '.$publisher->headShortHash();

        $this->info('Release pronta para publicar');
        $this->table(
            ['Campo', 'Valor'],
            [
                ['Versão', $version],
                ['Tag', $tag],
                ['Codename', $parsed['codename']],
                ['Notas', $notesPath],
                ['Título GitHub', $title],
                ['HEAD', $publisher->headShortHash().' (#'.$publisher->commitCount().')'],
            ],
        );

        if ($this->option('dry-run')) {
            $this->warn('Dry-run — nenhuma tag nem GitHub Release criada.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Criar tag Git'.($this->option('no-github') ? '' : ' + GitHub Release').'?', true)) {
            $this->warn('Cancelado.');

            return self::SUCCESS;
        }

        try {
            $publisher->createAnnotatedTag($tag, $message);

            if (! $this->option('no-push')) {
                $publisher->pushTag($tag);
            }

            if (! $this->option('no-github')) {
                $publisher->createGitHubRelease($tag, $title, $notesPath);
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Publicado: '.$tag);
        if (! $this->option('no-github')) {
            $this->line('GitHub: gh release view '.$tag);
        }

        return self::SUCCESS;
    }
}
