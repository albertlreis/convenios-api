# Endpoint x Schema Audit

## Escopo

- Prefixo da API: `/api/v1`
- Fonte de verdade do schema: migrations + `docs/schema-map.json`
- Auditoria feita contra rotas em `docs/routes.json`

## Matriz Route -> Controller -> Model -> Tabela/Colunas

| Method | Route | Controller@Action | Models principais | Tabelas e colunas usadas |
| --- | --- | --- | --- | --- |
| GET | `/api/v1/health` | closure | - | - |
| GET | `/api/v1/convenios` | `ConvenioController@index` | `Convenio`, `Parcela`, `Municipio`, `Orgao`, `MandatoPrefeito`, `MunicipioDemografia` | `convenio.*`; joins `municipio(id,nome,regiao_id)`, `orgao(id,sigla,deleted_at)`, `mandato_prefeito(municipio_id,prefeito_id,partido_id,mandato_consecutivo,mandato_inicio,mandato_fim)`, `prefeito(nome_completo)`, `partido(sigla)`, `demografia_municipio(municipio_id,ano_ref,populacao,eleitores)`; subqueries `parcela(convenio_id,situacao,valor_previsto,valor_pago,data_pagamento,deleted_at)` |
| POST | `/api/v1/convenios` | `ConvenioController@store` | `Convenio` | insert em `convenio(orgao_id,numero_convenio,codigo,municipio_beneficiario_id,convenente_nome,convenente_municipio_id,plano_interno,objeto,grupo_despesa,data_inicio,data_fim,valor_orgao,valor_contrapartida,valor_aditivo,valor_total_informado,valor_total_calculado,metadata)` |
| GET | `/api/v1/convenios/{convenio}` | `ConvenioController@show` | `Convenio`, `Parcela` | leitura `convenio.*` + agregados de `parcela` |
| PATCH/PUT | `/api/v1/convenios/{convenio}` | `ConvenioController@update` | `Convenio` | update das mesmas colunas do store |
| DELETE | `/api/v1/convenios/{convenio}` | `ConvenioController@destroy` | `Convenio` | soft delete em `convenio.deleted_at` |
| GET | `/api/v1/convenios/{convenio}/parcelas` | `ConvenioController@parcelas` | `Parcela` | `parcela.*` por `convenio_id` |
| GET | `/api/v1/convenios/{convenio}/parcelas-em-aberto` | `ConvenioController@parcelasEmAberto` | `Parcela` | `parcela.*` + condição de aberto (valor/data/situação) |
| GET | `/api/v1/convenios/indicadores/quantidade-com-parcelas-em-aberto` | `ConvenioIndicadoresController@quantidadeComParcelasEmAberto` | `Convenio` | contagem de convênios com subquery em `parcela` |
| GET | `/api/v1/convenios/indicadores/valores-em-aberto` | `ConvenioIndicadoresController@valoresEmAberto` | `Convenio`, `Parcela` | soma dos agregados de parcelas por convênio |
| GET | `/api/v1/convenios/indicadores/populacao-atendida` | `ConvenioIndicadoresController@populacaoAtendida` | `Municipio`, `MunicipioDemografia` | soma `demografia_municipio.populacao` para municípios com convênio |
| GET | `/api/v1/convenios/indicadores/eleitores-atendidos` | `ConvenioIndicadoresController@eleitoresAtendidos` | `Municipio`, `MunicipioDemografia` | soma `demografia_municipio.eleitores` para municípios com convênio |
| GET | `/api/v1/parcelas` | `ParcelaController@index` | `Parcela` | `parcela.*` paginado |
| POST | `/api/v1/parcelas` | `ParcelaController@store` | `Parcela` | insert em `parcela(convenio_id,numero,valor_previsto,valor_pago,data_pagamento,nota_empenho,data_ne,valor_empenhado,situacao,observacoes)` |
| GET | `/api/v1/parcelas/{parcela}` | `ParcelaController@show` | `Parcela` | `parcela.*` |
| PATCH/PUT | `/api/v1/parcelas/{parcela}` | `ParcelaController@update` | `Parcela` | update das colunas da parcela |
| PATCH | `/api/v1/parcelas/{parcela}/pagamento` | `ParcelaController@patchPagamento` | `Parcela` | update parcial das colunas de pagamento e eventual ajuste automático de `situacao` |
| DELETE | `/api/v1/parcelas/{parcela}` | `ParcelaController@destroy` | `Parcela` | soft delete em `parcela.deleted_at` |
| GET | `/api/v1/municipios` | `MunicipioController@index` | `Municipio` | `municipio.*` + `regiao_integracao` |
| POST | `/api/v1/municipios` | `MunicipioController@store` | `Municipio` | insert em `municipio(legacy_id,regiao_id,nome,uf,codigo_ibge,codigo_tse,codigo_sigplan)` |
| GET | `/api/v1/municipios/{municipio}` | `MunicipioController@show` | `Municipio`, `MunicipioDemografia` | `municipio.*` + `demografia_municipio` |
| PATCH/PUT | `/api/v1/municipios/{municipio}` | `MunicipioController@update` | `Municipio` | update das colunas do município |
| DELETE | `/api/v1/municipios/{municipio}` | `MunicipioController@destroy` | `Municipio` | delete físico em `municipio` |
| GET | `/api/v1/municipios/indicadores/populacao-por-regiao` | `MunicipioIndicadoresController@populacaoPorRegiao` | `RegiaoIntegracao`, `MunicipioDemografia` | `regiao_integracao(id,descricao)` + join `municipio(regiao_id)` + soma `demografia_municipio.populacao` |
| GET | `/api/v1/municipios/indicadores/eleitores-por-regiao` | `MunicipioIndicadoresController@eleitoresPorRegiao` | `RegiaoIntegracao`, `MunicipioDemografia` | `regiao_integracao` + soma `demografia_municipio.eleitores` |
| GET | `/api/v1/municipio-demografias` | `MunicipioDemografiaController@index` | `MunicipioDemografia` | `demografia_municipio.*` |
| POST | `/api/v1/municipio-demografias` | `MunicipioDemografiaController@store` | `MunicipioDemografia` | insert em `demografia_municipio(municipio_id,ano_ref,populacao,eleitores)` |
| GET | `/api/v1/municipio-demografias/{municipioDemografia}` | `MunicipioDemografiaController@show` | `MunicipioDemografia` | `demografia_municipio.*` |
| PATCH/PUT | `/api/v1/municipio-demografias/{municipioDemografia}` | `MunicipioDemografiaController@update` | `MunicipioDemografia` | update das colunas de demografia |
| DELETE | `/api/v1/municipio-demografias/{municipioDemografia}` | `MunicipioDemografiaController@destroy` | `MunicipioDemografia` | delete físico |
| GET | `/api/v1/orgaos` | `OrgaoController@index` | `Orgao` | `orgao.*` (soft-delete ativo por escopo padrão) |
| POST | `/api/v1/orgaos` | `OrgaoController@store` | `Orgao` | insert em `orgao(sigla,nome,codigo_sigplan)` |
| GET | `/api/v1/orgaos/{orgao}` | `OrgaoController@show` | `Orgao`, `Convenio` | `orgao.*` + convenios relacionados |
| PATCH/PUT | `/api/v1/orgaos/{orgao}` | `OrgaoController@update` | `Orgao` | update colunas de `orgao` |
| DELETE | `/api/v1/orgaos/{orgao}` | `OrgaoController@destroy` | `Orgao` | soft delete em `orgao.deleted_at` |
| GET | `/api/v1/prefeitos` | `PrefeitoController@index` | `Prefeito` | `prefeito.*` |
| POST | `/api/v1/prefeitos` | `PrefeitoController@store` | `Prefeito` | insert em `prefeito(legacy_id,nome_completo,nome_urna,dt_nascimento)` |
| GET | `/api/v1/prefeitos/{prefeito}` | `PrefeitoController@show` | `Prefeito`, `MandatoPrefeito` | `prefeito.*` + mandatos |
| PATCH/PUT | `/api/v1/prefeitos/{prefeito}` | `PrefeitoController@update` | `Prefeito` | update colunas de `prefeito` |
| DELETE | `/api/v1/prefeitos/{prefeito}` | `PrefeitoController@destroy` | `Prefeito` | delete físico |
| GET | `/api/v1/partidos` | `PartidoController@index` | `Partido` | `partido.*` |
| POST | `/api/v1/partidos` | `PartidoController@store` | `Partido` | insert em `partido(legacy_id,sigla,nome,numero)` |
| GET | `/api/v1/partidos/{partido}` | `PartidoController@show` | `Partido`, `MandatoPrefeito` | `partido.*` + mandatos |
| PATCH/PUT | `/api/v1/partidos/{partido}` | `PartidoController@update` | `Partido` | update colunas de `partido` |
| DELETE | `/api/v1/partidos/{partido}` | `PartidoController@destroy` | `Partido` | delete físico |
| GET | `/api/v1/mandatos` | `MandatoController@index` | `MandatoPrefeito` | `mandato_prefeito.*`, filtros `municipio_id` e `vigente_hoje` (`mandato_inicio/mandato_fim`) |
| POST | `/api/v1/mandatos` | `MandatoController@store` | `MandatoPrefeito` | insert em `mandato_prefeito(legacy_id,municipio_id,prefeito_id,partido_id,ano_eleicao,cd_eleicao,dt_eleicao,nr_turno,nr_candidato,mandato_inicio,mandato_fim,mandato_consecutivo,reeleito)` |
| GET | `/api/v1/mandatos/{mandato}` | `MandatoController@show` | `MandatoPrefeito` | `mandato_prefeito.*` |
| PATCH/PUT | `/api/v1/mandatos/{mandato}` | `MandatoController@update` | `MandatoPrefeito` | update colunas de mandato |
| DELETE | `/api/v1/mandatos/{mandato}` | `MandatoController@destroy` | `MandatoPrefeito` | delete físico |
| GET | `/api/v1/financeiro/pi/{pi}` | `FinanceiroController@showByPi` | `PlanoInternoFinanc` | leitura externa em `gp.PlanoInternoFinanc` (SQL Server) |
| GET | `/api/v1/convenios/{convenio}/financeiro` | `FinanceiroController@showByConvenio` | `Convenio`, `PlanoInternoFinanc` | usa `convenio.plano_interno` para lookup externo |

