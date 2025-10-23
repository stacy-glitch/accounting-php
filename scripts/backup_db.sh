#!/usr/bin/env bash
set -euo pipefail

DB_HOST="127.0.0.1"
DB_PORT="8889"
DB_NAME="judacargo_local"
DB_USER="root"
DB_PASS="root"

if command -v brew >/dev/null 2>&1 && [ -x "$(brew --prefix)/opt/mysql-client/bin/mysqldump" ]; then
  DUMP="$(brew --prefix)/opt/mysql-client/bin/mysqldump"
elif [ -x /Applications/MAMP/Library/bin/mysqldump ]; then
  DUMP="/Applications/MAMP/Library/bin/mysqldump"
elif command -v mysqldump >/dev/null 2>&1; then
  DUMP="$(command -v mysqldump)"
else
  echo "ERROR: mysqldump not found." >&2
  exit 1
fi

OUT_DIR="backups/db"
mkdir -p "$OUT_DIR"
STAMP="$(date +%Y%m%d_%H%M%S)"
FILE="$OUT_DIR/${DB_NAME}_${STAMP}.sql"

"$DUMP" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" \
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
