#!/usr/bin/env bash
set -euo pipefail
DB_HOST="127.0.0.1"
DB_PORT="3306"
DB_NAME="judacargo_local"
DB_USER="root"
DB_PASS="root"

OUT_DIR="backups/db"
mkdir -p "$OUT_DIR"
STAMP="$(date +%Y%m%d_%H%M%S)"
FILE="$OUT_DIR/${DB_NAME}_${STAMP}.sql"

mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" \
  --routines --triggers --single-transaction --default-character-set=utf8mb4 \
  "$DB_NAME" > "$FILE"

(ls -1t "$OUT_DIR"/*.sql | sed -e '1,7d' | xargs -I {} rm -f "{}") || true

TARGET_REPO="$HOME/Projects/judacargo/judacargo-accounting"
mkdir -p "$TARGET_REPO/backups/db"
cp -a "$FILE" "$TARGET_REPO/backups/db/"

cd "$TARGET_REPO"
git pull --rebase || true
git add backups/db
git commit -m "backup: ${DB_NAME} @ ${STAMP}" || true
git push || true

echo "Backup synced to $TARGET_REPO/backups/db/"