## Requests/Validação vs Schema

- `Store/UpdateConvenioRequest`: colunas de `convenio` + unique de `codigo` em ativos (`deleted_at IS NULL`).
- `Store/UpdateParcelaRequest` + `PatchParcelaPagamentoRequest`: colunas de `parcela`, enum `situacao`, unique composto lógico `(convenio_id, numero)` em ativos.
- `Store/UpdateMunicipioRequest`: reflete `municipio` (nome obrigatório, limites/unique por `uf`, `codigo_sigplan`).
- `Store/UpdateMunicipioDemografiaRequest`: reflete `demografia_municipio` (required `municipio_id`, `ano_ref`, `populacao`, unique `(municipio_id, ano_ref)`).
- `Store/UpdateOrgaoRequest`: reflete `orgao` (sigla/nome obrigatórios, unique ativo para sigla).
- `Store/UpdatePartidoRequest`: reflete `partido` (sigla obrigatória, unique sigla/numero).
- `Store/UpdatePrefeitoRequest`: reflete `prefeito` (`nome_completo` obrigatório, `dt_nascimento`).
- `Store/UpdateMandatoRequest`: reflete `mandato_prefeito` com FKs obrigatórias e datas/constraints de eleição e mandato.

## Checklist de Compatibilidade

- [x] Nenhum endpoint ativo referencia tabela inexistente (entidade `eleicao` removida da API).
- [x] Nenhum endpoint ativo referencia coluna inexistente no schema atual.
- [x] Relações Eloquent alinhadas às FKs reais das migrations.
- [x] Validações FormRequest alinhadas a tipos/nullability/unique/FK do banco.
- [x] Prefixo de versão aplicado: `/api/v1`.
- [x] Front ajustado para novo prefixo e contrato sem recurso `eleicoes`.
