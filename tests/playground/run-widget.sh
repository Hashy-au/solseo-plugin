#!/usr/bin/env bash
# Draw the dashboard widget inside a real WordPress: latest WP, PHP 8.3, the
# free plugin on its own.
#
#   bash tests/playground/run-widget.sh
#
# Prints the tally. The unit suite proves which site the widget picks against a
# fake WordPress; this proves it draws, because a dashboard widget is registered
# by a hook that only fires on the Dashboard and its view calls functions that
# only exist once wp-admin has loaded.
#
# NO mu-plugin AND NETWORKING IS OFF, deliberately. The widget reads the
# transient the twice daily sync fills and must make no request of its own, so a
# request that got out would hang or error and show up in the log the probe
# reads.
#
# ONLY activatePlugin AND runPHP STEPS. A `wp-cli` step runs the whole of WP-CLI
# inside the WASM build and blows its memory limit, which presents as the run
# ending with no report and nothing in the log that names the cause.
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
  --blueprint="$(cygpath -m "$here/blueprint-widget.json")" \
  --mount-dir "$(cygpath -m "$work/stage/solseo")" "/wordpress/wp-content/plugins/solseo" \
  --mount-dir "$(cygpath -m "$here")" "/wordpress/review-tools" \
  --mount-dir "$(cygpath -m "$work/out")" "/wordpress/review-out" \
  --verbosity=debug > "$work/cli.log" 2>&1

report="$work/out/widget.txt"

if [ ! -f "$report" ]; then
	echo "The probe wrote nothing. The Playground log is at $work/cli.log"
	cp "$work/cli.log" "$here/last-widget-cli.log" 2>/dev/null || true
	tail -40 "$work/cli.log"
	exit 1
fi

cat "$report"

failed="$(grep -c '^FAIL' "$report" || true)"

if [ "$failed" != "0" ]; then
	exit 1
fi
