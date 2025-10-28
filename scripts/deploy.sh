#!/usr/bin/env bash
set -euo pipefail

REMOTE_USER="${CPANEL_USER:-}"
REMOTE_HOST="${CPANEL_HOST:-}"
REMOTE_PATH="${CPANEL_PATH:-}"

if [[ -z "$REMOTE_USER" || -z "$REMOTE_HOST" || -z "$REMOTE_PATH" ]]; then
  echo "[deploy] 請先設定 CPANEL_USER、CPANEL_HOST、CPANEL_PATH 環境變數" >&2
  echo "範例：export CPANEL_USER=myuser" >&2
  echo "        export CPANEL_HOST=example.com" >&2
  echo "        export CPANEL_PATH=/home/myuser/public_html/accounting" >&2
  exit 1
fi

REMOTE="${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_PATH}"

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SOURCE_PATH="${PROJECT_ROOT}/public_html/accounting/"

if [[ ! -d "$SOURCE_PATH" ]]; then
  echo "[deploy] 找不到 public_html/accounting/" >&2
  exit 1
fi

rsync -avz \
  --delete \
  --exclude '.git/' \
  --exclude '.DS_Store' \
  "$SOURCE_PATH" "$REMOTE"

echo "[deploy] 已同步 public_html/accounting 到 ${REMOTE}" 
