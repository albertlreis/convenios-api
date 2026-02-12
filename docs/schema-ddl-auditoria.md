# Auditoria DDL x Migrations

Data da auditoria: 2026-02-12

## Resultado executivo
- Todo DDL de schema foi consolidado em migrations Laravel em `database/migrations`.
- O arquivo legado `database/seeders/data/01_schema_politico_demografia.sql` foi limpo de DDL e mantido apenas como referência histórica.
- Seeds SQL ficaram somente com DML (`INSERT`/`ON DUPLICATE KEY UPDATE`).
- `created_at` e `updated_at` foram removidos dos `INSERT` dos seeds.
- Para tabelas com timestamps, o schema canônico foi ajustado para defaults no banco:
  - `created_at DEFAULT CURRENT_TIMESTAMP`
  - `updated_at DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`

## Inventário de ocorrências (arquivo + linha)

| Arquivo | Linha | Tabela(s) | Tipo | Decisão | Observações |
|---|---:|---|---|---|---|
| `database/seeders/data/01_schema_politico_demografia.sql` | histórico (7-128 antes da limpeza) | `regiao_integracao`, `municipio`, `partido`, `prefeito`, `mandato_prefeito`, `demografia_municipio` | DROP/CREATE/FK/INDEX/ENGINE/CHARSET/COLLATE | Migrado para migrations | DDL removido do arquivo de seed e refletido nas migrations existentes. |
| `database/migrations/2026_02_09_162936_create_regiao_integracao_table.php` | 13 | `regiao_integracao` | CREATE | Alinhado ao canônico | Corrigido campo `legacy_id`; removido `codigo_sigplan`; adicionado `engine InnoDB`; timestamp default no banco. |
| `database/migrations/2026_02_09_162937_create_municipio_table.php` | 13 | `municipio` | CREATE/FK/INDEX | Alinhado ao canônico | Incluído `legacy_id`; preservados índices/unique/FK conforme DDL legado; timestamp default no banco. |
| `database/migrations/2026_02_09_162941_create_partido_table.php` | 13 | `partido` | CREATE/UNIQUE | Alinhado ao canônico | `numero` ajustado para `smallInteger` (signed); timestamp default no banco. |
| `database/migrations/2026_02_09_162942_create_prefeito_table.php` | 13 | `prefeito` | CREATE/INDEX | Alinhado ao canônico | Índices mantidos; timestamp default no banco. |
| `database/migrations/2026_02_09_162945_create_mandato_prefeito_table.php` | 13 | `mandato_prefeito` | CREATE/FK/INDEX/UNIQUE | Alinhado ao canônico | `ano_eleicao` e `nr_turno` ajustados para signed; FK/índices preservados; timestamp default no banco. |
| `database/migrations/2026_02_09_162939_create_municipio_demografia_table.php` | 13 | `demografia_municipio` | CREATE/FK/INDEX/UNIQUE | Alinhado ao canônico | `ano_ref` ajustado para `smallInteger` (signed); timestamp default no banco. |
| `database/migrations/2026_02_09_162940_create_orgao_table.php` | 12 | `orgao` | CREATE | Ajuste de padrão de timestamp | `timestamps()` substituído por colunas explícitas com defaults no banco. |
| `database/migrations/2026_02_09_162947_create_convenio_table.php` | 12 | `convenio` | CREATE | Ajuste de padrão de timestamp | `timestamps()` substituído por colunas explícitas com defaults no banco. |
| `database/migrations/2026_02_09_162949_create_parcela_table.php` | 12 | `parcela` | CREATE | Ajuste de padrão de timestamp | `timestamps()` substituído por colunas explícitas com defaults no banco. |
| `database/migrations/0001_01_01_000000_create_users_table.php` | 12 | `users`, `password_reset_tokens` | CREATE | Ajuste de padrão de timestamp | `users.created_at/updated_at` com defaults no banco; `password_reset_tokens.created_at` com `useCurrent()`. |
| `database/seeders/data/02_seeds_politico.sql` | 6,22,169,192,413 | tabelas políticas | INSERT | Limpo | Removidas colunas/valores de `created_at`/`updated_at`. |
| `database/seeders/data/03_seed_demografia_pa_2026.sql` | 7 | `demografia_municipio` | INSERT/UPSERT | Limpo | Removidas colunas/valores de `created_at`/`updated_at`; removido update explícito de `updated_at`. |
| `database/seeders/data/05_orgao.sql` | 4 | `orgao` | INSERT | Mantido | Já estava sem timestamps e sem DDL. |
| `scripts/validate_db.sh` | 171,173 | várias | SHOW CREATE TABLE | Mantido | Não altera schema; apenas validação. |

## Schema canônico final (derivado do DDL legado + ajuste obrigatório de timestamps)

