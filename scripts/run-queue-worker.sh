#!/usr/bin/env bash
set -euo pipefail

attempt=0

queue_args=(--queue=default,reminders --tries=3 --sleep=1 --max-time=0)
if [ "${APP_ENV:-}" = "local" ]; then
  # Local dev: reload between jobs so code changes take effect without queue:restart.
  queue_args+=(--max-jobs=1)
fi

while true; do
  php artisan queue:work "${queue_args[@]}" && attempt=0

  exit_code=$?
  attempt=$((attempt + 1))
  delay=$((attempt < 7 ? 2 ** attempt : 120))

  echo "queue-worker: artisan queue:work exited with $exit_code; retrying in ${delay}s" >&2
  sleep "$delay"
done
