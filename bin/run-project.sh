#!/usr/bin/env bash
# Analyses one corpus project with one Psalm checkout and writes <out>/<project>.json (+ .issues.json when ok).
# usage: run-project.sh <corpus.json> <project> <psalm dir> <major> <perf.php> <out dir> <work dir>
# Install steps mirror bin/perf/run-shard.sh of vimeo/psalm, so both tools analyse the same code.
set -euo pipefail

[ $# -eq 7 ] || { echo "usage: run-project.sh <corpus.json> <project> <psalm dir> <major> <perf.php> <out> <work>" >&2; exit 2; }
CORPUS=$1 NAME=$2 PSALM=$3 MAJOR=$4 PERF_PHP=$5 OUT=$6 WORK=$7
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
TIMEOUT_FACTOR=3

mkdir -p "$OUT" "$WORK"
PSALM="$(cd "$PSALM" && pwd -P)"
OUT="$(cd "$OUT" && pwd -P)"
WORK="$(cd "$WORK" && pwd -P)"
PROJ="$WORK/$NAME"
LOG="$WORK/$NAME.psalm.log"
REPORT="$WORK/$NAME.report.json"

field() { jq -r --arg p "$NAME" ".projects[\$p].$1 // empty" "$CORPUS"; }

REPO="$(field repo)" REF="$(field ref)" TAG="$(field tag)"
TIMEOUT=$(( $(field timeout) * TIMEOUT_FACTOR ))
MEMORY_LIMIT="$(jq -r '.memory_limit' "$CORPUS")"
PSALM_VERSION="$(php "$PSALM/psalm" --version 2>/dev/null | sed -n 's/^Psalm \([^ ]*\).*/\1/p' | head -n 1)"

# result <status> <reason> [issue count]
result() {
    local tail_text=''
    if [ "$1" != ok ] && [ -f "$LOG" ]; then
        tail_text="$({ grep -aE 'Fatal error|Allowed memory size|Exception' "$LOG" | head -n 5 || true; tail -n 30 "$LOG"; } \
            | sed $'s/\033\\[[0-9;]*m//g' | cut -c1-300 | head -c 4000)"
    fi
    jq -n --arg project "$NAME" --arg major "$MAJOR" --arg psalm "$PSALM_VERSION" --arg tag "$TAG" --arg ref "$REF" \
        --arg status "$1" --arg reason "$2" --arg count "${3:-}" --arg tail "$tail_text" '{
            project: $project, major: $major, psalm: $psalm, tag: $tag, ref: $ref, status: $status,
            reason: (if $reason == "" then null else $reason end),
            issue_count: (if $count == "" then null else ($count | tonumber) end),
            log_tail: (if $tail == "" then null else $tail end)
        }' > "$OUT/$NAME.json"
    echo "run-project: $NAME with Psalm $PSALM_VERSION: $1 $2 ${3:-}" >&2
}

install() {
    local composer before_install attempt=1
    composer="$(jq -r --arg p "$NAME" 'if .projects[$p].composer == false then "false" else "true" end' "$CORPUS")"
    before_install="$(field before_install)"
    rm -rf "$PROJ"
    mkdir -p "$PROJ"
    git -C "$PROJ" init -q
    git -C "$PROJ" remote add origin "$REPO"
    until git -C "$PROJ" fetch -q --depth 1 origin "$REF"; do
        [ "$attempt" -lt 3 ] || return 1
        attempt=$((attempt + 1))
        sleep 5
    done
    git -C "$PROJ" -c advice.detachedHead=false checkout -q FETCH_HEAD
    if [ -n "$before_install" ]; then (cd "$PROJ" && bash -c "$before_install"); fi
    if [ "$composer" = true ] && [ -f "$PROJ/composer.json" ]; then
        (cd "$PROJ" && COMPOSER_ROOT_VERSION="${TAG#v}" composer update --no-dev --no-scripts --no-plugins \
            --ignore-platform-reqs --no-interaction --no-progress)
    fi
}

# Same config as the perf report, minus attributes this Psalm doesn't know; after_install may patch psalm-perf.xml.
configure() {
    local after_install
    php "$PERF_PHP" config --corpus "$CORPUS" --project "$NAME" > "$PROJ/psalm-perf.xml"
    php "$HERE/version-diff.php" fit-config "$PROJ/psalm-perf.xml" "$PSALM/config.xsd"
    after_install="$(field after_install)"
    if [ -n "$after_install" ]; then (cd "$PROJ" && bash -c "$after_install"); fi
}

[ -n "$REPO" ] && [ -n "$REF" ] || { result error "no corpus entry for $NAME"; exit 0; }
install > "$WORK/$NAME.install.log" 2>&1 || { LOG="$WORK/$NAME.install.log"; result error "install failed"; exit 0; }
configure 2> "$WORK/$NAME.config.log" || { LOG="$WORK/$NAME.config.log"; result error "config failed"; exit 0; }

export PSALM_ALLOW_XDEBUG=1
rc=0
# One thread: the perf report's setting, known to fit the runner's memory, and deterministic.
(cd "$PROJ" && timeout -k 20 "$TIMEOUT" php -d memory_limit=-1 "$PSALM/psalm" --root="$PROJ" -c "$PROJ/psalm-perf.xml" \
    --no-cache --no-progress --ignore-baseline --threads=1 --output-format=compact --report="$REPORT" \
    --memory-limit="$MEMORY_LIMIT") > "$LOG" 2>&1 < /dev/null || rc=$?

if [ "$rc" -eq 124 ] || [ "$rc" -eq 137 ]; then
    result timeout "exceeded ${TIMEOUT}s (exit $rc)"
elif { [ "$rc" -eq 0 ] || [ "$rc" -eq 2 ]; } && grep -aq 'Checks took' "$LOG" && [ -s "$REPORT" ]; then
    if count="$(php "$HERE/version-diff.php" normalize "$REPORT" "$PROJ" "$OUT/$NAME.issues.json")"; then
        result ok '' "$count"
    else
        result error 'issue normalisation failed'
    fi
else
    reason="Psalm exited with code $rc"
    if grep -aqE 'Allowed memory size|Hard memory limit' "$LOG"; then reason="PHP memory limit exceeded"; fi
    result crash "$reason"
fi
