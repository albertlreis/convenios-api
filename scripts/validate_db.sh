#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

FRESH=0
if [[ "${1:-}" == "--fresh" ]]; then
  FRESH=1
fi

if [[ -f .env ]]; then
  set -a
  # shellcheck disable=SC1091
  source .env
  set +a
fi

DB_NAME="convenios"
DB_CHARSET="utf8mb4"
DB_COLLATION="utf8mb4_unicode_ci"
DB_USER="${DB_USERNAME:-root}"
DB_PASS="${DB_PASSWORD:-}"
DB_HOST_VAL="${DB_HOST:-127.0.0.1}"
DB_PORT_VAL="${DB_PORT:-3306}"

COMPOSE_FILE=""
for candidate in "compose.yaml" "compose.yml" "docker-compose.yml" "docker-compose.yaml" "../docker-compose.yml" "../compose.yaml"; do
  if [[ -f "$candidate" ]]; then
    COMPOSE_FILE="$candidate"
    break
  fi
done

HAS_DOCKER=0
if command -v docker >/dev/null 2>&1 && [[ -n "$COMPOSE_FILE" ]] && docker compose -f "$COMPOSE_FILE" version >/dev/null 2>&1; then
  HAS_DOCKER=1
fi

MYSQL_SERVICE=""
APP_SERVICE=""

if [[ "$HAS_DOCKER" -eq 1 ]]; then
  SERVICES="$(WWWUSER="${WWWUSER:-1000}" WWWGROUP="${WWWGROUP:-1000}" docker compose -f "$COMPOSE_FILE" config --services)"
  while IFS= read -r service; do
    case "$service" in
      mysql|db|mariadb|mysql-shared)
        MYSQL_SERVICE="$service"
        ;;
    esac

    if [[ -z "$APP_SERVICE" ]]; then
      case "$service" in
        laravel.test|convenios-api|app)
          APP_SERVICE="$service"
          ;;
      esac
    fi
  done <<< "$SERVICES"

  if [[ -z "$MYSQL_SERVICE" ]]; then
    while IFS= read -r service; do
      if [[ "$service" == *mysql* || "$service" == *maria* || "$service" == *db* ]]; then
        MYSQL_SERVICE="$service"
        break
      fi
    done <<< "$SERVICES"
  fi

  if [[ -z "$APP_SERVICE" ]]; then
    while IFS= read -r service; do
      if [[ "$service" == *laravel* || "$service" == *api* || "$service" == app ]]; then
        APP_SERVICE="$service"
        break
      fi
    done <<< "$SERVICES"
  fi
fi

compose_cmd() {
  WWWUSER="${WWWUSER:-1000}" WWWGROUP="${WWWGROUP:-1000}" docker compose -f "$COMPOSE_FILE" "$@"
}

echo "[validate_db] Root: $ROOT_DIR"
if [[ "$HAS_DOCKER" -eq 1 ]]; then
  echo "[validate_db] Docker compose: $COMPOSE_FILE"
  echo "[validate_db] MySQL service: $MYSQL_SERVICE"
  echo "[validate_db] App service: $APP_SERVICE"
fi

mysql_exec() {
  local sql="$1"
  local database="${2:-}"

  if [[ "$HAS_DOCKER" -eq 1 && -n "$MYSQL_SERVICE" ]]; then
    compose_cmd up -d "$MYSQL_SERVICE" >/dev/null

    local ping_args=(mysqladmin ping -h127.0.0.1 -uroot --silent)
    if [[ -n "$DB_PASS" ]]; then
      ping_args+=("-p$DB_PASS")
    fi
    for _ in $(seq 1 30); do
      if compose_cmd exec -T "$MYSQL_SERVICE" "${ping_args[@]}" >/dev/null 2>&1; then
        break
      fi
      sleep 2
    done

    local args=(mysql -h127.0.0.1 -uroot)
    if [[ -n "$DB_PASS" ]]; then
      args+=("-p$DB_PASS")
    fi
    if [[ -n "$database" ]]; then
      args+=("$database")
    fi
    args+=(-e "$sql")

    compose_cmd exec -T "$MYSQL_SERVICE" "${args[@]}"
  else
    local args=(mysql -h "$DB_HOST_VAL" -P "$DB_PORT_VAL" -u "$DB_USER")
    if [[ -n "$DB_PASS" ]]; then
      args+=("-p$DB_PASS")
    fi
    if [[ -n "$database" ]]; then
      args+=("$database")
    fi
    args+=(-e "$sql")

    "${args[@]}"
  fi
}

artisan_run() {
  local cmd="$1"

  if [[ "$HAS_DOCKER" -eq 1 && -n "$APP_SERVICE" ]]; then
    if compose_cmd ps --status running --services | grep -qx "$APP_SERVICE"; then
      compose_cmd exec -T -e DB_DATABASE="$DB_NAME" "$APP_SERVICE" php artisan $cmd
    else
      compose_cmd run --rm --no-deps -T -e DB_DATABASE="$DB_NAME" "$APP_SERVICE" php artisan $cmd
    fi
  else
    DB_DATABASE="$DB_NAME" php artisan $cmd
  fi
}

