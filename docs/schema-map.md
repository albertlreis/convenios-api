# Schema Map

- Connection: `mysql`
- Database: `convenios`
- Generated at: `2026-02-12T16:45:10+00:00`

## `cache`

### Columns

| Column | Type | Nullable | Default | Extra |
| --- | --- | --- | --- | --- |
| `key` | `varchar(255)` | `NO` | `NULL` | `-` |
| `value` | `mediumtext` | `NO` | `NULL` | `-` |
| `expiration` | `int` | `NO` | `NULL` | `-` |

### Indexes

- `cache_expiration_index` (NON-UNIQUE, BTREE): `expiration`
- `PRIMARY` (UNIQUE, BTREE): `key`

### Foreign Keys

- _none_

## `cache_locks`

### Columns

| Column | Type | Nullable | Default | Extra |
| --- | --- | --- | --- | --- |
| `key` | `varchar(255)` | `NO` | `NULL` | `-` |
| `owner` | `varchar(255)` | `NO` | `NULL` | `-` |
| `expiration` | `int` | `NO` | `NULL` | `-` |

### Indexes

- `cache_locks_expiration_index` (NON-UNIQUE, BTREE): `expiration`
- `PRIMARY` (UNIQUE, BTREE): `key`

### Foreign Keys

- _none_

## `convenio`

### Columns

| Column | Type | Nullable | Default | Extra |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | `NO` | `NULL` | `auto_increment` |
| `orgao_id` | `bigint unsigned` | `YES` | `NULL` | `-` |
| `numero_convenio` | `varchar(255)` | `YES` | `NULL` | `-` |
| `codigo` | `varchar(32)` | `YES` | `NULL` | `-` |
| `municipio_beneficiario_id` | `bigint unsigned` | `YES` | `NULL` | `-` |
| `convenente_nome` | `varchar(255)` | `YES` | `NULL` | `-` |
| `convenente_municipio_id` | `bigint unsigned` | `YES` | `NULL` | `-` |
| `plano_interno` | `varchar(11)` | `YES` | `NULL` | `-` |
| `objeto` | `text` | `YES` | `NULL` | `-` |
| `grupo_despesa` | `varchar(255)` | `YES` | `NULL` | `-` |
| `data_inicio` | `date` | `YES` | `NULL` | `-` |
| `data_fim` | `date` | `YES` | `NULL` | `-` |
| `valor_orgao` | `decimal(15,2)` | `YES` | `NULL` | `-` |
| `valor_contrapartida` | `decimal(15,2)` | `YES` | `NULL` | `-` |
| `valor_aditivo` | `decimal(15,2)` | `YES` | `NULL` | `-` |
| `valor_total_informado` | `decimal(15,2)` | `YES` | `NULL` | `-` |
| `valor_total_calculado` | `decimal(15,2)` | `YES` | `NULL` | `-` |
| `metadata` | `json` | `YES` | `NULL` | `-` |
| `created_at` | `timestamp` | `NO` | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED` |
| `updated_at` | `timestamp` | `NO` | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED on update CURRENT_TIMESTAMP` |
| `deleted_at` | `timestamp` | `YES` | `NULL` | `-` |
| `is_active` | `tinyint(1)` | `YES` | `NULL` | `VIRTUAL GENERATED` |

### Indexes

- `convenio_codigo_unique_active` (UNIQUE, BTREE): `codigo`, `is_active`
- `convenio_convenente_municipio_id_foreign` (NON-UNIQUE, BTREE): `convenente_municipio_id`
- `convenio_data_fim_index` (NON-UNIQUE, BTREE): `data_fim`
- `convenio_data_inicio_index` (NON-UNIQUE, BTREE): `data_inicio`
- `convenio_municipio_beneficiario_id_index` (NON-UNIQUE, BTREE): `municipio_beneficiario_id`
- `convenio_orgao_id_index` (NON-UNIQUE, BTREE): `orgao_id`
- `convenio_orgao_municipio_pi_index` (NON-UNIQUE, BTREE): `orgao_id`, `municipio_beneficiario_id`, `plano_interno`
- `convenio_plano_interno_index` (NON-UNIQUE, BTREE): `plano_interno`
- `PRIMARY` (UNIQUE, BTREE): `id`

