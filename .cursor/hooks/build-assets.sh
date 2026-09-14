#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

python3 - "$ROOT" <<'PY'
import json
import os
import subprocess
import sys
import time
from pathlib import Path

root = Path(sys.argv[1]).resolve()
raw = sys.stdin.read().strip() or "{}"

try:
    payload = json.loads(raw)
except json.JSONDecodeError:
    payload = {}

file_path = str(payload.get("file_path") or "")
relative = os.path.relpath(file_path, root) if file_path else ""
normalized = relative.replace("\\", "/")

asset_prefixes = (
    "resources/js/",
    "resources/css/",
)

asset_names = {
    "vite.config.ts",
    "vite.config.js",
    "tsconfig.json",
    "tsconfig.app.json",
}

is_asset = normalized in asset_names or any(
    normalized.startswith(prefix) for prefix in asset_prefixes
)

if not is_asset or normalized.startswith("public/build/"):
    sys.exit(0)

stamp_path = root / ".cursor" / "hooks" / ".build-request"
stamp_path.parent.mkdir(parents=True, exist_ok=True)
mine = f"{time.time_ns()}"
stamp_path.write_text(mine)
time.sleep(2)

if stamp_path.read_text().strip() != mine:
    sys.exit(0)

print(f"Building frontend assets after change to {normalized}", file=sys.stderr)
result = subprocess.run(["npm", "run", "build"], cwd=root)
sys.exit(result.returncode)
PY
