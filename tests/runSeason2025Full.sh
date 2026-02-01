#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

php "${ROOT_DIR}/tests/season2025ResetAndSeedTest.php" --apply=1 --year=2025
php "${ROOT_DIR}/tests/season2025PickFlowTest.php" --apply=1 --weeks=18
