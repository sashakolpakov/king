# Sputnik Gossip Mesh Isolation Branch

Branch: `sputnik/gossip-mesh-isolation`

This branch isolates the Sputnik Gossip Mesh from the old server media-relay path while preserving useful call-shell pieces: frontend UI, background controls, local media capture helpers, audio helpers, room membership, chat/roster support, and the Sputnik dev peer harness.

## Boundary

Keep:

- Call workspace UI shell, layout CSS, roster/minis, controls, and diagnostics surfaces used to test Sputnik.
- Background filter UI and background processing modules.
- Native/audio helpers and Sputnik audio publication.
- Room membership, presence, auth/session, and websocket control plane.
- Server topology authority for admitted peers and reciprocal neighbor hints.
- Current bounded peer gossip data plane and Sputnik dev peer session tooling.

Remove or quarantine:

- Backend `/sfu` endpoint and server media relay/storage gateway.
- SFU-first media fallback decisions.
- Remote-video health/reconnect logic as a source of truth for gossip media.
- Frame freshness/continuity gates that globally reject gossip-delivered frames.
- Rollout modes that default back to server-first or mirror behavior.

## Current State

Completed in this branch:

- Backend `/sfu` route and SFU gateway/store/session modules were removed from the active realtime bootstrap.
- Room-state topology payloads now advertise `gossip_primary` only for media carriers.
- Gossip data lane defaults to `active` when no env override is set.
- Media carrier defaults to `gossip_primary` when no env override is set.
- Frontend gossip video frame command type is `gossip/video-frame`, not `sfu/frame`.
- The accidental alias `handleGossipEncodedFrame: handleSFUEncodedFrame` was removed from the active media stack return path.
- Protected media runtime path was moved from `wlvc_sfu` to `wlvc_gossip` for gossip frames.
- Shared base64 payload helpers used by Sputnik/gossip were moved out of `lib/sfu/framePayload` into neutral `lib/media/base64Payload`.
- Docker compose no longer starts a dedicated backend SFU websocket service.
- Local Sputnik launcher runs HTTP plus one WS service, with WS workers defaulting to `36`.
- Local compose launcher and telemetry observer scripts were added for repeatable debugging.

## Current Runtime Shape

Server head:

- Authenticates users and Sputnik dev peers.
- Maintains room membership.
- Emits reciprocal topology hints and repair hints.
- Accepts control-plane telemetry snapshots.
- Rejects normal media payloads on the control websocket.
- Does not decide gossip frame freshness.
- Does not relay normal video frames as server fanout.

Peers:

- Publish video/audio frames to the gossip data lane.
- Relay frames across assigned bidirectional neighbor links.
- Track duplicate/frame history locally.
- Decide local freshness/render behavior from local arrival/render history.

## Local Launch

Use the direct local launcher when debugging without Docker:

```bash
demo/video-chat/scripts/kingrt-sputnik.local.sh
```

Defaults:

- HTTP: `127.0.0.1:18080`, 1 worker.
- WS: `127.0.0.1:18081/ws`, 36 workers.
- Frontend: `127.0.0.1:5176`.
- Gossip data lane: `active`.
- Media carrier: `gossip_primary`.
- Frontend SFU flag: `false`.

Use compose when testing the deploy shape:

```bash
demo/video-chat/scripts/sputnik-gossip-compose.sh
```

Observe relevant telemetry/log lines:

```bash
demo/video-chat/scripts/sputnik-gossip-observe.sh
```

Override workers if needed:

```bash
VIDEOCHAT_LOCAL_WS_WORKERS=48 demo/video-chat/scripts/kingrt-sputnik.local.sh
VIDEOCHAT_V1_WS_WORKERS=48 demo/video-chat/scripts/sputnik-gossip-compose.sh
```

## Telemetry To Watch Tomorrow

Useful filters are already included in `sputnik-gossip-observe.sh`:

- `topology`, `neighbor`, `repair`: topology assignment and repair churn.
- `video-frame`, `frame`, `drop`: frame delivery and drop behavior.
- `reconnect`, `disconnect`, `frozen`: peer transport instability.
- `telemetry`, `health`: client/server snapshots.
- `error`, `fail`: hard failures.

## Remaining Couplings

These are known and should be rewired next, not hidden:

- `CallWorkspaceView.vue` and `mediaStack.ts` still import SFU-named frontend helper modules for useful WLVC encode/decode/render code. They are now server-relay-disabled, but names remain.
- `sputnikPeerRuntime.ts` still uses the old local publisher pipeline shape, which has SFU-named parameters even when publishing to gossip.
- `src/lib/sfu` and `src/domain/realtime/sfu` still contain reusable WLVC/browser media code mixed with old SFU client code. The next clean step is to split reusable media code into neutral `media`/`gossip` modules, then delete the true client/transport remnants.
- Legacy deployment script paths still contain older multi-host SFU terms; use the new Sputnik gossip scripts for this branch until the production deploy script is fully rewritten.

## Design Rule

The server head remains a control-plane authority only:

- identity
- admission
- room membership
- topology hints
- optional non-media diagnostics

Peers own:

- frame publication
- frame relay
- local freshness/render decisions
- audio/video delivery over peer links

No server media fanout should be reintroduced on this branch.
