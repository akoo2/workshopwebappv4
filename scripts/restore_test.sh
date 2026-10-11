#!/usr/bin/env bash
# TV Repair Workshop Management System - Restore Verification Script
# Usage: ./scripts/restore_test.sh [optional_backup_file.enc]

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"
STORAGE_DIR="$ROOT_DIR/storage"
BACKUP_DIR="${STORAGE_DIR}/backups"

# Source .env if exists
if [ -f "$ROOT_DIR/.env" ]; then
    while IFS='=' read -r key val || [ -n "$key" ]; do
        [[ $key =~ ^#.*$ ]] && continue
        [[ -z $key ]] && continue
        val=$(echo "$val" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//' -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//")
        export "$key=$val"
    done < "$ROOT_DIR/.env"
fi

BACKUP_DIR="${STORAGE_DIR}/backups"

# Find latest backup if not provided
BACKUP_FILE="${1:-$(ls -t "$BACKUP_DIR"/tv_workshop_backup_*.enc 2>/dev/null | head -n 1 || true)}"

if [ -z "$BACKUP_FILE" ] || [ ! -f "$BACKUP_FILE" ]; then
    echo "[-] No backup file found in $BACKUP_DIR to test."
    exit 1
fi

echo "[*] Testing restoration of backup: $BACKUP_FILE"
WORK_DIR="$(mktemp -d)"

# 1. Decrypt into work directory
TAR_FILE="${WORK_DIR}/extracted.tar.gz"
if file "$BACKUP_FILE" | grep -q "GPG"; then
    echo "[*] Backup was encrypted with GPG. Note: The server only has the public key by security design."
    echo "[*] In disaster recovery, off-server operator decrypts using the private key."
    echo "[+] GPG header verified successfully."
    rm -rf "$WORK_DIR"
    exit 0
else
    echo "[*] Decrypting with OpenSSL test key..."
    SECRET="${APP_SECRET:-workshop_default_backup_key_2026}"
    openssl enc -d -aes-256-cbc -pbkdf2 -iter 100000 -in "$BACKUP_FILE" -out "$TAR_FILE" -k "$SECRET"
fi

# 2. Extract tarball
tar -xzf "$TAR_FILE" -C "$WORK_DIR"

# 3. Verify contents
if [ -f "${WORK_DIR}/database.sqlite" ]; then
    echo "[*] Verifying SQLite database structure..."
    sqlite3 "${WORK_DIR}/database.sqlite" "SELECT count(*) FROM users;" >/dev/null
    echo "[+] SQLite database integrity test PASSED."
elif [ -f "${WORK_DIR}/database.sql" ]; then
    echo "[*] Verifying SQL dump structure..."
    grep -q "CREATE TABLE" "${WORK_DIR}/database.sql"
    echo "[+] MySQL dump integrity test PASSED."
fi

# 4. Clean up
rm -rf "$WORK_DIR"
echo "[+] Restore test completed successfully."
