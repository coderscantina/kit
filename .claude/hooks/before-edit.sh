#!/usr/bin/env bash
# PreToolUse hook for Edit and Write. Refuses hand edits to files a command
# owns, and says which command to run instead.
set -uo pipefail

file="$(php -r '$in = json_decode(stream_get_contents(STDIN), true); echo $in["tool_input"]["file_path"] ?? "";')"
file="${file#"${CLAUDE_PROJECT_DIR:-$PWD}"/}"

case "$file" in
  resources/js/types/generated.d.ts)
    echo 'generated.d.ts is written by `php artisan types:generate` from the PHP data classes, queries, mutations and AI actions. Change those and it regenerates.' >&2
    exit 2
    ;;
  resources/js/types/auto-imports.d.ts)
    echo 'auto-imports.d.ts is written by unplugin-auto-import when Vite or vitest runs. Do not edit it.' >&2
    exit 2
    ;;
  app/Features/*)
    echo 'There is no app/Features. The layout is layer-first; CLAUDE.md section 1 says where each file goes.' >&2
    exit 2
    ;;
esac

exit 0
