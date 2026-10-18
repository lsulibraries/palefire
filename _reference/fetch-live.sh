#!/usr/bin/env bash
# Saves pages from lib.lsu.edu into _reference/live/ so Claude can read them
# locally. Run it yourself (it sends your WAF debug token).
#
# Usage, from the repo root in Git Bash:
#   export PF_TOKEN='<your X-Debug-Token>'      # once per terminal session
#   bash _reference/fetch-live.sh /v2/special /v2/some/other/page
#   bash _reference/fetch-live.sh "https://lib.lsu.edu/v2/special?foo=1"
#
# Takes any number of paths ("/v2/special") or full lib.lsu.edu URLs.
# Anything on another host is skipped. Each page is saved as
# _reference/live/<path with / turned into _>.html and its status printed.

set -u

if [ -z "${PF_TOKEN:-}" ]; then
  echo "Set PF_TOKEN first: export PF_TOKEN='<your token>'" >&2
  exit 1
fi
if [ "$#" -eq 0 ]; then
  echo "Give one or more paths or lib.lsu.edu URLs, e.g. /v2/special" >&2
  exit 1
fi

out_dir="$(cd "$(dirname "$0")" && pwd)/live"
mkdir -p "$out_dir"

for arg in "$@"; do
  case "$arg" in
    https://lib.lsu.edu/*|https://lib.lsu.edu) url="$arg" ;;
    /*) url="https://lib.lsu.edu$arg" ;;
    *) echo "skip (not lib.lsu.edu): $arg" >&2; continue ;;
  esac

  # /v2/special?x=1 -> v2_special_x=1.html ; the home page -> index.html
  name="${url#https://lib.lsu.edu}"
  name="${name#/}"
  name="$(printf '%s' "$name" | tr '/?&#:' '_____')"
  [ -z "$name" ] && name="index"
  file="$out_dir/$name.html"

  status="$(curl -sS -L --max-time 30 -o "$file" -w '%{http_code}' \
    -H "X-Debug-Token: $PF_TOKEN" "$url")"
  printf '%s  %s  -> %s\n' "$status" "$url" "${file#"$out_dir/"}"
done
