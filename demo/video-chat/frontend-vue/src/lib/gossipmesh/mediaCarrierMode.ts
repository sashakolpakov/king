export type VideochatMediaCarrierMode = 'gossip_primary' | 'server_first' | 'server_mirror'

export interface VideochatMediaCarrierConfig {
  envKey: 'VITE_VIDEOCHAT_MEDIA_CARRIER'
  mode: VideochatMediaCarrierMode
  gossipPrimary: boolean
  serverFirst: boolean
  serverMirror: boolean
  gossipMayPublishWithoutServer: boolean
  serverMediaRequiredBeforeGossip: boolean
  serverMediaSendIsOptional: boolean
  serverMediaFallbackAllowed: boolean
  diagnosticsLabel: 'media_carrier_gossip_primary' | 'media_carrier_server_first' | 'media_carrier_server_mirror'
}

export const VIDEOCHAT_MEDIA_CARRIER_ENV_KEY = 'VITE_VIDEOCHAT_MEDIA_CARRIER'

export function normalizeVideochatMediaCarrierMode(value: unknown): VideochatMediaCarrierMode {
  const normalized = String(value || '').trim().toLowerCase()
  if (normalized === '') return 'gossip_primary'
  if (normalized === 'gossip_primary' || normalized === 'gossip-primary' || normalized === 'gossip') {
    return 'gossip_primary'
  }
  if (normalized === 'server_mirror' || normalized === 'server-mirror' || normalized === 'mirror') {
    return 'server_mirror'
  }
  return 'server_first'
}

export function resolveVideochatMediaCarrierConfig(env: Record<string, unknown> = import.meta.env): VideochatMediaCarrierConfig {
  const mode = normalizeVideochatMediaCarrierMode(env[VIDEOCHAT_MEDIA_CARRIER_ENV_KEY])
  const gossipPrimary = mode === 'gossip_primary'
  const serverMirror = mode === 'server_mirror'
  const serverFirst = mode === 'server_first'
  return {
    envKey: VIDEOCHAT_MEDIA_CARRIER_ENV_KEY,
    mode,
    gossipPrimary,
    serverFirst,
    serverMirror,
    gossipMayPublishWithoutServer: gossipPrimary,
    serverMediaRequiredBeforeGossip: !gossipPrimary,
    serverMediaSendIsOptional: gossipPrimary || serverMirror,
    serverMediaFallbackAllowed: false,
    diagnosticsLabel: gossipPrimary
      ? 'media_carrier_gossip_primary'
      : serverMirror
        ? 'media_carrier_server_mirror'
        : 'media_carrier_server_first',
  }
}

export const VIDEOCHAT_MEDIA_CARRIER_CONFIG = Object.freeze(resolveVideochatMediaCarrierConfig())
