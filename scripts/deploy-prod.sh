#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE_FILE="$ROOT_DIR/docker-compose.prod.yml"
ENV_FILE="$ROOT_DIR/backend/.env.production"
NETWORK="personnelplanner_prod_network"
TUNNEL_CONTAINER="cf-tunnel"

if ! command -v docker >/dev/null 2>&1; then
  echo "Error: docker is not installed or not in PATH." >&2
  exit 1
fi

if [ ! -f "$ENV_FILE" ]; then
  echo "Error: $ENV_FILE not found. Copy backend/.env.production.example and fill in the blanks." >&2
  exit 1
fi

if grep -q '^MAIL_MAILER=smtp' "$ENV_FILE" && grep -q '^MAIL_PASSWORD=$' "$ENV_FILE"; then
  echo "Error: MAIL_PASSWORD (the Resend API key) is empty in $ENV_FILE. Add it, or set MAIL_MAILER=log." >&2
  exit 1
fi

compose() {
  docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" "$@"
}

echo "Starting production deploy..."
compose up -d --build --remove-orphans

echo
echo "Waiting for backend health..."
for i in $(seq 1 30); do
  backend_health="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' personnelplanner_backend 2>/dev/null || true)"
  if [ "$backend_health" = "healthy" ]; then
    break
  fi
  sleep 2
done

echo "Running migrations..."
compose exec -T backend php artisan migrate --force

# The shared Cloudflare Tunnel reaches personnelplanner_nginx over this stack's network.
if docker inspect "$TUNNEL_CONTAINER" >/dev/null 2>&1; then
  if ! docker inspect -f '{{range $name, $_ := .NetworkSettings.Networks}}{{$name}} {{end}}' "$TUNNEL_CONTAINER" | grep -qw "$NETWORK"; then
    echo "Connecting $TUNNEL_CONTAINER to $NETWORK..."
    docker network connect "$NETWORK" "$TUNNEL_CONTAINER"
  fi
else
  echo "Warning: $TUNNEL_CONTAINER is not running; the site is not reachable from outside." >&2
fi

echo
echo "Production services:"
compose ps
