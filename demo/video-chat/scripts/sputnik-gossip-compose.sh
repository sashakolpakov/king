#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
videochat_dir="$(cd "${script_dir}/.." && pwd)"

export VIDEOCHAT_V1_HTTP_WORKERS="${VIDEOCHAT_V1_HTTP_WORKERS:-24}"
export VIDEOCHAT_V1_WS_WORKERS="${VIDEOCHAT_V1_WS_WORKERS:-36}"
export VITE_VIDEOCHAT_ENABLE_SFU="${VITE_VIDEOCHAT_ENABLE_SFU:-false}"
export VITE_VIDEOCHAT_GOSSIP_DATA_LANE="${VITE_VIDEOCHAT_GOSSIP_DATA_LANE:-active}"
export VITE_VIDEOCHAT_MEDIA_CARRIER="${VITE_VIDEOCHAT_MEDIA_CARRIER:-gossip_primary}"
export VITE_VIDEOCHAT_ENABLE_SPUTNIK_PEERS="${VITE_VIDEOCHAT_ENABLE_SPUTNIK_PEERS:-true}"
export VITE_VIDEOCHAT_ENABLE_SPUTNIK_HELL="${VITE_VIDEOCHAT_ENABLE_SPUTNIK_HELL:-true}"
export VIDEOCHAT_OTEL_LOGS_ENABLE="${VIDEOCHAT_OTEL_LOGS_ENABLE:-1}"
export VIDEOCHAT_OTEL_METRICS_ENABLE="${VIDEOCHAT_OTEL_METRICS_ENABLE:-1}"

cd "${videochat_dir}"

echo "[sputnik-gossip-compose] http workers=${VIDEOCHAT_V1_HTTP_WORKERS} ws workers=${VIDEOCHAT_V1_WS_WORKERS}"
echo "[sputnik-gossip-compose] media carrier=${VITE_VIDEOCHAT_MEDIA_CARRIER} data lane=${VITE_VIDEOCHAT_GOSSIP_DATA_LANE} sfu=${VITE_VIDEOCHAT_ENABLE_SFU}"

docker compose --env-file .env --env-file .env.local -f docker-compose.v1.yml up -d --build --remove-orphans \
  videochat-backend-v1 \
  videochat-backend-ws-v1 \
  videochat-frontend-v1

echo "[sputnik-gossip-compose] frontend: http://127.0.0.1:${VIDEOCHAT_V1_FRONTEND_PORT:-5176}/"
echo "[sputnik-gossip-compose] ws:       ${VIDEOCHAT_V1_BACKEND_WS_ORIGIN:-http://127.0.0.1:${VIDEOCHAT_V1_BACKEND_WS_PORT:-18081}}/ws"
