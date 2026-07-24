# Testes unitários

- Qualificação, inventário e metas: [docs/QUALIFICACAO_SISTEMA_E_COBERTURA_TESTES.md](../../docs/QUALIFICACAO_SISTEMA_E_COBERTURA_TESTES.md)
- Convenções e mapa histórico: [docs/PLANO_TESTES_UNITARIOS.md](../../docs/PLANO_TESTES_UNITARIOS.md)

## Executar

```bash
composer test
# ou só Unit:
bash scripts/run-tests.sh --testsuite=Unit
```

## Convenção de comentários

Cada método `test_*` ou `#[Test]` deve ter docblock com:

1. **Cenário** — entrada ou estado inicial  
2. **Esperado** — asserção e regra de negócio  
3. **Impacto** — efeito no painel municipal / FUNDEB / Censo (quando aplicável)

## Dependência SQLite

Testes com `RefreshDatabase` requerem `pdo_sqlite` (local: `scripts/php-with-sqlite.sh` / `composer test`).
