# Mapa de Dados: Demografia e Eleitoral

## Demografia (fonte real)
- Tabela: `demografia_municipio`
- Colunas principais:
  - `id`
  - `municipio_id` (FK -> `municipio.id`)
  - `ano_ref` (série histórica anual)
  - `populacao`
  - `eleitores`
- Relacionamentos:
  - `demografia_municipio` N:1 `municipio`
  - `municipio` N:1 `regiao_integracao`

## Eleitoral (fonte real)
- Tabela principal: `mandato_prefeito`
- Colunas principais:
  - `id`
  - `municipio_id` (FK -> `municipio.id`)
  - `prefeito_id` (FK -> `prefeito.id`)
  - `partido_id` (FK -> `partido.id`, nullable)
  - `ano_eleicao`
  - `cd_eleicao`
  - `dt_eleicao`
  - `nr_turno`
  - `nr_candidato`
  - `mandato_inicio`
  - `mandato_fim`
  - `mandato_consecutivo`
  - `reeleito`
- Relacionamentos:
  - `mandato_prefeito` N:1 `municipio`
  - `mandato_prefeito` N:1 `prefeito`
  - `mandato_prefeito` N:1 `partido`

## Dimensões de filtro
- `municipio` (`id`, `nome`, `uf`, `regiao_id`)
- `regiao_integracao` (`id`, `descricao`)
- `partido` (`id`, `sigla`, `nome`)

## Endpoints unificados implementados

### Demografia
- `GET /api/v1/demografia`
  - Filtros: `q`, `municipio_id`, `regiao_integracao_id`, `ano`, `ano_de`, `ano_ate`
  - Paginação: `page`, `per_page`
  - Ordenação: `orderBy`, `direction`
  - Retorna: `results`, `pagination`, `meta.kpis`, `meta.series`
- `GET /api/v1/demografia/municipios/{municipioId}`
  - Retorna município + série histórica + indicadores.
- `GET /api/v1/demografia/lookups`
  - Retorna `municipios`, `regioes`, `anos`.

### Eleitoral
- `GET /api/v1/eleitoral`
  - Filtros: `q`, `municipio_id`, `ano_eleicao`, `partido_id`, `vigente_hoje`
  - Paginação: `page`, `per_page`
  - Ordenação: `orderBy`, `direction`
  - Retorna: `results`, `pagination`, `meta.kpis`, `meta.distribuicao_partidos`
- `GET /api/v1/eleitoral/municipios/{municipioId}`
  - Retorna município + anos disponíveis + mandatos + KPIs.
- `GET /api/v1/eleitoral/lookups`
  - Retorna `municipios`, `partidos`, `anos`.

## Observações
- Não há tabela de resultados eleitorais detalhados por candidato/voto no schema atual.
- Não há tabela `eleicoes` dedicada; o eixo temporal eleitoral vem de `mandato_prefeito.ano_eleicao/cd_eleicao`.
- Front deve usar IDs reais (`municipio.id`, `partido.id`) e não códigos legacy.
