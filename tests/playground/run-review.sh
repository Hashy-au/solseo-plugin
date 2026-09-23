#!/usr/bin/env bash
# Answer the WordPress.org review of solseo-2.0.0 inside a real WordPress:
# latest WP, PHP 8.3, WP_DEBUG on, the free plugin on its own.
#
#   bash tests/playground/run-review.sh
#
# Prints the tally. The unit suite pins the code that decides each finding; this
# runs the decisions on a live install, which is the only place "the stylesheet
# is not on the media library screen" and "a title cannot close the script
# element" are facts rather than readings of the source.
#
# ONLY activatePlugin AND runPHP STEPS, for the reason run.sh gives: a wp-cli
# step runs the whole of WP-CLI inside the WASM build and blows its memory
# limit, which presents as the run ending with no report and nothing in the log
# that names the cause.
#
# TWO runPHP STEPS, because is_admin() is settled before WordPress loads. The
# asset gating only exists on an admin request and the head output only on one
# that is not, so each phase is its own request.
#
# NETWORKING IS OFF, and the mu-plugin records anything that tries, so the
# "activation contacts nobody" check is a list rather than an assumption.
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
  --blueprint="$(cygpath -m "$here/blueprint-review.json")" \
  --mount-dir "$(cygpath -m "$work/stage/solseo")" "/wordpress/wp-content/plugins/solseo" \
  --mount-dir "$(cygpath -m "$here/mu-review")" "/wordpress/wp-content/mu-plugins" \
  --mount-dir "$(cygpath -m "$here")" "/wordpress/review-tools" \
  --mount-dir "$(cygpath -m "$work/out")" "/wordpress/review-out" \
  --verbosity=debug > "$work/cli.log" 2>&1

missing=0

for phase in front admin; do
	if [ ! -f "$work/out/review-$phase.txt" ]; then
		echo "The $phase probe wrote nothing."
		missing=1
	fi
done

if [ "$missing" != "0" ]; then
	echo "The Playground log is at $here/last-review-cli.log"
	cp "$work/cli.log" "$here/last-review-cli.log" 2>/dev/null || true
	tail -60 "$work/cli.log"
	exit 1
fi

cat "$work/out/review-front.txt" "$work/out/review-admin.txt"

passed="$(cat "$work/out/review-"*.txt | grep -c '^PASS' || true)"
failed="$(cat "$work/out/review-"*.txt | grep -c '^FAIL' || true)"

echo
echo "$passed passed, $failed failed"

if [ "$failed" != "0" ]; then
	exit 1
fi
