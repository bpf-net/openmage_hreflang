#!/usr/bin/env bash
# PostToolUse (Edit|Write): syntax-check edited PHP/PHTML and XML files.
# Exit 2 feeds the error back to Claude.
f=$(jq -r '.tool_response.filePath // .tool_input.file_path // empty')
[ -n "$f" ] && [ -f "$f" ] || exit 0
case "$f" in
  *.php|*.phtml)
    out=$(php -l "$f" 2>&1) || { echo "$out" >&2; exit 2; } ;;
  *.xml|*.xml.dist)
    out=$(python3 -I -c 'import sys, xml.etree.ElementTree as E; E.parse(sys.argv[1])' "$f" 2>&1) \
      || { echo "XML parse error in $f:" >&2; echo "$out" | tail -1 >&2; exit 2; } ;;
esac
exit 0
