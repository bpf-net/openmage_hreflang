#!/usr/bin/env bash
# Stop: every file under Magento root dirs, outside the module code dir,
# must be covered by a modman mapping. Exit 2 asks Claude to fix it.
input=$(cat)
[ "$(jq -r '.stop_hook_active // false' <<<"$input")" = "true" ] && exit 0
root=$(git -C "${CLAUDE_PROJECT_DIR:-.}" rev-parse --show-toplevel 2>/dev/null) || exit 0
cd "$root" && [ -f modman ] || exit 0
mapfile -t sources < <(grep -vE '^\s*(#|$)' modman | awk '{print $1}' | sed 's#/*$##')
missing=()
while IFS= read -r p; do
  case "$p" in app/code/community/Bpf/Hreflang/*) continue ;; esac
  covered=
  for s in "${sources[@]}"; do
    [ "$p" = "$s" ] || [[ "$p" == "$s/"* ]] && { covered=1; break; }
  done
  [ -z "$covered" ] && missing+=("$p")
done < <(git ls-files --cached --others --exclude-standard -- app js skin lib shell errors media)
[ ${#missing[@]} -eq 0 ] && exit 0
{ echo "Files not mapped in modman (add a 'source target' line):"; printf '  %s\n' "${missing[@]}"; } >&2
exit 2