### Foreign Keys

- `convenio_convenente_municipio_id_foreign`: (`convenente_municipio_id`) -> `municipio`(`id`) [ON UPDATE NO ACTION, ON DELETE NO ACTION]
- `convenio_municipio_beneficiario_id_foreign`: (`municipio_beneficiario_id`) -> `municipio`(`id`) [ON UPDATE NO ACTION, ON DELETE NO ACTION]
- `convenio_orgao_id_foreign`: (`orgao_id`) -> `orgao`(`id`) [ON UPDATE NO ACTION, ON DELETE NO ACTION]

## `demografia_municipio`

### Columns

| Column | Type | Nullable | Default | Extra |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | `NO` | `NULL` | `auto_increment` |
| `municipio_id` | `bigint unsigned` | `NO` | `NULL` | `-` |
| `ano_ref` | `smallint` | `NO` | `NULL` | `-` |
| `populacao` | `int` | `NO` | `NULL` | `-` |
| `eleitores` | `int` | `YES` | `NULL` | `-` |
| `created_at` | `datetime` | `NO` | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED` |
| `updated_at` | `datetime` | `NO` | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED on update CURRENT_TIMESTAMP` |

### Indexes

- `idx_demografia_ano` (NON-UNIQUE, BTREE): `ano_ref`
- `PRIMARY` (UNIQUE, BTREE): `id`
- `uq_demografia_municipio_ano` (UNIQUE, BTREE): `municipio_id`, `ano_ref`

### Foreign Keys

- `fk_demografia_municipio`: (`municipio_id`) -> `municipio`(`id`) [ON UPDATE RESTRICT, ON DELETE RESTRICT]

## `failed_jobs`

### Columns