### `regiao_integracao`
- `id` BIGINT UNSIGNED PK AI
- `legacy_id` BIGINT UNSIGNED NULL
- `descricao` VARCHAR(255) NOT NULL UNIQUE (`uq_regiao_descricao`)
- `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
- `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
- Engine/charset/collation: InnoDB / utf8mb4 / utf8mb4_unicode_ci

### `municipio`
- `id` BIGINT UNSIGNED PK AI
- `legacy_id` BIGINT UNSIGNED NULL
- `regiao_id` BIGINT UNSIGNED NULL FK -> `regiao_integracao(id)` RESTRICT/RESTRICT
- `nome` VARCHAR(120) NOT NULL
- `uf` CHAR(2) NOT NULL DEFAULT 'PA'
- `codigo_ibge` CHAR(7) NULL
- `codigo_tse` INT NULL
- `codigo_sigplan` INT NULL
- `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
- `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
- UNIQUE: `uq_municipio_nome_uf`, `uq_municipio_ibge_uf`, `uq_municipio_tse_uf`
- INDEX: `idx_municipio_nome`, `idx_municipio_ibge`, `idx_municipio_regiao`
- Engine/charset/collation: InnoDB / utf8mb4 / utf8mb4_unicode_ci

### `partido`
- `id` BIGINT UNSIGNED PK AI
- `legacy_id` BIGINT UNSIGNED NULL
- `numero` SMALLINT NULL UNIQUE (`uq_partido_numero`)
- `sigla` VARCHAR(10) NOT NULL UNIQUE (`uq_partido_sigla`)
- `nome` VARCHAR(120) NULL
- `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
- `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
- Engine/charset/collation: InnoDB / utf8mb4 / utf8mb4_unicode_ci

### `prefeito`
- `id` BIGINT UNSIGNED PK AI
- `legacy_id` BIGINT UNSIGNED NULL
- `nome_completo` VARCHAR(200) NOT NULL
- `nome_urna` VARCHAR(200) NULL
- `dt_nascimento` DATE NULL
- `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
- `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
- INDEX: `idx_prefeito_nome`, `idx_prefeito_urna`
- Engine/charset/collation: InnoDB / utf8mb4 / utf8mb4_unicode_ci

### `mandato_prefeito`
- `id` BIGINT UNSIGNED PK AI
- `legacy_id` BIGINT UNSIGNED NULL
- `municipio_id` BIGINT UNSIGNED NOT NULL FK -> `municipio(id)` RESTRICT/RESTRICT
- `prefeito_id` BIGINT UNSIGNED NOT NULL FK -> `prefeito(id)` RESTRICT/RESTRICT
- `partido_id` BIGINT UNSIGNED NULL FK -> `partido(id)` RESTRICT/RESTRICT
- `ano_eleicao` SMALLINT NOT NULL
- `cd_eleicao` INT NOT NULL
- `dt_eleicao` DATE NOT NULL
- `nr_turno` TINYINT NOT NULL
- `nr_candidato` INT NULL
- `mandato_inicio` DATE NOT NULL
- `mandato_fim` DATE NOT NULL
- `mandato_consecutivo` TINYINT NULL
- `reeleito` TINYINT(1) NULL
- `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
- `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
- UNIQUE: `uq_mandato_eleicao`
- INDEX: `idx_mandato_municipio`, `idx_mandato_prefeito`, `fk_mandato_partido`
- Engine/charset/collation: InnoDB / utf8mb4 / utf8mb4_unicode_ci

### `demografia_municipio`
- `id` BIGINT UNSIGNED PK AI
- `municipio_id` BIGINT UNSIGNED NOT NULL FK -> `municipio(id)` RESTRICT/RESTRICT
- `ano_ref` SMALLINT NOT NULL
- `populacao` INT NOT NULL
- `eleitores` INT NULL
- `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
- `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
- UNIQUE: `uq_demografia_municipio_ano`
- INDEX: `idx_demografia_ano`
- Engine/charset/collation: InnoDB / utf8mb4 / utf8mb4_unicode_ci

## Observações de implementação
- Não foi necessário fallback com `DB::statement` para `ON UPDATE CURRENT_TIMESTAMP`; `Schema Builder` cobriu os casos com `useCurrent()->useCurrentOnUpdate()`.
- O projeto já possuía migrations de dados (`2026_02_11_000100_seed_politico_data.php` e `2026_02_11_000200_seed_demografia_data.php`) que parseiam SQL e continuam compatíveis após remoção das colunas de timestamps dos arquivos `.sql`.

## Validação executada
- Tentativa de `php artisan migrate:fresh`: falhou por versão de PHP do ambiente local (`7.3.33`) inferior ao mínimo exigido pelo projeto (`>=8.4.0`).
- Tentativa de `php artisan db:seed`: falhou pelo mesmo motivo.
- Portanto, não foi possível executar `SHOW CREATE TABLE` em banco real neste ambiente.
