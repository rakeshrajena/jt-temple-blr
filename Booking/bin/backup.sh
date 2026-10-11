#!/usr/bin/env bash
# Daily backup of the booking database and uploaded files.
#   bash bin/backup.sh            (run from cron, see docs/BACKUP-RECOVERY.md)
# Creates storage/backups/sjt-db-YYYYmmdd-HHMM.sql.gz and sjt-files-*.tar.gz,
# and deletes backups older than KEEP_DAYS (default 30).
set -euo pipefail
APP="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$APP/storage/backups"
KEEP_DAYS="${KEEP_DAYS:-30}"
STAMP="$(date +%Y%m%d-%H%M)"
umask 077
mkdir -p "$OUT"

envval() { grep -E "^$1=" "$APP/.env" | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"; }
DRIVER="$(envval DB_DRIVER || true)"

if [ "${DRIVER:-mysql}" = "sqlite" ]; then
  DBFILE="$(envval DB_PATH)"; case "$DBFILE" in /*) ;; *) DBFILE="$APP/$DBFILE";; esac
  php -r '$p=new PDO("sqlite:".$argv[1]); $p->exec("VACUUM INTO ".$p->quote($argv[2]));' "$DBFILE" "$OUT/sjt-db-$STAMP.sqlite"
  gzip "$OUT/sjt-db-$STAMP.sqlite"
else
  # Credentials go in a private temporary file, never on the command line.
  CNF="$(mktemp)"; trap 'rm -f "$CNF"' EXIT
  printf '[client]\nuser=%s\npassword="%s"\nhost=%s\nport=%s\n' "$(envval DB_USER)" "$(envval DB_PASS)" "$(envval DB_HOST)" "$(envval DB_PORT)" > "$CNF"
  mysqldump --defaults-extra-file="$CNF" --single-transaction --routines --no-tablespaces --default-character-set=utf8mb4 "$(envval DB_NAME)" \
    | gzip > "$OUT/sjt-db-$STAMP.sql.gz"
fi

tar -czf "$OUT/sjt-files-$STAMP.tar.gz" -C "$APP" storage/uploads .env 2>/dev/null || tar -czf "$OUT/sjt-files-$STAMP.tar.gz" -C "$APP" storage/uploads
find "$OUT" -name 'sjt-*' -type f -mtime +"$KEEP_DAYS" -delete
echo "Backup written: $OUT/sjt-db-$STAMP.*"
