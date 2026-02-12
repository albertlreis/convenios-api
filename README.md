# Convenios API

API REST em Laravel (Sail + Docker) para gestão de convênios, parcelas, municípios, órgãos, prefeitos, mandatos, eleições e partidos.

## Stack

- Laravel 12
- MySQL 8.4 (principal)
- SQL Server externo (somente leitura) via conexão `sqlsrv_financ`
- Docker Desktop + WSL (Laravel Sail)

## Subir ambiente

```bash
./vendor/bin/sail up -d
```

## Migrar banco

```bash
./vendor/bin/sail artisan migrate
```

## Seed inicial

Inclui:
- 13 regiões de integração
- 144 municípios do Pará
- demografia/eleitorado por ano de referência

```bash
./vendor/bin/sail artisan db:seed
```

## Import inicial de convênios (XLSX)

1. Coloque a planilha em:

`storage/app/imports/Convênios Consolidado.xlsx`

2. Rode:

```bash
./vendor/bin/sail artisan import:convenios-xlsx
```

## SQL Server externo (financeiro)

Configurar no `.env`:

```env
FINANC_DB_HOST=IP_DO_SERVIDOR
FINANC_DB_PORT=1433
FINANC_DB_DATABASE=...
FINANC_DB_USERNAME=...
FINANC_DB_PASSWORD=...
FINANC_DB_ENCRYPT=false
FINANC_DB_TRUST_SERVER_CERTIFICATE=true
```

## Endpoints principais

### Health

`GET /api/health`

### CRUD

- `apiResource('convenios')`
- `apiResource('parcelas')`
- `apiResource('municipios')`
- `apiResource('municipio-demografias')`
- `apiResource('orgaos')`
- `apiResource('prefeitos')`
- `apiResource('mandatos')`
- `apiResource('eleicoes')`
- `apiResource('partidos')`

### Rotas extras

- `GET /api/convenios/{convenio}/parcelas`
- `GET /api/convenios/{convenio}/parcelas-em-aberto`
- `PATCH /api/parcelas/{parcela}/pagamento`
- `GET /api/convenios/{convenio}/financeiro`
- `GET /api/financeiro/pi/{pi}`
- `GET /api/convenios/indicadores/quantidade-com-parcelas-em-aberto`
- `GET /api/convenios/indicadores/valores-em-aberto`
- `GET /api/convenios/indicadores/populacao-atendida`
- `GET /api/convenios/indicadores/eleitores-atendidos`
- `GET /api/municipios/indicadores/populacao-por-regiao`
- `GET /api/municipios/indicadores/eleitores-por-regiao`

## Exemplos curl

### Criar convênio

```bash
curl -X POST http://localhost:8080/api/convenios \
  -H "Content-Type: application/json" \
  -d '{
    "orgao_id": 1,
    "numero_convenio": "CV-001/2026",
    "codigo": "SE:001/2026",
    "municipio_beneficiario_id": 1,
    "plano_interno": "AB12CD34EF5",
    "data_inicio": "2026-01-01",
    "data_fim": "2026-12-31",
    "valor_orgao": 100000.00
  }'
```

### Atualizar parcela (PATCH pagamento)

```bash
curl -X PATCH http://localhost:8080/api/parcelas/1/pagamento \
  -H "Content-Type: application/json" \
  -d '{
    "data_pagamento": "2026-02-01",
    "valor_pago": 5000.00,
    "nota_empenho": "NE-2026-001",
    "data_ne": "2026-01-20",
    "valor_empenhado": 5000.00
  }'
```

### Filtros em convênios

```bash
curl "http://localhost:8080/api/convenios?municipio_id=1&com_parcelas_em_aberto=1&sort=-valor_em_aberto_total&per_page=15"
```

### Indicadores

```bash
curl "http://localhost:8080/api/convenios/indicadores/valores-em-aberto"
curl "http://localhost:8080/api/municipios/indicadores/populacao-por-regiao"
```

### Financeiro SQL Server

```bash
curl "http://localhost:8080/api/financeiro/pi/AB12CD34EF5"
curl "http://localhost:8080/api/convenios/1/financeiro"
```

## Testes

```bash
./vendor/bin/sail artisan test
```

## Observações

- `numero_convenio` não é único.
- `codigo` segue regex `^[A-Z0-9]{2,20}:\d{3}\/\d{4}$` e permite reuso após soft delete.
- PI (`plano_interno`) é tratado como string alfanumérica de 11 caracteres.
- Soft delete aplicado nas entidades solicitadas.

## Troubleshooting (Storage em Docker)

Se ocorrer erro ao subir importações, por exemplo:

`Unable to create a directory at /var/www/html/storage/app/private/imports/convenios`

verifique:

1. O disk `private` em `config/filesystems.php`.
2. Permissões em `storage` e `bootstrap/cache`.
3. Se o container foi recriado após mudanças de Dockerfile/entrypoint.

Este projeto já inclui um entrypoint (`docker/8.4/entrypoint.sh`) que, a cada start:

- garante diretórios `storage/app/private/imports/convenios`;
- garante `storage/framework/{cache,sessions,views}` e `bootstrap/cache`;
- ajusta owner para `WWWUSER:WWWGROUP`;
- aplica `chmod -R ug+rwX` em `storage` e `bootstrap/cache`.

Comandos úteis:

```bash
docker compose up -d --build --force-recreate convenios-api
docker exec convenios-api php artisan config:clear
docker exec convenios-api php artisan cache:clear
docker exec convenios-api php -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $kernel=$app->make(Illuminate\Contracts\Console\Kernel::class); $kernel->bootstrap(); Illuminate\Support\Facades\Storage::disk("private")->makeDirectory("imports/convenios"); echo "ok\n";'
```
