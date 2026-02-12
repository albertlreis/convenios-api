#!/usr/bin/env bash

set -euo pipefail

APP_DIR="/var/www/html"
STORAGE_DIR="${APP_DIR}/storage"
CACHE_DIR="${APP_DIR}/bootstrap/cache"
WWW_UID="${WWWUSER:-1000}"
WWW_GID="${WWWGROUP:-1000}"

mkdir -p \
  "${STORAGE_DIR}/app/private/imports/convenios" \
  "${STORAGE_DIR}/framework/cache" \
  "${STORAGE_DIR}/framework/sessions" \
  "${STORAGE_DIR}/framework/views" \
  "${CACHE_DIR}"

if ! chown -R "${WWW_UID}:${WWW_GID}" "${STORAGE_DIR}" "${CACHE_DIR}" 2>/dev/null; then
  echo "[entrypoint] Aviso: falha ao ajustar owner em storage/bootstrap-cache (bind mount pode restringir chown)." >&2
fi

chmod -R ug+rwX "${STORAGE_DIR}" "${CACHE_DIR}" || true

exec /usr/local/bin/start-container "$@"

