#!/usr/bin/env bash
set -euo pipefail

source_root="$(cd "$(dirname "$0")/../../.." && pwd)"
fixture="$(mktemp -d "${TMPDIR:-/tmp}/release-state.XXXXXX")"
trap 'rm -rf -- "$fixture"' EXIT

make_fixture() {
  local name="$1"
  local root="$fixture/$name"
  mkdir -p "$root/scripts/release" "$root/scripts/packages" "$root/scripts/docs" "$root/docs/release-notes" "$root/packages/demo" "$root/fake-bin"
  cp "$source_root/scripts/release/publish-ver.sh" "$root/scripts/release/publish-ver.sh"

  printf '{"navigation":["release-notes/v1.2.3"]}\n' > "$root/docs/mint.json"
  printf '# v1.2.3\n' > "$root/docs/release-notes/v1.2.3.mdx"
  printf '{"name":"demo","require":{}}\n' > "$root/packages/demo/composer.json"

  cat > "$root/scripts/packages/sync-ver.sh" <<'SCRIPT'
#!/usr/bin/env bash
set -euo pipefail
echo sync >> "$RELEASE_TEST_LOG"
php -r '$path = "packages/demo/composer.json"; $data = json_decode(file_get_contents($path), true); $data["version"] = $argv[1]; file_put_contents($path, json_encode($data) . "\n");' "$1"
SCRIPT
cat > "$root/scripts/release/verify-docs-deployment.sh" <<'SCRIPT'
#!/usr/bin/env bash
set -euo pipefail
if git rev-parse refs/tags/v1.2.3 >/dev/null 2>&1; then
  echo "tag existed before documentation verification" >&2
  exit 1
fi
if [[ "$(git rev-parse HEAD)" != "$(git ls-remote origin refs/heads/main | awk '{print $1}')" ]]; then
  echo "main was not pushed before documentation verification" >&2
  exit 1
fi
echo deploy >> "$RELEASE_TEST_LOG"
if [[ "${RELEASE_TEST_DEPLOY_FAIL:-0}" == '1' ]]; then exit 1; fi
SCRIPT
  cat > "$root/fake-bin/composer" <<'SCRIPT'
#!/usr/bin/env bash
set -euo pipefail
echo docs >> "$RELEASE_TEST_LOG"
if [[ "${RELEASE_TEST_DOCS_FAIL:-0}" == '1' ]]; then exit 1; fi
SCRIPT
cat > "$root/fake-bin/gh" <<'SCRIPT'
#!/usr/bin/env bash
set -euo pipefail
git rev-parse refs/tags/v1.2.3 >/dev/null
echo release >> "$RELEASE_TEST_LOG"
SCRIPT
  chmod +x "$root/scripts/release/publish-ver.sh" "$root/scripts/release/verify-docs-deployment.sh" "$root/scripts/packages/sync-ver.sh" "$root/fake-bin/composer" "$root/fake-bin/gh"

  git init --bare "$root/remote.git" >/dev/null
  git init -b main "$root/repository" >/dev/null
  cp -R "$root/scripts" "$root/docs" "$root/packages" "$root/fake-bin" "$root/repository/"
  git -C "$root/repository" config user.name Test
  git -C "$root/repository" config user.email test@example.com
  git -C "$root/repository" add .
  git -C "$root/repository" commit -m initial >/dev/null
  git -C "$root/repository" remote add origin "$root/remote.git"
  git -C "$root/repository" push -u origin main >/dev/null
  printf '%s' "$root/repository"
}

success_root="$(make_fixture success)"
success_log="$fixture/success.log"
PATH="$success_root/fake-bin:$PATH" RELEASE_TEST_LOG="$success_log" "$success_root/scripts/release/publish-ver.sh" 1.2.3 >/dev/null
if [[ "$(paste -sd, "$success_log")" != 'sync,docs,deploy,release' ]]; then
  echo "Release state transitions ran out of order." >&2
  exit 1
fi
git -C "$success_root" rev-parse refs/tags/v1.2.3 >/dev/null

dirty_root="$(make_fixture dirty)"
dirty_log="$fixture/dirty.log"
printf 'dirty\n' >> "$dirty_root/docs/release-notes/v1.2.3.mdx"
if PATH="$dirty_root/fake-bin:$PATH" RELEASE_TEST_LOG="$dirty_log" "$dirty_root/scripts/release/publish-ver.sh" 1.2.3 >/dev/null 2>&1; then
  echo "Dirty release checkout was accepted." >&2
  exit 1
fi
if [[ -e "$dirty_log" ]]; then
  echo "Dirty release checkout mutated state before aborting." >&2
  exit 1
fi

unindexed_root="$(make_fixture unindexed)"
unindexed_log="$fixture/unindexed.log"
printf '{"navigation":[]}\n' > "$unindexed_root/docs/mint.json"
git -C "$unindexed_root" add docs/mint.json
git -C "$unindexed_root" commit -m unindexed >/dev/null
git -C "$unindexed_root" push origin main >/dev/null
if PATH="$unindexed_root/fake-bin:$PATH" RELEASE_TEST_LOG="$unindexed_log" "$unindexed_root/scripts/release/publish-ver.sh" 1.2.3 >/dev/null 2>&1; then
  echo "Unindexed release note was accepted." >&2
  exit 1
fi
if [[ -e "$unindexed_log" ]]; then
  echo "Unindexed release note mutated state before aborting." >&2
  exit 1
fi

deploy_failure_root="$(make_fixture deploy-failure)"
deploy_failure_log="$fixture/deploy-failure.log"
if PATH="$deploy_failure_root/fake-bin:$PATH" RELEASE_TEST_LOG="$deploy_failure_log" RELEASE_TEST_DEPLOY_FAIL=1 "$deploy_failure_root/scripts/release/publish-ver.sh" 1.2.3 >/dev/null 2>&1; then
  echo "Release continued after documentation deployment failure." >&2
  exit 1
fi
if git -C "$deploy_failure_root" rev-parse refs/tags/v1.2.3 >/dev/null 2>&1; then
  echo "Tag was created before documentation deployment succeeded." >&2
  exit 1
fi

echo "Release publication state-machine tests passed."
