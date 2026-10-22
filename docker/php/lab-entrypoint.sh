#!/bin/sh
set -eu

# Compose mounts the source tree at runtime, so create sessions after mounting.
mkdir -p .local/sessions

exec "$@"
