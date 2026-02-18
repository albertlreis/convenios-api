# Convênios Unificados: Mapa de Dados e Filtros

## Fonte de verdade (schema)

### Tabela `convenio`
- PK: `id`
- FKs: `orgao_id`, `municipio_beneficiario_id`, `convenente_municipio_id`
- Campos de identificação: `numero_convenio`, `codigo`
- Campos de negócio: `objeto`, `grupo_despesa`, `data_inicio`, `data_fim`
- Campos financeiros: `valor_orgao`, `valor_contrapartida`, `valor_aditivo`, `valor_total_informado`, `valor_total_calculado`
- Import tolerante: `orgao_nome_informado`, `municipio_beneficiario_nome_informado`, `convenente_municipio_nome_informado`, `ano_referencia`, `quantidade_parcelas_informada`, `dados_origem`
- Índices principais: `orgao_id`, `municipio_beneficiario_id`, `data_inicio`, `data_fim`, `convenio_orgao_municipio_index`

### Tabela `parcela`
- PK: `id`
- FK: `convenio_id`
- Campos: `numero`, `valor_previsto`, `valor_pago`, `data_pagamento`, `nota_empenho`, `data_ne`, `valor_empenhado`, `situacao`, `observacoes`, `dados_origem`
- Enum `situacao`: `PREVISTA | PAGA | CANCELADA`
- Índices principais: `convenio_id`, `situacao`, `parcela_convenio_situacao_data_index`

### Tabela `convenio_plano_interno`
- PK: `id`
- FK: `convenio_id`
- Campos: `plano_interno`, `origem`
- Índices principais: `convenio_pi_unique (convenio_id, plano_interno)`, `convenio_pi_plano_interno_index`

### Tabelas de lookup
- `orgao` (`id`, `sigla`, `nome`)
- `municipio` (`id`, `nome`, `uf`, `regiao_id`)
- `regiao_integracao` (via `municipio.regiao_id`)

## Relações
- `convenio` 1:N `parcela`
- `convenio` N:1 `orgao`
- `convenio` N:1 `municipio` (beneficiário e convenente)
- `convenio` 1:N `convenio_plano_interno`

## Gargalos e mitigação
- Agregados de parcelas por convênio são feitos por subselect em `scopeWithParcelasAgg` (evita N+1 de leitura no front).
- Filtros textuais (`q`) fazem join com `orgao`/`municipio` + `exists` em PI.
- Filtros de valores em aberto usam colunas agregadas (`having`), evitando pós-processamento no front.

## Contrato final de API usado pela UI unificada

### `GET /api/v1/convenios`
Retorna:
- `data.results[]`: convênios com relações e agregados de parcelas
- `data.pagination`: `page`, `perPage`, `total`, `lastPage`
- `data.meta`: ordenação aplicada

### `GET /api/v1/convenios/{id}`
Retorna:
- `data.convenio`: dados completos do convênio
- `data.agregados`: resumo financeiro (total/pago/aberto/%)
- `data.parcelas.results[]`: parcelas paginadas
- `data.parcelas.pagination`

### `GET /api/v1/convenios/filtros`
Retorna listas para selects:
- `orgaos[]`, `municipios[]`, `planos_internos[]`

## Filtros suportados e tradução de query
- `q`: busca textual em `numero_convenio`, `codigo`, `objeto`, `municipio.nome`, `orgao.sigla/nome`, `convenio_plano_interno.plano_interno`
- `municipio_id`: `convenio.municipio_beneficiario_id`
- `orgao_id`: `convenio.orgao_id`
- `situacao_financeira=EM_ABERTO|QUITADO`: existência de parcelas em aberto
- `com_parcelas_em_aberto=0|1`: bool legado equivalente
- `vigencia_de`, `vigencia_ate`: recorte por janela de vigência (`data_inicio/data_fim`)
- `data_pagamento_de`, `data_pagamento_ate`: recorte por período de pagamento de parcelas
- `pi[]=...`, `pi_codigo`, `plano_interno`: filtro por plano interno
- `valor_total_min/max`: em `COALESCE(valor_total_calculado, valor_total_informado)`
- `valor_em_aberto_min/max`: em agregado `valor_em_aberto_total`
- `orderBy`, `direction` e legado `sort`

## Limitações reais do schema
- Não existe campo de vencimento da parcela; por isso não há filtro nativo de `vencimento_de/vencimento_ate`.
- Não existe coluna explícita de status de convênio (ativo/concluído/cancelado). A UI usa situação financeira derivada das parcelas.
