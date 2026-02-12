# Importacao de Convenios XLSX (Fluxo Tolerante)

## Objetivo
A importacao foi desenhada para **nao bloquear** por erro de linha.

1. Upload sempre grava staging.
2. Confirmacao processa tudo que for possivel.
3. Itens nao resolvidos viram pendencia (`convenio_import_pending_items`).

## Formato esperado do XLSX
O arquivo deve conter 3 abas com esses nomes:

1. `lista`
2. `parcelas`
3. `plano_interno`

### Aba `lista`
Colunas principais:

- `orgao`
- `municipio`
- `convenente`
- `numero_convenio`
- `ano`
- `plano_interno`
- `objeto`
- `grupo_despesa`
- `data_inicio`
- `data_fim`
- `valor_total`
- `valor_orgao`
- `valor_contrapartida`
- `quantidade_parcelas`

### Aba `parcelas`
Colunas principais:

- `numero_convenio`
- `numero_parcela`
- `valor_previsto`
- `situacao`
- `data_pagamento`
- `valor_pago`
- `observacoes`

### Aba `plano_interno`
Colunas principais:

- `numero_convenio`
- `plano_interno`

## Regras de normalizacao
- Datas aceitas: `Y-m-d`, `d/m/Y`, `d-m-Y`, `d.m.Y` e serial Excel.
- Numeros aceitam formatos com `,` e `.`.
- Strings sao trimadas; vazio vira `null`.
- `situacao` de parcela: `PREVISTA`, `PAGA` ou `CANCELADA`.

## Endpoints
Base: `/api/v1/imports/convenios`

- `GET /api/v1/imports/convenios`
  - Lista historico recente (ultimas 50 importacoes).
- `POST /api/v1/imports/convenios/upload`
  - `multipart/form-data` com campo `arquivo`.
  - Salva arquivo + staging + preview inicial.
- `POST /api/v1/imports/convenios/confirm`
  - Body: `{ "import_id": number, "batch_size"?: number }`
  - Processa staging para dominio final de forma tolerante.
- `GET /api/v1/imports/convenios/{id}`
  - Detalhe da importacao com preview e pendencias.

## Tabelas envolvidas
### Dominio
- `convenio`
- `convenio_plano_interno`
- `parcela`

### Staging
- `convenio_imports`
- `convenio_import_lista_rows`
- `convenio_import_parcelas_rows`
- `convenio_import_pi_rows`
- `convenio_import_pending_items`

## Fluxo recomendado
1. `POST /upload`
2. Revisar preview (`status`, `issues`, contagens)
3. `POST /confirm`
4. Revisar pendencias e tratar no front

## Troubleshooting
### Upload retorna 422
- Verifique se o campo do arquivo e `arquivo`.
- Verifique extensao `.xlsx` ou `.xls`.

### Confirmacao cria muitas pendencias
- `orgao_nao_encontrado`: sigla/nome do orgao nao bate no cadastro.
- `municipio_beneficiario_nao_encontrado`: nome de municipio divergente.
- `convenio_nao_encontrado`: parcela/PI aponta para numero de convenio inexistente.

### Comandos uteis
No container da API:

```bash
php artisan config:clear
php artisan migrate:fresh --seed --force
php artisan test
```

No front:

```bash
npm run build
```

No workspace `projetos`:

```bash
make setup
make reset
```