| Column | Type | Nullable | Default | Extra |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | `NO` | `NULL` | `auto_increment` |
| `uuid` | `varchar(255)` | `NO` | `NULL` | `-` |
| `connection` | `text` | `NO` | `NULL` | `-` |
| `queue` | `text` | `NO` | `NULL` | `-` |
| `payload` | `longtext` | `NO` | `NULL` | `-` |
| `exception` | `longtext` | `NO` | `NULL` | `-` |
| `failed_at` | `timestamp` | `NO` | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED` |

### Indexes

- `failed_jobs_uuid_unique` (UNIQUE, BTREE): `uuid`
- `PRIMARY` (UNIQUE, BTREE): `id`

### Foreign Keys

- _none_

## `job_batches`

### Columns

| Column | Type | Nullable | Default | Extra |
| --- | --- | --- | --- | --- |
| `id` | `varchar(255)` | `NO` | `NULL` | `-` |
| `name` | `varchar(255)` | `NO` | `NULL` | `-` |
| `total_jobs` | `int` | `NO` | `NULL` | `-` |
| `pending_jobs` | `int` | `NO` | `NULL` | `-` |
| `failed_jobs` | `int` | `NO` | `NULL` | `-` |
| `failed_job_ids` | `longtext` | `NO` | `NULL` | `-` |
| `options` | `mediumtext` | `YES` | `NULL` | `-` |
| `cancelled_at` | `int` | `YES` | `NULL` | `-` |
| `created_at` | `int` | `NO` | `NULL` | `-` |
| `finished_at` | `int` | `YES` | `NULL` | `-` |

### Indexes

- `PRIMARY` (UNIQUE, BTREE): `id`

### Foreign Keys

- _none_

## `jobs`

### Columns

| Column | Type | Nullable | Default | Extra |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | `NO` | `NULL` | `auto_increment` |
| `queue` | `varchar(255)` | `NO` | `NULL` | `-` |
| `payload` | `longtext` | `NO` | `NULL` | `-` |
| `attempts` | `tinyint unsigned` | `NO` | `NULL` | `-` |
| `reserved_at` | `int unsigned` | `YES` | `NULL` | `-` |
| `available_at` | `int unsigned` | `NO` | `NULL` | `-` |
| `created_at` | `int unsigned` | `NO` | `NULL` | `-` |

### Indexes

- `jobs_queue_index` (NON-UNIQUE, BTREE): `queue`
- `PRIMARY` (UNIQUE, BTREE): `id`

### Foreign Keys

- _none_

## `mandato_prefeito`

### Columns

| Column | Type | Nullable | Default | Extra |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | `NO` | `NULL` | `auto_increment` |
| `legacy_id` | `bigint unsigned` | `YES` | `NULL` | `-` |
| `municipio_id` | `bigint unsigned` | `NO` | `NULL` | `-` |
| `prefeito_id` | `bigint unsigned` | `NO` | `NULL` | `-` |
| `partido_id` | `bigint unsigned` | `YES` | `NULL` | `-` |
| `ano_eleicao` | `smallint` | `NO` | `NULL` | `-` |
| `cd_eleicao` | `int` | `NO` | `NULL` | `-` |
| `dt_eleicao` | `date` | `NO` | `NULL` | `-` |
| `nr_turno` | `tinyint` | `NO` | `NULL` | `-` |
| `nr_candidato` | `int` | `YES` | `NULL` | `-` |
| `mandato_inicio` | `date` | `NO` | `NULL` | `-` |
| `mandato_fim` | `date` | `NO` | `NULL` | `-` |
| `mandato_consecutivo` | `tinyint` | `YES` | `NULL` | `-` |
| `reeleito` | `tinyint(1)` | `YES` | `NULL` | `-` |
| `created_at` | `datetime` | `NO` | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED` |
| `updated_at` | `datetime` | `NO` | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED on update CURRENT_TIMESTAMP` |

### Indexes

- `fk_mandato_partido` (NON-UNIQUE, BTREE): `partido_id`
- `idx_mandato_municipio` (NON-UNIQUE, BTREE): `municipio_id`, `mandato_inicio`, `mandato_fim`
- `idx_mandato_prefeito` (NON-UNIQUE, BTREE): `prefeito_id`, `mandato_inicio`
- `PRIMARY` (UNIQUE, BTREE): `id`
- `uq_mandato_eleicao` (UNIQUE, BTREE): `municipio_id`, `ano_eleicao`, `dt_eleicao`, `nr_turno`

### Foreign Keys

- `fk_mandato_municipio`: (`municipio_id`) -> `municipio`(`id`) [ON UPDATE RESTRICT, ON DELETE RESTRICT]
- `fk_mandato_partido`: (`partido_id`) -> `partido`(`id`) [ON UPDATE RESTRICT, ON DELETE RESTRICT]
- `fk_mandato_prefeito`: (`prefeito_id`) -> `prefeito`(`id`) [ON UPDATE RESTRICT, ON DELETE RESTRICT]

## `migrations`

### Columns

| Column | Type | Nullable | Default | Extra |
| --- | --- | --- | --- | --- |
| `id` | `int unsigned` | `NO` | `NULL` | `auto_increment` |
| `migration` | `varchar(255)` | `NO` | `NULL` | `-` |
| `batch` | `int` | `NO` | `NULL` | `-` |

### Indexes

- `PRIMARY` (UNIQUE, BTREE): `id`

### Foreign Keys

- _none_

## `municipio`

### Columns

| Column | Type | Nullable | Default | Extra |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | `NO` | `NULL` | `auto_increment` |
| `legacy_id` | `bigint unsigned` | `YES` | `NULL` | `-` |
| `regiao_id` | `bigint unsigned` | `YES` | `NULL` | `-` |
| `nome` | `varchar(120)` | `NO` | `NULL` | `-` |
| `uf` | `char(2)` | `NO` | `PA` | `-` |
| `codigo_ibge` | `char(7)` | `YES` | `NULL` | `-` |
| `codigo_tse` | `int` | `YES` | `NULL` | `-` |
| `codigo_sigplan` | `int` | `YES` | `NULL` | `-` |
| `created_at` | `datetime` | `NO` | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED` |
| `updated_at` | `datetime` | `NO` | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED on update CURRENT_TIMESTAMP` |

### Indexes

- `idx_municipio_ibge` (NON-UNIQUE, BTREE): `codigo_ibge`
- `idx_municipio_nome` (NON-UNIQUE, BTREE): `nome`
- `idx_municipio_regiao` (NON-UNIQUE, BTREE): `regiao_id`
- `PRIMARY` (UNIQUE, BTREE): `id`
- `uq_municipio_ibge_uf` (UNIQUE, BTREE): `codigo_ibge`, `uf`
- `uq_municipio_nome_uf` (UNIQUE, BTREE): `nome`, `uf`
- `uq_municipio_tse_uf` (UNIQUE, BTREE): `codigo_tse`, `uf`

### Foreign Keys

- `fk_municipio_regiao`: (`regiao_id`) -> `regiao_integracao`(`id`) [ON UPDATE RESTRICT, ON DELETE RESTRICT]

## `orgao`

### Columns

| Column | Type | Nullable | Default | Extra |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | `NO` | `NULL` | `auto_increment` |
| `sigla` | `varchar(20)` | `NO` | `NULL` | `-` |
| `nome` | `varchar(255)` | `NO` | `NULL` | `-` |
| `codigo_sigplan` | `int` | `YES` | `NULL` | `-` |
| `created_at` | `timestamp` | `NO` | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED` |
| `updated_at` | `timestamp` | `NO` | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED on update CURRENT_TIMESTAMP` |
| `deleted_at` | `timestamp` | `YES` | `NULL` | `-` |
| `is_active` | `tinyint(1)` | `YES` | `NULL` | `VIRTUAL GENERATED` |

### Indexes

- `orgao_sigla_index` (NON-UNIQUE, BTREE): `sigla`
- `orgao_sigla_unique_active` (UNIQUE, BTREE): `sigla`, `is_active`
- `PRIMARY` (UNIQUE, BTREE): `id`

### Foreign Keys

- _none_

## `parcela`

### Columns

| Column | Type | Nullable | Default | Extra |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | `NO` | `NULL` | `auto_increment` |
| `convenio_id` | `bigint unsigned` | `NO` | `NULL` | `-` |
| `numero` | `int` | `NO` | `NULL` | `-` |
| `valor_previsto` | `decimal(15,2)` | `YES` | `NULL` | `-` |
| `valor_pago` | `decimal(15,2)` | `YES` | `NULL` | `-` |
| `data_pagamento` | `date` | `YES` | `NULL` | `-` |
| `nota_empenho` | `varchar(50)` | `YES` | `NULL` | `-` |
| `data_ne` | `date` | `YES` | `NULL` | `-` |
| `valor_empenhado` | `decimal(15,2)` | `YES` | `NULL` | `-` |
| `situacao` | `enum('PREVISTA','PAGA','CANCELADA')` | `NO` | `PREVISTA` | `-` |
| `observacoes` | `text` | `YES` | `NULL` | `-` |
| `created_at` | `timestamp` | `NO` | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED` |
| `updated_at` | `timestamp` | `NO` | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED on update CURRENT_TIMESTAMP` |
| `deleted_at` | `timestamp` | `YES` | `NULL` | `-` |
| `is_active` | `tinyint(1)` | `YES` | `NULL` | `VIRTUAL GENERATED` |

### Indexes

- `parcela_convenio_id_index` (NON-UNIQUE, BTREE): `convenio_id`
- `parcela_convenio_numero_unique_active` (UNIQUE, BTREE): `convenio_id`, `numero`, `is_active`
- `parcela_convenio_situacao_data_index` (NON-UNIQUE, BTREE): `convenio_id`, `situacao`, `data_pagamento`
- `parcela_nota_empenho_index` (NON-UNIQUE, BTREE): `nota_empenho`
- `parcela_situacao_index` (NON-UNIQUE, BTREE): `situacao`
- `PRIMARY` (UNIQUE, BTREE): `id`

### Foreign Keys

- `parcela_convenio_id_foreign`: (`convenio_id`) -> `convenio`(`id`) [ON UPDATE NO ACTION, ON DELETE CASCADE]

## `partido`

### Columns

| Column | Type | Nullable | Default | Extra |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | `NO` | `NULL` | `auto_increment` |
| `legacy_id` | `bigint unsigned` | `YES` | `NULL` | `-` |
| `numero` | `smallint` | `YES` | `NULL` | `-` |
| `sigla` | `varchar(10)` | `NO` | `NULL` | `-` |
| `nome` | `varchar(120)` | `YES` | `NULL` | `-` |
| `created_at` | `datetime` | `NO` | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED` |
| `updated_at` | `datetime` | `NO` | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED on update CURRENT_TIMESTAMP` |

### Indexes

- `PRIMARY` (UNIQUE, BTREE): `id`
- `uq_partido_numero` (UNIQUE, BTREE): `numero`
- `uq_partido_sigla` (UNIQUE, BTREE): `sigla`

### Foreign Keys

- _none_

## `password_reset_tokens`

### Columns

| Column | Type | Nullable | Default | Extra |
| --- | --- | --- | --- | --- |
| `email` | `varchar(255)` | `NO` | `NULL` | `-` |
| `token` | `varchar(255)` | `NO` | `NULL` | `-` |
| `created_at` | `timestamp` | `NO` | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED` |

