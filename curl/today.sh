#!/bin/sh
# The Hukamnama with curl and jq, checked against its hash.
#   sh curl/today.sh
# Needs curl, jq and shasum (or sha256sum).
set -eu
API="${HUKAMNAMA_API:-https://api.gurbanisoul.com}"
sha() { if command -v sha256sum >/dev/null 2>&1; then sha256sum; else shasum -a 256; fi | cut -d' ' -f1; }

day=$(curl -fsS "$API/v1/hukamnama/today")
if [ "$(printf '%s' "$day" | jq -r .status)" != "published" ]; then
  latest=$(printf '%s' "$day" | jq -r '.latest // empty')
  [ -n "$latest" ] || { echo "No Hukamnama is available yet." >&2; exit 2; }
  day=$(curl -fsS "$API/v1/hukamnama/$latest")      # shown under its own date, never as today's
fi

# The hash is over every line's gurmukhi joined by a newline, with no newline at the end.
got=$(printf '%s' "$day" | jq -j '[.lines[].gurmukhi] | join("\n")' | sha)
want=$(printf '%s' "$day" | jq -r .range_sha256)
[ "$got" = "$want" ] || { echo "The Hukamnama could not be verified, so it is not shown." >&2; exit 1; }

printf '%s' "$day" | jq -r '"Hukamnama · Sri Harmandir Sahib", "\(.amritsar_date) (Amritsar)", "", (.lines[].gurmukhi), "", .citation, "\(.attribution.text): \(.attribution.url)"'
