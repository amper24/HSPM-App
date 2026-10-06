#!/usr/bin/env bash
set -euo pipefail
cd -- "$(dirname -- "${BASH_SOURCE[0]}")"
if [ ! -f .env ]; then cp .env.example .env; fi
echo 'Заполните DB_PASSWORD, MYSQL_ROOT_PASSWORD и ADMIN_PASSWORD (не менее 12 символов) в .env.'
echo 'Затем выполните команды из README.md. setup.sh не меняет существующую базу.'
