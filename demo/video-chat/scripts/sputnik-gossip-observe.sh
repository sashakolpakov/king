#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
videochat_dir="$(cd "${script_dir}/.." && pwd)"
backend_dir="${videochat_dir}/backend-king-php"
local_log_dir="${VIDEOCHAT_LOCAL_LOG_DIR:-${backend_dir}/.local/sputnik-deploy/logs}"
pattern="${SPUTNIK_GOSSIP_LOG_PATTERN:-sputnik|gossip|topology|neighbor|video-frame|frame|reconnect|disconnect|frozen|stale|drop|error|fail|health|telemetry}"

if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
  cd "${videochat_dir}"
  if docker compose -f docker-compose.v1.yml ps --services --filter status=running 2>/dev/null | grep -q '^videochat-'; then
    echo "[sputnik-gossip-observe] docker compose logs, pattern: ${pattern}"
    exec docker compose -f docker-compose.v1.yml logs -f --tail=200 videochat-backend-v1 videochat-backend-ws-v1 videochat-frontend-v1 \
      | grep -Eai --line-buffered "${pattern}"
  fi
fi

if compgen -G "${local_log_dir}/*.log" >/dev/null; then
  echo "[sputnik-gossip-observe] local logs: ${local_log_dir}, pattern: ${pattern}"
  exec tail -n 200 -F "${local_log_dir}"/*.log | grep -Eai --line-buffered "${pattern}"
fi

echo "[sputnik-gossip-observe] no compose services or local log files found" >&2
echo "Start with demo/video-chat/scripts/kingrt-sputnik.local.sh or demo/video-chat/scripts/sputnik-gossip-compose.sh" >&2
exit 2
