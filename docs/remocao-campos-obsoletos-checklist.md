# Checklist de Remoção de Campos Obsoletos

## Banco
- `convenio`: removidos `codigo`, `municipio_beneficiario_id`, `municipio_beneficiario_nome_informado`, `convenente_municipio_id`, `convenente_municipio_nome_informado`, `quantidade_parcelas_informada`, `metadata`, `orgao_nome_informado`.
- `convenio`: adicionado `municipio_id` com backfill (`COALESCE(municipio_beneficiario_id, convenente_municipio_id)`).
- `parcela`: removido `convenio_numero_informado`.
- `convenio_plano_interno`: removido `origem`.
- `partido`: removido `legacy_id`.
- `municipio`: removido `legacy_id`.
- `mandato_prefeito`: removido `legacy_id`.
- Migration aplicada em `database/migrations/2026_02_13_132000_remove_obsolete_fields_and_fix_convenio_links.php`.

## Back-end
- Requests atualizados:
  - `StoreConvenioRequest` e `UpdateConvenioRequest` sem campos removidos.
  - `Store/UpdateMunicipioRequest`, `Store/UpdatePartidoRequest`, `Store/UpdateMandatoRequest` sem `legacy_id`.
- Resources atualizados:
  - `ConvenioResource` sem campos removidos e com `municipio`.
  - `ParcelaResource` sem `convenio_numero_informado`.
  - `MunicipioResource`, `PartidoResource`, `MandatoResource` sem `legacy_id`.
- Domínio/consultas:
  - `Convenio` usa `municipio_id` (`municipio()`).
  - `ConvenioController`/`ConvenioIndicadoresController` migrados para `municipio_id`.

## Importação
- `ConvenioImportService`:
  - não persiste mais os campos removidos.
  - convênio identificado por `numero_convenio`.
  - vínculo de órgão por prioridade: `orgao_legacy_id` (mapeado para `codigo_sigplan`) -> sigla -> nome normalizado.
  - parcelas vinculadas somente por `convenio_id`.
  - `convenio_plano_interno` sem `origem`.
- `ImportConveniosXlsx` ajustado para o novo contrato (sem `codigo`/`metadata`/municípios legados).

## Front-end
- Tipos e telas de convênio atualizados para:
  - usar `numero_convenio` e `municipio_id`/`municipio`.
  - remover exibição/uso de `codigo` e campos legados.
- Formulário de convênio (`ConvenioForm`) sem `codigo` e com `municipio_id`.

## Validação executada
- Backend: `php artisan test --testsuite=Feature` (19 testes passados).
- Frontend: `npm run build` em container (`docker run convenios-front-build-check ...`) concluído com sucesso.
