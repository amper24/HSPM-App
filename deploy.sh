#!/usr/bin/env bash
set -euo pipefail
cd -- "$(dirname -- "${BASH_SOURCE[0]}")"
test -f .env || { echo 'Создайте .env по README.md'; exit 1; }
docker compose build
docker compose up -d
docker compose ps
