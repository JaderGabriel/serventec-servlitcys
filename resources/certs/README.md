# Certificados TLS — download INEP (SAEB)

O host `download.inep.gov.br` usa cadeia **RNP ICPEdu** que pode não existir ainda no `ca-certificates` do servidor (erro cURL 60).

- `inep-download-chain.pem` — cadeia servida pelo INEP (usada automaticamente nas importações).
- Actualizar: `php artisan saeb:refresh-ca-bundle` (grava também `storage/app/certs/saeb-ca-bundle.pem`).