### Indexes

- `PRIMARY` (UNIQUE, BTREE): `email`

### Foreign Keys

- _none_

## `prefeito`

### Columns

| Column | Type | Nullable | Default | Extra |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | `NO` | `NULL` | `auto_increment` |
| `legacy_id` | `bigint unsigned` | `YES` | `NULL` | `-` |
| `nome_completo` | `varchar(200)` | `NO` | `NULL` | `-` |
| `nome_urna` | `varchar(200)` | `YES` | `NULL` | `-` |
| `dt_nascimento` | `date` | `YES` | `NULL` | `-` |
| `created_at` | `datetime` | `NO` | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED` |
| `updated_at` | `datetime` | `NO` | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED on update CURRENT_TIMESTAMP` |

### Indexes

- `idx_prefeito_nome` (NON-UNIQUE, BTREE): `nome_completo`
- `idx_prefeito_urna` (NON-UNIQUE, BTREE): `nome_urna`
- `PRIMARY` (UNIQUE, BTREE): `id`

### Foreign Keys

- _none_

## `regiao_integracao`

### Columns

| Column | Type | Nullable | Default | Extra |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | `NO` | `NULL` | `auto_increment` |
| `legacy_id` | `bigint unsigned` | `YES` | `NULL` | `-` |
| `descricao` | `varchar(255)` | `NO` | `NULL` | `-` |
| `created_at` | `datetime` | `NO` | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED` |
| `updated_at` | `datetime` | `NO` | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED on update CURRENT_TIMESTAMP` |

