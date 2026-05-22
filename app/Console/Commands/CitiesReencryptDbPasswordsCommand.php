<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Services\CityDataConnection;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;

#[Signature('cities:reencrypt-db-passwords
    {--password= : Senha padrão do banco i-Educar (aplicada a todas as cidades cadastradas)}
    {--probe : Testa a conexão PDO após gravar cada cidade com host/base/usuário}
    {--dry-run : Lista as cidades que seriam atualizadas, sem gravar}
    {--confirm= : Em production, passe reencrypt-db-passwords para confirmar}')]
#[Description('Regrava db_password de todas as cidades com a APP_KEY actual e uma senha padrão')]
class CitiesReencryptDbPasswordsCommand extends Command
{
    public function handle(CityDataConnection $cityData): int
    {
        if (! filled(config('app.key'))) {
            $this->error(__('APP_KEY está vazia. Defina APP_KEY no .env antes de regravar senhas.'));

            return self::FAILURE;
        }

        $password = $this->resolvePassword();
        if ($password === null) {
            return self::FAILURE;
        }

        $cities = City::query()->orderBy('name')->orderBy('uf')->get();
        if ($cities->isEmpty()) {
            $this->warn(__('Nenhuma cidade cadastrada na base da aplicação.'));

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $probe = (bool) $this->option('probe');

        if (app()->environment('production') && ! $dryRun) {
            $required = 'reencrypt-db-passwords';
            $confirm = trim((string) $this->option('confirm'));
            if ($confirm === '' || ! hash_equals($required, $confirm)) {
                $this->error(__('Em production use:'));
                $this->line('  php artisan cities:reencrypt-db-passwords --password=... --confirm='.$required);

                return self::FAILURE;
            }
        } elseif (! $dryRun && trim((string) $this->option('confirm')) === '') {
            $this->warn(__('Será aplicada a mesma senha a :n cidade(s).', ['n' => $cities->count()]));
            if (! $this->confirm(__('Continuar?'), false)) {
                $this->comment(__('Operação cancelada.'));

                return self::SUCCESS;
            }
        }

        $this->info($dryRun
            ? __('Simulação — nenhuma senha será gravada (:n cidade(s)).', ['n' => $cities->count()])
            : __('A regravar senha em :n cidade(s) com a APP_KEY actual…', ['n' => $cities->count()]));
        $this->newLine();

        $updated = 0;
        $failed = 0;

        foreach ($cities as $index => $city) {
            $lineNo = $index + 1;

            if ($dryRun) {
                $setup = $city->hasDataSetup() ? __('com conexão') : __('sem host/base/usuário');
                $this->line(__('[:line] :name (:uf) id :id — :setup', [
                    'line' => $lineNo,
                    'name' => $city->name,
                    'uf' => $city->uf,
                    'id' => $city->id,
                    'setup' => $setup,
                ]));

                continue;
            }

            $city->db_password = $password;
            $city->save();

            try {
                $city->refresh();
                $plain = $city->db_password;
                if (! is_string($plain) || $plain === '') {
                    throw new DecryptException('empty');
                }
            } catch (DecryptException) {
                $failed++;
                $this->error(__('[:line] :name — falhou verificação de descriptografia após gravar.', [
                    'line' => $lineNo,
                    'name' => $city->name,
                ]));

                continue;
            }

            $updated++;
            $msg = __('[:line] :name (:uf) id :id — senha regravada.', [
                'line' => $lineNo,
                'name' => $city->name,
                'uf' => $city->uf,
                'id' => $city->id,
            ]);

            if ($probe && $city->hasDataSetup()) {
                $status = $cityData->connectionStatus($city);
                if ($status['status'] === 'ok' || $status['status'] === 'slow') {
                    $msg .= ' '.__('Conexão: :st (:ms ms).', [
                        'st' => $status['status'],
                        'ms' => $status['ms'] ?? '—',
                    ]);
                } else {
                    $msg .= ' '.__('Conexão falhou: :err', [
                        'err' => $status['message'] ?? __('erro desconhecido'),
                    ]);
                    $failed++;
                    $updated--;
                }
            }

            $this->info($msg);
        }

        $this->newLine();
        if ($dryRun) {
            $this->comment(__('Dry-run concluído. Execute com --password=... para gravar.'));
        } else {
            $this->info(__('Concluído: :ok atualizada(s), :fail falha(s).', ['ok' => $updated, 'fail' => $failed]));
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolvePassword(): ?string
    {
        $fromOption = (string) $this->option('password');
        if ($fromOption !== '') {
            return $fromOption;
        }

        $fromEnv = trim((string) env('CITIES_DB_DEFAULT_PASSWORD', ''));
        if ($fromEnv !== '') {
            $this->comment(__('Senha lida de CITIES_DB_DEFAULT_PASSWORD no .env.'));

            return $fromEnv;
        }

        if ($this->input->isInteractive()) {
            $secret = $this->secret(__('Senha padrão do banco i-Educar (todas as cidades):'));
            if (is_string($secret) && $secret !== '') {
                return $secret;
            }
        }

        $this->error(__('Informe a senha com --password=... ou defina CITIES_DB_DEFAULT_PASSWORD no .env (apenas para este comando).'));

        return null;
    }
}
