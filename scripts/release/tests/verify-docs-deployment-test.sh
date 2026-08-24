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

cat > "$fixture/fake-bin/sleep" <<'SCRIPT'
#!/usr/bin/env bash
exit 0
SCRIPT

chmod +x "$fixture/scripts/release/verify-docs-deployment.sh" \
  "$fixture/scripts/docs/verify-live-provenance.sh" \
  "$fixture/fake-bin/gh" \
  "$fixture/fake-bin/sleep"

# Runs the verifier against a stubbed curl. The stub emits a large body so the
# `curl | grep` pipeline is exercised under `pipefail` with a response far
# bigger than a pipe buffer -- a `grep -q` there would exit early, SIGPIPE the
# curl, and fail the gate on a perfectly good deployment.
run_case() {
  local label="$1" expected_status="$2" version_line="$3"

  cat > "$fixture/fake-bin/curl" <<SCRIPT
#!/usr/bin/env bash
set -euo pipefail

${version_line}
for ((line = 1; line <= 100000; line++)); do
  printf 'response padding %s\n' "\$line"
done
SCRIPT
  chmod +x "$fixture/fake-bin/curl"

  local status=0
  (
    cd "$fixture"
    PATH="$fixture/fake-bin:$PATH" \
    DOCS_VERIFY_ATTEMPTS=1 \
    DOCS_VERIFY_INTERVAL=0 \
    MINTLIFY_BASE_URL='https://mintlify.example' \
    MKDOCS_BASE_URL='https://mkdocs.example' \
      ./scripts/release/verify-docs-deployment.sh \
        aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa \
        1.2.3 >/dev/null 2>&1
  ) || status=$?

  if ((status != expected_status)); then
    echo "FAIL: $label (expected exit $expected_status, got $status)" >&2
    exit 1
  fi
}

# Both release pages serve the released version -> the gate passes.
run_case 'release pages serve v1.2.3' 0 "printf 'v1.2.3\n'"

# A release page that is reachable but does not carry the version is a real
# failure (wrong build promoted, truncated page) and must still fail the gate.
run_case 'release page missing the version' 1 ":"

echo "Documentation deployment verification tests passed."
