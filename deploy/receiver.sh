#!/usr/bin/env bash
# Reference deploy receiver. Reads a signed webhook body on stdin, pulls the
# image the workflow just published, restarts the stack and reports back.
#
# Wire it to a one-line HTTP endpoint of your choosing, for example:
#   socat TCP-LISTEN:9000,fork EXEC:"deploy/receiver.sh"
# or a systemd socket unit, or a two-line nginx + fcgiwrap location. What
# matters is that the raw body reaches stdin and X-Kit-Signature reaches the
# environment as HTTP_X_KIT_SIGNATURE.
#
# Required environment:
#   DEPLOY_WEBHOOK_SECRET  shared with the GitHub workflow
#   KIT_IMAGE              ghcr.io/<org>/<repo>
#   COMPOSE_FILE           path to deploy/compose.prod.yml
#   GITHUB_TOKEN           optional, only to post the deployment status back
set -euo pipefail

: "${DEPLOY_WEBHOOK_SECRET:?}"
: "${COMPOSE_FILE:=/srv/kit/compose.prod.yml}"

body="$(cat)"

# Verify before parsing: an unsigned body must never reach jq, let alone
# docker. Constant-time compare so a wrong signature leaks no timing.
expected="sha256=$(printf '%s' "$body" | openssl dgst -sha256 -hmac "$DEPLOY_WEBHOOK_SECRET" -r | cut -d' ' -f1)"
received="${HTTP_X_KIT_SIGNATURE:-}"

if [ "${#received}" -ne "${#expected}" ] || ! printf '%s' "$received" | cmp -s - <(printf '%s' "$expected"); then
  echo "receiver: bad signature" >&2
  exit 1
fi

environment="$(printf '%s' "$body" | jq -r '.environment')"
image="$(printf '%s' "$body" | jq -r '.image')"
tag="$(printf '%s' "$body" | jq -r '.tag')"
status_url="$(printf '%s' "$body" | jq -r '.statusUrl // empty')"

echo "receiver: deploying ${image} to ${environment}"

report() {
  [ -n "$status_url" ] || return 0
  [ -n "${GITHUB_TOKEN:-}" ] || return 0
  curl -fsS -X POST "$status_url" \
    -H "Authorization: Bearer ${GITHUB_TOKEN}" \
    -H 'Accept: application/vnd.github+json' \
    -d "{\"state\":\"$1\",\"description\":\"$2\"}" >/dev/null || true
}

trap 'report failure "deploy failed"' ERR

export KIT_IMAGE="$image" KIT_IMAGE_TAG="$tag"

docker compose -f "$COMPOSE_FILE" pull
docker compose -f "$COMPOSE_FILE" up -d --wait

# Migrations run from the entrypoint too, but only when APP_VERSION changed.
# Running them here as well makes a hotfix that keeps the tag still land.
docker compose -f "$COMPOSE_FILE" exec -T app php artisan migrate --force

# Both hold code in memory across the release: terminate so the new image's
# workers pick the jobs up, and restart so subscribers reconnect to it.
docker compose -f "$COMPOSE_FILE" exec -T app php artisan horizon:terminate
docker compose -f "$COMPOSE_FILE" exec -T app php artisan reverb:restart

trap - ERR
report success "deployed ${tag}"
echo "receiver: ${tag} is live"

# Rollback is the same call with the previous tag:
#   KIT_IMAGE_TAG=v2026.9.1-abc1234 docker compose -f "$COMPOSE_FILE" up -d --wait
