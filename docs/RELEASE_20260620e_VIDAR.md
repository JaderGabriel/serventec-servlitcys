# Release `20260620e-Vidar` — ServLITCYS 5.7.4

**Data:** 2026-06-20 · **Ramo:** `main` · **Minor:** **5.7.4** sobre **5.7.3** (Bragi).

> Sexta release do dia 20/06 — sufixo **`e`**. Anterior: [RELEASE_20260620d_BRAGI.md](RELEASE_20260620d_BRAGI.md).

**Vidar** (mitologia nórdica): deus da perseverança e sobrevivência — alinhado ao sync BR resistente a TERM/logout SSH.

---

## Resumo

1. **Sync BR — flag `wanted`** — `start` marca intenção de correr; `stop` remove; runner não termina em TERM acidental se wanted activo.
2. **Runner** — ignora HUP/TERM/INT; reinicia `continue` após sinal ou falha; `setsid` no filho.
3. **Comando `ensure`** — reinicia screen se wanted activo mas sessão morta (cron `*/5`).
4. **Docs** — `loginctl enable-linger serventec` obrigatório em produção; diagnóstico melhorado em `status`.

---

## Deploy

```bash
git fetch --tags && git checkout 20260620e-Vidar

# Uma vez no servidor (user serventec):
sudo loginctl enable-linger serventec

./scripts/horizonte-sync-br-screen.sh start
./scripts/horizonte-sync-br-screen.sh status
```

**Cron opcional:**

```cron
*/5 * * * * cd /home/serventec/analise.serventecassessoria.com.br && ./scripts/horizonte-sync-br-screen.sh ensure >> storage/logs/horizonte-sync-br-ensure.log 2>&1
```

---

## Referências

| Tema | Doc |
|------|-----|
| Sync screen | [HORIZONTE.md](HORIZONTE.md) §9.1b |
| Anterior | [RELEASE_20260620d_BRAGI.md](RELEASE_20260620d_BRAGI.md) |
