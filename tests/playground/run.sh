#!/usr/bin/env bash
# Verify the Google Search Console connection inside a real WordPress: latest
# WP, PHP 8.3, the free plugin on its own, because that is all this feature
# sits on.
#
#   bash tests/playground/run.sh
#
# Prints the tally. The unit suite proves the arithmetic against a fake
# WordPress; this proves the wiring, which is where the risk is: a REST route
# that has to answer a signed-out browser, a handshake that crosses two
# requests, a token that has to come back out of libsodium, and a screen drawn
# by code that only exists inside wp-admin.
#
# ONLY activatePlugin AND runPHP STEPS. A `wp-cli` step runs the whole of WP-CLI
# inside the WASM build and blows its memory limit, which presents as the run
# ending with no report and nothing in the log that names the cause.
#
# NETWORKING IS OFF. Google and the relay are answered by `pre_http_request` in
# the mu-plugin, so a request that got out would be a bug rather than a test,
# and the probe asserts the list of addresses that were asked for.
set -u

here="$(cd "$(dirname "$0")" && pwd)"
free="$(cd "$here/../.." && pwd)"
work="$(mktemp -d)"

trap 'rm -rf "$work"' EXIT

mkdir -p "$work/out" "$work/stage"

cp -r "$free" "$work/stage/solseo"

# The zip holds none of this and neither should the mounted copy.
rm -rf "$work/stage/solseo/dist" "$work/stage/solseo/tests"

export MSYS_NO_PATHCONV=1 MSYS2_ARG_CONV_EXCL="*"

npx --yes @wp-playground/cli@latest run-blueprint \
  --php=8.3 --wp=latest \
  --blueprint="$(cygpath -m "$here/blueprint.json")" \
  --mount-dir "$(cygpath -m "$work/stage/solseo")" "/wordpress/wp-content/plugins/solseo" \
  --mount-dir "$(cygpath -m "$here/mu")" "/wordpress/wp-content/mu-plugins" \
  --mount-dir "$(cygpath -m "$here")" "/wordpress/review-tools" \
  --mount-dir "$(cygpath -m "$work/out")" "/wordpress/review-out" \
  --verbosity=debug > "$work/cli.log" 2>&1

report="$work/out/freec1.txt"

if [ ! -f "$report" ]; then
	echo "The probe wrote nothing. The Playground log is at $work/cli.log"
	cp "$work/cli.log" "$here/last-run-cli.log" 2>/dev/null || true
	tail -40 "$work/cli.log"
	exit 1
fi

cat "$report"

passed="$(grep -c '^PASS' "$report" || true)"
failed="$(grep -c '^FAIL' "$report" || true)"

echo "$passed passed, $failed failed"

if [ "$failed" != "0" ]; then
	exit 1
fi
