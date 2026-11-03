#!/usr/bin/env bash
set -euo pipefail
if [ $# -lt 1 ]; then
  echo "Usage: ./APPLY_PATCH_LINUX.sh /path/to/sales-dashboard"
  exit 1
fi
php "$(cd "$(dirname "$0")" && pwd)/apply_patch.php" "$1"
