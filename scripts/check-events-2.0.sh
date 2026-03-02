#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

echo "==> Events 2.0 completeness checks"

if [[ -f "packages/events/src/EventBusResolver.php" ]]; then
  echo "ERROR: packages/events/src/EventBusResolver.php still exists."
  exit 1
fi

resolver_usages="$(rg -n "EventBusResolver" packages -g '*.php' || true)"
if [[ -n "$resolver_usages" ]]; then
  echo "ERROR: EventBusResolver references found:"
  echo "$resolver_usages"
  exit 1
fi

legacy_union_signatures="$(rg -n 'null\|CanHandleEvents\|EventDispatcherInterface|null\|EventDispatcherInterface\|CanHandleEvents' \
  packages/events packages/addons packages/agents packages/agent-ctrl packages/polyglot packages/instructor packages/http-client packages/logging packages/laravel \
  -g '*.php' || true)"
if [[ -n "$legacy_union_signatures" ]]; then
  echo "ERROR: Legacy nullable/union event signatures found:"
  echo "$legacy_union_signatures"
  exit 1
fi

nullable_event_ctors="$(rg -n "function __construct\\([^\\)]*\\?CanHandleEvents" \
  packages/events packages/addons packages/agents packages/agent-ctrl packages/polyglot packages/instructor packages/http-client packages/logging packages/laravel \
  -g '*.php' || true)"

if [[ -n "$nullable_event_ctors" ]]; then
  disallowed_ctors="$(printf '%s\n' "$nullable_event_ctors" | rg -v "^packages/agents/src/Hook/HookStack.php:" || true)"
  if [[ -n "$disallowed_ctors" ]]; then
    echo "ERROR: Nullable CanHandleEvents constructors found outside allowlist:"
    echo "$disallowed_ctors"
    exit 1
  fi
fi

logging_trait_magic="$(rg -n "class_uses_recursive\\(|usesTrait\\(.*HandlesEvents::class|afterResolving\\(" \
  packages/logging/src/Integrations \
  -g '*.php' || true)"
if [[ -n "$logging_trait_magic" ]]; then
  echo "ERROR: Trait-discovery logging magic found in integrations:"
  echo "$logging_trait_magic"
  exit 1
fi

echo "OK: Events 2.0 checks passed."
