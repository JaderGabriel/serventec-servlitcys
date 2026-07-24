<?php

namespace Tests\Unit;

use App\Support\Filesystem\AppTemp;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AppTempAndTmpPurgeTest extends TestCase
{
    #[Test]
    public function cria_ficheiros_no_temp_da_app_e_nao_no_tmp_do_so(): void
    {
        $root = storage_path('app/tmp');
        config(['tmp.path' => $root]);

        $file = AppTemp::tempnam('unit_', 'test');
        $this->assertStringStartsWith($root.DIRECTORY_SEPARATOR, $file);
        $this->assertFileExists($file);
        $this->assertSame(realpath($root), realpath(dirname(dirname($file))) ?: realpath(dirname($file)));

        $dir = AppTemp::directory('unit_dir_', 'test');
        $this->assertDirectoryExists($dir);
        $this->assertStringStartsWith($root.DIRECTORY_SEPARATOR, $dir);

        @unlink($file);
        @rmdir($dir);
    }

    #[Test]
    public function tmp_purge_remove_ficheiros_antigos_em_dry_run_e_real(): void
    {
        $root = storage_path('app/tmp');
        config([
            'tmp.path' => $root,
            'tmp.retention_hours' => 1,
            'tmp.extra_targets' => [],
        ]);

        AppTemp::ensure('purge');
        $old = AppTemp::path('old-file.txt', 'purge');
        file_put_contents($old, 'x');
        touch($old, time() - 7200);

        $exit = Artisan::call('tmp:purge', ['--hours' => 1, '--only' => 'tmp', '--dry-run' => true]);
        $this->assertSame(0, $exit);
        $this->assertFileExists($old);

        $exit = Artisan::call('tmp:purge', ['--hours' => 1, '--only' => 'tmp']);
        $this->assertSame(0, $exit);
        $this->assertFileDoesNotExist($old);
    }
}