### Indexes

- `PRIMARY` (UNIQUE, BTREE): `id`
- `uq_regiao_descricao` (UNIQUE, BTREE): `descricao`

### Foreign Keys

- _none_

## `sessions`

### Columns

| Column | Type | Nullable | Default | Extra |
| --- | --- | --- | --- | --- |
| `id` | `varchar(255)` | `NO` | `NULL` | `-` |
| `user_id` | `bigint unsigned` | `YES` | `NULL` | `-` |
| `ip_address` | `varchar(45)` | `YES` | `NULL` | `-` |
| `user_agent` | `text` | `YES` | `NULL` | `-` |
| `payload` | `longtext` | `NO` | `NULL` | `-` |
| `last_activity` | `int` | `NO` | `NULL` | `-` |

### Indexes

- `PRIMARY` (UNIQUE, BTREE): `id`
- `sessions_last_activity_index` (NON-UNIQUE, BTREE): `last_activity`
- `sessions_user_id_index` (NON-UNIQUE, BTREE): `user_id`

### Foreign Keys

- _none_

## `users`

### Columns

| Column | Type | Nullable | Default | Extra |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | `NO` | `NULL` | `auto_increment` |
| `name` | `varchar(255)` | `NO` | `NULL` | `-` |
| `email` | `varchar(255)` | `NO` | `NULL` | `-` |
| `email_verified_at` | `timestamp` | `YES` | `NULL` | `-` |
| `password` | `varchar(255)` | `NO` | `NULL` | `-` |
| `remember_token` | `varchar(100)` | `YES` | `NULL` | `-` |
| `created_at` | `timestamp` | `NO` | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED` |
| `updated_at` | `timestamp` | `NO` | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED on update CURRENT_TIMESTAMP` |

### Indexes

- `PRIMARY` (UNIQUE, BTREE): `id`
- `users_email_unique` (UNIQUE, BTREE): `email`

### Foreign Keys

- _none_

