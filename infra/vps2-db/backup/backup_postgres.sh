#!/usr/bin/env bash
set -euo pipefail

STAMP=$(date +%F_%H%M)
TARGET_DIR=/backup/dumps
mkdir -p "$TARGET_DIR"

pg_dump -h 127.0.0.1 -U app transporte | gzip > "$TARGET_DIR/transporte_${STAMP}.sql.gz"
find "$TARGET_DIR" -type f -name '*.sql.gz' -mtime +14 -delete
