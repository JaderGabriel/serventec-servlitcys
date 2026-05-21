<?php

namespace App\Console\Commands;

use App\Services\Inep\SaebInepDownloadCaBundle;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('saeb:refresh-ca-bundle
    {--host=download.inep.gov.br : Host INEP para obter a cadeia TLS}')]
#[Description('Actualiza o bundle CA para descarregar microdados SAEB do INEP (cURL 60 / RNP).')]
class SaebRefreshCaBundleCommand extends Command
{
    public function handle(): int
    {
        $host = trim((string) $this->option('host'));
        if ($host === '') {
            $this->error(__('Host inválido.'));

            return self::FAILURE;
        }

        try {
            $path = SaebInepDownloadCaBundle::refreshFromHost($host);
            $this->info(__('Bundle gravado em :path', ['path' => $path]));
            $this->line(__('Defina no .env (opcional): IEDUCAR_SAEB_HTTP_CA_BUNDLE=:path', ['path' => $path]));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            $this->line(SaebInepDownloadCaBundle::sslFailureHint());

            return self::FAILURE;
        }
    }
}
