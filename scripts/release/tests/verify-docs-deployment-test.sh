#!/usr/bin/env bash
set -euo pipefail

source_root="$(cd "$(dirname "$0")/../../.." && pwd)"
fixture="$(mktemp -d "${TMPDIR:-/tmp}/verify-docs-deployment.XXXXXX")"
trap 'rm -rf -- "$fixture"' EXIT

mkdir -p "$fixture/scripts/release" "$fixture/scripts/docs" "$fixture/fake-bin"
cp "$source_root/scripts/release/verify-docs-deployment.sh" "$fixture/scripts/release/verify-docs-deployment.sh"

cat > "$fixture/scripts/docs/verify-live-provenance.sh" <<'SCRIPT'
#!/usr/bin/env bash
set -euo pipefail
SCRIPT

cat > "$fixture/fake-bin/gh" <<'SCRIPT'
#!/usr/bin/env bash
set -euo pipefail

if [[ "$1 $2" == 'run list' ]]; then
  printf '123\n'
  exit 0
fi

if [[ "$1 $2" == 'run watch' ]]; then
  exit 0
fi

exit 1
SCRIPT

cat > "$fixture/fake-bin/curl" <<'SCRIPT'
#!/usr/bin/env bash
set -euo pipefail

printf 'v1.2.3\n'
for ((line = 1; line <= 100000; line++)); do
  printf 'response padding %s\n' "$line"
done
SCRIPT

cat > "$fixture/fake-bin/sleep" <<'SCRIPT'
#!/usr/bin/env bash
exit 0
SCRIPT

chmod +x "$fixture/scripts/release/verify-docs-deployment.sh" \
  "$fixture/scripts/docs/verify-live-provenance.sh" \
  "$fixture/fake-bin/gh" \
  "$fixture/fake-bin/curl" \
  "$fixture/fake-bin/sleep"

(
  cd "$fixture"
  PATH="$fixture/fake-bin:$PATH" \
  DOCS_VERIFY_ATTEMPTS=1 \
  DOCS_VERIFY_INTERVAL=0 \
  MINTLIFY_BASE_URL='https://mintlify.example' \
  MKDOCS_BASE_URL='https://mkdocs.example' \
    ./scripts/release/verify-docs-deployment.sh \
      aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa \
      1.2.3 >/dev/null
)

echo "Documentation deployment verification tests passed."
