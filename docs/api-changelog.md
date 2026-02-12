# API Changelog

## 2026-02-12

### Breaking changes

- Prefixo versionado aplicado: endpoints migraram de `/api/*` para `/api/v1/*`.
- Recurso `eleicoes` removido da API (não existe tabela `eleicao` nas migrations atuais).

### Contract updates

- `mandatos`:
  - removidos campos `eleicao_id`, `inicio`, `fim`, `situacao`.
  - adicionados/normalizados campos `ano_eleicao`, `cd_eleicao`, `dt_eleicao`, `nr_turno`, `nr_candidato`, `mandato_inicio`, `mandato_fim`.
  - filtro `vigente_hoje=1` passou a ser suportado no backend.
- `municipio-demografias`:
  - modelo/tabela normalizado para `demografia_municipio`.
- `prefeitos`:
  - campo de data normalizado para `dt_nascimento`.
  - removidos campos legados não mapeados no schema (`chave`, `cpf_hash`).

### Frontend sync

- `VITE_API_URL` atualizado para `/api/v1`.
- Página e hooks de `eleicoes` removidos.
- Telas de mandatos/prefeitos/municipios ajustadas para os novos campos.
