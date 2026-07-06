# Release `20260705-Ploutos` — ServLITCYS 7.0.0

**Data:** 2026-07-05 · **Ramo:** `main` · **Marco:** **7.0.0** (major) sobre **6.5.0** (Horizonte territorial).

Codename **Ploutos** (mitologia grega — abundância e finanças públicas): enriquecimento do Horizonte com bases SICONFI, Portal da Transparência, tendência SAEB/Educacenso, CadÚnico aprofundado e novas dimensões de scoring.

---

## Resumo

### Dados públicos e sync

- **SICONFI / RREO** — API Tesouro (`horizonte:sync-siconfi`); tabela `municipal_fiscal_snapshots`; fase `siconfi_sync` no feed bimestral.
- **Portal da Transparência** — convénios MEC/FNDE, empenhos educação/tecnologia, contratos software (`horizonte:sync-transparency`; requer `PORTAL_TRANSPARENCIA_API_KEY`); fase `transparency_sync`.
- **PNAD** — tabela `municipal_pnad_snapshots` (importação SIDRA pendente; UI pronta quando houver dados).

### Scoring e mapa

- Dimensões novas: `fiscal_capacity`, `learning_trajectory`, `enrollment_momentum`, `inclusion_gap`.
- Pesos actualizados em `config/horizonte.php`.
- SAEB: série até **4 ciclos** com tendência ↑↓ no cabeçalho do modal.

### Modal municipal

- Secções **Finanças**, **Transparência**, **Pedagogia e escala**, **Social e demanda**.
- Educacenso: aluno/docente, % integral/profissional, dependência administrativa.
- CadÚnico: estimativa crianças 0–17 fora da escola.

---

## Deploy

```bash
git pull origin main
git checkout 20260705-Ploutos   # tag de deploy (opcional)
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

**Pós-deploy — enriquecimento fiscal e transparência:**

```bash
# SICONFI — 1 UF por execução (ordem DF→MG); repetir até cobertura nacional
php artisan horizonte:sync-siconfi --reset --continue   # 1ª UF = DF
php artisan horizonte:sync-siconfi --continue           # próximas UFs
php artisan horizonte:warm-map-cache

# Portal da Transparência (definir PORTAL_TRANSPARENCIA_API_KEY no .env)
php artisan horizonte:sync-transparency --limit=5

# Ou via feed bimestral:
php artisan horizonte:fortnightly-feed --phase=siconfi_sync
php artisan horizonte:fortnightly-feed --phase=transparency_sync
```

---

## Testes

```bash
php artisan test --filter='SiconfiRreoParser|HorizonteSaebTrend|HorizonteOpportunityScorer|HorizonteFortnightlyFeed'
```

---

## Referências

- [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md)
- [HORIZONTE.md](HORIZONTE.md) §6.11, §9.1, §9.2
- [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md) §3.2b
- [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md) §11b
