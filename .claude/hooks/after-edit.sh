#!/usr/bin/env bash
# PostToolUse hook for Edit and Write. Does the steps CLAUDE.md would
# otherwise have to remind an agent of, right after the file changes:
#
#   - formats the file (pint for PHP, oxfmt for the frontend), so formatting
#     never fails a gate
#   - regenerates resources/js/types/generated.d.ts after a change to a data
#     class, a query, a mutation or an AI action, so the client types and the
#     committed file never lag the PHP
#
# Silent on success. A failed types:generate is reported back (exit 2), since
# the client cannot typecheck against stale types.
set -uo pipefail

cd "${CLAUDE_PROJECT_DIR:-$(dirname "$0")/../..}"

file="$(php -r '$in = json_decode(stream_get_contents(STDIN), true); echo $in["tool_input"]["file_path"] ?? "";')"
[ -n "$file" ] && [ -f "$file" ] || exit 0
file="${file#"$PWD"/}"

case "$file" in
  vendor/* | node_modules/* | public/build/*) exit 0 ;;
  *.php) vendor/bin/pint --quiet "$file" >/dev/null 2>&1 || true ;;
  *.ts | *.vue | *.js | *.mjs | *.json | *.css | *.md)
    bunx oxfmt --no-error-on-unmatched-pattern "$file" >/dev/null 2>&1 || true
    ;;
esac

case "$file" in
  app/Data/*.php | app/Queries/*.php | app/Mutations/*.php | app/Ai/Actions/*.php)
    if ! output="$(php artisan types:generate 2>&1)"; then
      {
        echo "types:generate failed after editing $file. Mid-change this is expected; it runs again on the next edit. If it persists, fix it before typechecking:"
        tail -n 20 <<<"$output"
      } >&2
      exit 2
    fi
    ;;
esac

exit 0
