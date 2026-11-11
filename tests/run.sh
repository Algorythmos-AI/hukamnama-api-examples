#!/bin/sh
# Run every command-line example against the stand-in API, in its three modes.
#   sh tests/run.sh            (needs python3, node 18+, curl, jq; swift is used when installed)
set -eu
cd "$(dirname "$0")/.."
PORT="${PORT:-8787}"
export HUKAMNAMA_API="http://127.0.0.1:$PORT"
fail=0
say() { printf '%s\n' "$*"; }
start() { python3 tools/mock_api.py --port "$PORT" --mode "$1" >/dev/null 2>&1 & MOCK=$!; for _ in 1 2 3 4 5 6 7 8 9 10; do curl -fsS "$HUKAMNAMA_API/v1/status" >/dev/null 2>&1 && return 0; sleep 0.3; done; say "mock did not start"; exit 1; }
stop() { kill "$MOCK" 2>/dev/null || true; wait "$MOCK" 2>/dev/null || true; }
trap stop EXIT

EXAMPLES="python:python3 python/hukamnama.py|node:node node/today.mjs|curl:sh curl/today.sh"
if command -v swift >/dev/null 2>&1 && [ "${SKIP_SWIFT:-0}" != 1 ]; then EXAMPLES="$EXAMPLES|swift:swift swift/Hukamnama.swift"; fi

# What must be printed: every line of the fixture, in order, exactly.
python3 -c "import json; print('\n'.join(l['gurmukhi'] for l in json.load(open('fixtures/today.json', encoding='utf-8'))['lines']))" > /tmp/hukamnama-want.$$

run() {   # mode, expected exit code, must-the-lines-be-there (yes|no)
  start "$1"
  old_ifs=$IFS; IFS='|'
  for e in $EXAMPLES; do
    IFS=$old_ifs
    name=${e%%:*}; cmd=${e#*:}
    set +e; out=$($cmd 2>/tmp/hukamnama-err.$$); code=$?; set -e
    ok=yes
    [ "$code" = "$2" ] || ok=no
    if [ "$3" = yes ]; then
      printf '%s\n' "$out" | python3 -c "import sys; want=open(sys.argv[1], encoding='utf-8').read().strip(); sys.exit(0 if want in sys.stdin.read() else 1)" /tmp/hukamnama-want.$$ || ok=no
      printf '%s' "$out" | grep -q "Sri Guru Granth Sahib Ji · Ang 680" || ok=no
      printf '%s' "$out" | grep -q "via Gurbani Soul" || ok=no
      if printf '%s' "$out" | grep -q "Today"; then ok=no; fi      # the fixture's date is in the past
    else
      first=$(sed -n 2p /tmp/hukamnama-want.$$)
      if printf '%s' "$out" | grep -qF "$first"; then ok=no; fi    # not one line may be printed
    fi
    if [ $ok = yes ]; then say "ok    $1: $name"; else say "FAIL  $1: $name (exit $code)"; fail=1; fi
    IFS='|'
  done
  IFS=$old_ifs
  stop
}

run published 0 yes     # today's is published: the whole shabad, the citation, the credit
run pending 0 yes       # not published yet: the latest one, under its own date
run tampered 1 no       # one altered line: nothing is shown, exit 1

rm -f /tmp/hukamnama-want.$$ /tmp/hukamnama-err.$$
[ $fail = 0 ] && say "all examples passed" || { say "some examples failed"; exit 1; }
