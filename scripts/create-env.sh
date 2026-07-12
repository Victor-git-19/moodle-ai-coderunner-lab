#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$ROOT_DIR/.env"
EXAMPLE_FILE="$ROOT_DIR/.env.example"

if [[ -f "$ENV_FILE" ]]; then
    echo ".env already exists; nothing changed."
    exit 0
fi

if [[ ! -f "$EXAMPLE_FILE" ]]; then
    echo "Missing .env.example" >&2
    exit 1
fi

generate_password() {
    if command -v openssl >/dev/null 2>&1; then
        openssl rand -hex 16
    else
        od -An -N16 -tx1 /dev/urandom | tr -d ' \n'
    fi
}

admin_password="$(generate_password)"
db_password="$(generate_password)"
db_root_password="$(generate_password)"

cp "$EXAMPLE_FILE" "$ENV_FILE"
sed -i.bak \
    -e "s/^MOODLE_ADMIN_PASSWORD=.*/MOODLE_ADMIN_PASSWORD=$admin_password/" \
    -e "s/^DB_PASSWORD=.*/DB_PASSWORD=$db_password/" \
    -e "s/^DB_ROOT_PASSWORD=.*/DB_ROOT_PASSWORD=$db_root_password/" \
    "$ENV_FILE"
rm -f "$ENV_FILE.bak"
chmod 600 "$ENV_FILE"

echo "Created .env"
echo "Moodle admin password: $admin_password"
echo "Save this password in a secure place."

