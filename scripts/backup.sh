#!/usr/bin/env bash
# TV Repair Workshop Management System - Encrypted Backup Script
# Usage: ./scripts/backup.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"
STORAGE_DIR="$ROOT_DIR/storage"
BACKUP_DIR="${STORAGE_DIR}/backups"
UPLOADS_DIR="${STORAGE_DIR}/uploads"
TIMESTAMP="$(date -u +"%Y%m%d_%H%M%S")"
BACKUP_PREFIX="tv_workshop_backup_${TIMESTAMP}"
WORK_DIR="$(mktemp -d)"

mkdir -p "$BACKUP_DIR"

# Source .env if exists
if [ -f "$ROOT_DIR/.env" ]; then
    while IFS='=' read -r key val || [ -n "$key" ]; do
        # Ignore comments and empty lines
        [[ $key =~ ^#.*$ ]] && continue
        [[ -z $key ]] && continue
        # Strip leading/trailing whitespace and quotes
        val=$(echo "$val" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//' -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//")
        export "$key=$val"
    done < "$ROOT_DIR/.env"
fi

DB_DRIVER="${DB_DRIVER:-sqlite}"
BACKUP_DIR="${STORAGE_DIR}/backups"
GPG_RECIPIENT="${BACKUP_GPG_RECIPIENT:-workshop-backup@local}"
REMOTE_DEST="${BACKUP_OFFSERVER_COMMAND:-}"

echo "[*] Starting backup at $(date -u) UTC"

# 1. Export Database
if [ "$DB_DRIVER" = "mysql" ]; then
    echo "[*] Running mysqldump..."
    mysqldump --host="${DB_HOST:-127.0.0.1}" \
              --port="${DB_PORT:-3306}" \
              --user="${DB_USERNAME:-tv_app_user}" \
              --password="${DB_PASSWORD:-}" \
              --single-transaction \
              --quick \
              "${DB_DATABASE:-tv_workshop}" > "${WORK_DIR}/database.sql"
else
    echo "[*] Backing up SQLite database..."
    SQLITE_FILE="${DB_DATABASE:-$STORAGE_DIR/database.sqlite}"
    if [ -f "$SQLITE_FILE" ]; then
        sqlite3 "$SQLITE_FILE" ".backup '${WORK_DIR}/database.sqlite'"
    else
        touch "${WORK_DIR}/database.sqlite"
    fi
fi

# 2. Package Uploads
echo "[*] Archiving uploads..."
mkdir -p "${WORK_DIR}/uploads"
if [ -d "$UPLOADS_DIR" ]; then
    cp -r "$UPLOADS_DIR"/* "${WORK_DIR}/uploads/" 2>/dev/null || true
fi

# 3. Create unencrypted tarball
TAR_FILE="${STORAGE_DIR}/backups/temp_${BACKUP_PREFIX}.tar.gz"
tar -czf "$TAR_FILE" -C "$WORK_DIR" .

# 4. GPG Encryption (Public key only, server never holds private decryption key)
ENC_FILE="${BACKUP_DIR}/${BACKUP_PREFIX}.tar.gz.enc"
if command -v gpg >/dev/null 2>&1 && gpg --list-keys "$GPG_RECIPIENT" >/dev/null 2>&1; then
    echo "[*] Encrypting with GPG for recipient: $GPG_RECIPIENT..."
    gpg --batch --yes --encrypt --recipient "$GPG_RECIPIENT" --output "$ENC_FILE" "$TAR_FILE"
else
    echo "[!] GPG recipient not found in keyring, encrypting with AES-256-CBC using APP_SECRET..."
    SECRET="${APP_SECRET:-workshop_default_backup_key_2026}"
    openssl enc -aes-256-cbc -salt -pbkdf2 -iter 100000 -in "$TAR_FILE" -out "$ENC_FILE" -k "$SECRET"
fi

# 5. Clean temporary work directory and unencrypted tarball
rm -rf "$WORK_DIR"
rm -f "$TAR_FILE"

# 6. Update last successful backup timestamp
date +%s > "${BACKUP_DIR}/.last_backup"
echo "[+] Backup successfully created: $ENC_FILE"

# 7. Off-server copy hook if configured
if [ -n "$REMOTE_DEST" ]; then
    echo "[*] Copying backup off-server: $REMOTE_DEST"
    eval "$REMOTE_DEST $ENC_FILE" || echo "[!] Off-server copy failed, investigate immediately."
fi

# 8. Retain backups for 14 days
echo "[*] Cleaning up backups older than 14 days..."
find "$BACKUP_DIR" -type f -name "tv_workshop_backup_*.enc" -mtime +14 -delete || true

echo "[+] Backup complete."