echo "[validate_db] Ensuring database '$DB_NAME' exists"
mysql_exec "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET $DB_CHARSET COLLATE $DB_COLLATION;"
if [[ "$HAS_DOCKER" -eq 1 && -n "${DB_USER:-}" && "$DB_USER" != "root" ]]; then
  mysql_exec "CREATE USER IF NOT EXISTS '$DB_USER'@'%' IDENTIFIED BY '$DB_PASS'; GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'%'; FLUSH PRIVILEGES;"
fi

if [[ "$FRESH" -eq 1 ]]; then
  echo "[validate_db] Running migrate:fresh"
  artisan_run "migrate:fresh --force"
else
  echo "[validate_db] Running migrate"
  artisan_run "migrate --force"
fi

echo "[validate_db] Table counts"
mysql_exec "
SELECT 'regiao_integracao' AS tabela, COUNT(*) AS qtd FROM regiao_integracao
UNION ALL SELECT 'municipio', COUNT(*) FROM municipio
UNION ALL SELECT 'partido', COUNT(*) FROM partido
UNION ALL SELECT 'prefeito', COUNT(*) FROM prefeito
UNION ALL SELECT 'mandato_prefeito', COUNT(*) FROM mandato_prefeito
UNION ALL SELECT 'demografia_municipio', COUNT(*) FROM demografia_municipio;
" "$DB_NAME"

echo "[validate_db] SHOW CREATE TABLE checks"
for table in regiao_integracao municipio partido prefeito mandato_prefeito demografia_municipio; do
  mysql_exec "SHOW CREATE TABLE $table;" "$DB_NAME" >/dev/null
  echo "  - $table: OK"
done

missing_demografia_fk=$(mysql_exec "SELECT COUNT(*) AS c FROM demografia_municipio d LEFT JOIN municipio m ON m.id = d.municipio_id WHERE m.id IS NULL;" "$DB_NAME" | tail -n 1 | tr -d '[:space:]')
missing_mandato_municipio_fk=$(mysql_exec "SELECT COUNT(*) AS c FROM mandato_prefeito mp LEFT JOIN municipio m ON m.id = mp.municipio_id WHERE m.id IS NULL;" "$DB_NAME" | tail -n 1 | tr -d '[:space:]')
missing_mandato_prefeito_fk=$(mysql_exec "SELECT COUNT(*) AS c FROM mandato_prefeito mp LEFT JOIN prefeito p ON p.id = mp.prefeito_id WHERE p.id IS NULL;" "$DB_NAME" | tail -n 1 | tr -d '[:space:]')
missing_mandato_partido_fk=$(mysql_exec "SELECT COUNT(*) AS c FROM mandato_prefeito mp LEFT JOIN partido pt ON pt.id = mp.partido_id WHERE mp.partido_id IS NOT NULL AND pt.id IS NULL;" "$DB_NAME" | tail -n 1 | tr -d '[:space:]')

duplicidade_municipio=$(mysql_exec "SELECT COUNT(*) AS c FROM (SELECT codigo_ibge, uf, COUNT(*) qtd FROM municipio WHERE codigo_ibge IS NOT NULL GROUP BY codigo_ibge, uf HAVING qtd > 1) t;" "$DB_NAME" | tail -n 1 | tr -d '[:space:]')
duplicidade_partido_sigla=$(mysql_exec "SELECT COUNT(*) AS c FROM (SELECT sigla, COUNT(*) qtd FROM partido GROUP BY sigla HAVING qtd > 1) t;" "$DB_NAME" | tail -n 1 | tr -d '[:space:]')
duplicidade_demografia=$(mysql_exec "SELECT COUNT(*) AS c FROM (SELECT municipio_id, ano_ref, COUNT(*) qtd FROM demografia_municipio GROUP BY municipio_id, ano_ref HAVING qtd > 1) t;" "$DB_NAME" | tail -n 1 | tr -d '[:space:]')

if [[ "$missing_demografia_fk" != "0" || "$missing_mandato_municipio_fk" != "0" || "$missing_mandato_prefeito_fk" != "0" || "$missing_mandato_partido_fk" != "0" || "$duplicidade_municipio" != "0" || "$duplicidade_partido_sigla" != "0" || "$duplicidade_demografia" != "0" ]]; then
  echo "[validate_db] Validation failed"
  echo "  missing_demografia_fk=$missing_demografia_fk"
  echo "  missing_mandato_municipio_fk=$missing_mandato_municipio_fk"
  echo "  missing_mandato_prefeito_fk=$missing_mandato_prefeito_fk"
  echo "  missing_mandato_partido_fk=$missing_mandato_partido_fk"
  echo "  duplicidade_municipio=$duplicidade_municipio"
  echo "  duplicidade_partido_sigla=$duplicidade_partido_sigla"
  echo "  duplicidade_demografia=$duplicidade_demografia"
  exit 1
fi

echo "[validate_db] All validations passed"
