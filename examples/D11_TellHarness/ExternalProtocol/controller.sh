#!/usr/bin/env bash
set -euo pipefail

controller_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
repository_root=$(CDPATH= cd -- "$controller_dir/../../.." && pwd)
request_id="shell-$(date +%s)-$$"
prompt=${1:-Summarize the highest-risk unfinished work in this project.}
target_directory=${TELL_RPC_DIR:-$repository_root}

jq -cn \
  --arg id "$request_id" \
  --arg prompt "$prompt" \
  '{
    schema: "tell.agent.request.v1",
    id: $id,
    prompt: $prompt,
    mode: "stateless",
    maxSteps: 5,
    policy: {
      timeoutMs: 30000,
      maxOutputChars: 20000,
      maxToolOutputChars: 4000,
      maxToolCalls: 8
    }
  }' |
  php "$repository_root/bin/tell" agent --rpc --dir "$target_directory"
