#!/usr/bin/env bash
# Restore the booking database from a backup made by bin/backup.sh.
#   bash bin/restore.sh storage/backups/sjt-db-20261010-0230.sql.gz
# This REPLACES the current booking data. A safety backup is taken first.
set -euo pipefail
APP="$(cd "$(dirname "$0")/.." && pwd)"
FILE="${1:?Usage: bin/restore.sh <backup file>}"
[ -f "$FILE" ] || { echo "No such file: $FILE"; exit 1; }
envval() { grep -E "^$1=" "$APP/.env" | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"; }

read -r -p "This replaces ALL current booking data with $FILE. Type RESTORE to continue: " ans
[ "$ans" = "RESTORE" ] || { echo "Cancelled."; exit 1; }

echo "Taking a safety backup of the current data first..."
bash "$APP/bin/backup.sh"

if [ "$(envval DB_DRIVER)" = "sqlite" ]; then
  DBFILE="$(envval DB_PATH)"; case "$DBFILE" in /*) ;; *) DBFILE="$APP/$DBFILE";; esac
  gunzip -c "$FILE" > "$DBFILE.restoring" && mv "$DBFILE.restoring" "$DBFILE"
else
  CNF="$(mktemp)"; trap 'rm -f "$CNF"' EXIT
  printf '[client]\nuser=%s\npassword="%s"\nhost=%s\nport=%s\n' "$(envval DB_USER)" "$(envval DB_PASS)" "$(envval DB_HOST)" "$(envval DB_PORT)" > "$CNF"
  gunzip -c "$FILE" | mysql --defaults-extra-file="$CNF" --default-character-set=utf8mb4 "$(envval DB_NAME)"
fi
php "$APP/bin/console.php" migrate
echo "Restore complete. Check the admin dashboard."
