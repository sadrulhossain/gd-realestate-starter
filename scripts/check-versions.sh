#!/bin/sh
# Every *_VERSION in .env must appear as a pin in docs/VERSIONS.md.
cd "$(dirname "$0")/.."
bad=0
for kv in $(grep -E '^[A-Z_]+_VERSION=' .env); do
  k=${kv%%=*}; v=${kv#*=}
  if grep -qF "| $v " docs/VERSIONS.md; then echo "ok       $k=$v"; else echo "MISMATCH $k=$v"; bad=1; fi
done
exit $bad
