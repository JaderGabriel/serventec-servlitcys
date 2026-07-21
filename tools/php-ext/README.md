# Extensões PHP locais (testes)

Os ficheiros `*.so` aqui **não** entram no Git (ver `.gitignore`).

São usados por `scripts/php-with-sqlite.sh` para carregar `pdo_sqlite` sem `apt install` root.

Obter automaticamente:

```bash
./scripts/php-with-sqlite.sh -r 'echo extension_loaded("pdo_sqlite")?"ok":"fail";'
```

Ou instalar no sistema: `php8.4-sqlite3`.
