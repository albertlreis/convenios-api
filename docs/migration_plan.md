# Plano de Conversao SQL -> Migrations

## Ambiente Docker
- Repositorio principal: `../docker-compose.yml` (em `~/projetos`)
- Arquivo do app Laravel: `compose.yaml` (em `convenios-api`)
- Service MySQL identificado: `mysql`
- Porta exposta (host): `${FORWARD_DB_PORT:-3306}` (via `.env`, atual: `3307`)
- Porta interna do container: `3306`
- Credenciais no `compose.yaml` + `.env`:
  - `MYSQL_ROOT_PASSWORD=${DB_PASSWORD}`
  - `MYSQL_DATABASE=${DB_DATABASE}`
  - `MYSQL_USER=${DB_USERNAME}`
  - `MYSQL_PASSWORD=${DB_PASSWORD}`
- Valores atuais em `.env`:
  - `DB_HOST=mysql`
  - `DB_PORT=3306`
  - `DB_DATABASE=laravel` (script/migration usa `convenios` quando criar automaticamente)
  - `DB_USERNAME=sail`
  - `DB_PASSWORD=password`

## SQLs Lidos
- `database/seeders/data/01_schema_politico_demografia.sql`
  - DROP/CREATE de: `regiao_integracao`, `municipio`, `partido`, `prefeito`, `mandato_prefeito`, `demografia_municipio`
  - Constraints, indices e FKs completos
- `database/seeders/data/02_seeds_politico.sql`
  - INSERTs para: `regiao_integracao` (13), `municipio` (144), `partido` (20), `prefeito` (218), `mandato_prefeito` (289)
- `database/seeders/data/03_seed_demografia_pa_2026.sql`
  - INSERT/UPSERT para: `demografia_municipio` (146 linhas no arquivo)
- `database/seeders/data/04_validacoes_seeds.sql`
  - Queries de validacao de contagem, integridade FK e cobertura de demografia

## Mapa Tabela -> Migration
- `regiao_integracao`
  - SQL origem: `01_schema_politico_demografia.sql` + `02_seeds_politico.sql`
  - Migration schema editada: `database/migrations/2026_02_09_162936_create_regiao_integracao_table.php`
  - Data migration: `database/migrations/2026_02_11_000100_seed_politico_data.php`
- `municipio`
  - SQL origem: `01_schema_politico_demografia.sql` + `02_seeds_politico.sql`
  - Migration schema editada: `database/migrations/2026_02_09_162937_create_municipio_table.php`
  - Data migration: `database/migrations/2026_02_11_000100_seed_politico_data.php`
- `partido`
  - SQL origem: `01_schema_politico_demografia.sql` + `02_seeds_politico.sql`
  - Migration schema editada: `database/migrations/2026_02_09_162941_create_partido_table.php`
  - Data migration: `database/migrations/2026_02_11_000100_seed_politico_data.php`
- `prefeito`
  - SQL origem: `01_schema_politico_demografia.sql` + `02_seeds_politico.sql`
  - Migration schema editada: `database/migrations/2026_02_09_162942_create_prefeito_table.php`
  - Data migration: `database/migrations/2026_02_11_000100_seed_politico_data.php`
- `mandato_prefeito`
  - SQL origem: `01_schema_politico_demografia.sql` + `02_seeds_politico.sql`
  - Migration schema editada: `database/migrations/2026_02_09_162945_create_mandato_prefeito_table.php`
  - Data migration: `database/migrations/2026_02_11_000100_seed_politico_data.php`
- `demografia_municipio`
  - SQL origem: `01_schema_politico_demografia.sql` + `03_seed_demografia_pa_2026.sql`
  - Migration schema editada: `database/migrations/2026_02_09_162939_create_municipio_demografia_table.php` (arquivo mantido, tabela alinhada para `demografia_municipio`)
  - Data migration: `database/migrations/2026_02_11_000200_seed_demografia_data.php`

## Dependencias (ordem logica)
1. `regiao_integracao`
2. `municipio` (FK -> `regiao_integracao`)
3. `partido`
4. `prefeito`
5. `mandato_prefeito` (FK -> `municipio`, `partido`, `prefeito`)
6. `demografia_municipio` (FK -> `municipio`)

## Estrategia de Idempotencia
- Data migrations fazem parse dos SQLs e aplicam `upsert` em chunks de 500 registros.
- Chaves de upsert:
  - `id` para `regiao_integracao`, `municipio`, `partido`, `prefeito`, `mandato_prefeito`
  - (`municipio_id`, `ano_ref`) para `demografia_municipio`
- `down()` remove dados em ordem segura com FK checks desabilitado quando necessario.
