# Codex SDK

Aside from using Codex through the different interfaces like the Codex CLI, IDE extension or Codex Web, you can also programmatically control Codex.

This can be useful if you want to:

- Control Codex as part of your CI/CD pipeline
- Create your own agent that can engage with Codex to perform complex engineering tasks
- Build Codex into your own internal tools and workflows
- Integrate Codex within your own application

Just to name a few.

There are different ways to programmatically control Codex, depending on your use case.

- [TypeScript library](#typescript-library) — if you want to have full control over Codex from within your JavaScript or TypeScript server-side application
- [Using Codex CLI programmatically](#using-codex-cli-programmatically) — if you are just trying to send individual tasks to Codex
- [GitHub Action](#github-action) — if you want to trigger and control Codex from within your GitHub Actions workflow

## TypeScript library

The TypeScript library provides a more comprehensive way to control Codex from within your application.

The library is intended to be used server-side and requires at least Node.js v18.

### Installation

To get started, install the Codex SDK using `npm`:

```bash
npm install @openai/codex-sdk
```

### Usage

Start a thread with Codex and run it with your prompt.

```ts


const codex = new Codex();
const thread = codex.startThread();
const result = await thread.run(
  "Make a plan to diagnose and fix the CI failures"
);

console.log(result);
```

Call `run()` again to continue on the same thread, or resume a past thread by providing a `threadID`.

```ts
// running the same thread
const result = await thread.run("Implement the plan");

console.log(result);

// resuming past thread

const thread2 = codex.resumeThread(threadId);
const result2 = await thread.run("Pick up where you left off");

console.log(result2);
```

For more details, check out the [TypeScript repo](https://github.com/openai/codex/tree/main/sdk/typescript).

## Using Codex CLI programmatically

Aside from the library, you can also use the [Codex CLI](/codex/cli) in a programmatic way using the `exec` command. This runs Codex in non-interactive mode so you can hand it a task and let it finish without requiring inline approvals.

### Non-interactive execution

`codex exec "<task>"` streams Codex’s progress to stderr and prints only the final agent message to stdout. This makes it easy to pipe the final result into other tools.

```bash
codex exec "find any remaining TODOs and create for each TODO a detailed implementation plan markdown file in the .plans/ directory."
```

By default, Codex operates in a read-only sandbox and will not modify files or run networked commands.

### Allowing Codex to edit or reach the network

- Use `codex exec --full-auto "<task>"` to allow Codex to edit files.
- Use `codex exec --sandbox danger-full-access "<task>"` to allow edits and networked commands.

Combine these flags as needed to give Codex the permissions required for your workflow.

### Output control and streaming

While `codex exec` runs, Codex streams its activity to stderr. Only the final agent message is written to stdout, which makes it simple to pipe the result into other tools:

```bash
codex exec "generate release notes" | tee release-notes.md
```

- `-o`/`--output-last-message` writes the final message to a file in addition to stdout redirection.
- `--json` switches stdout to a JSON Lines stream so you can capture every event Codex emits while it is working. Event types include `thread.started`, `turn.started`, `turn.completed`, `turn.failed`, `item.*`, and `error`. Item types cover agent messages, reasoning, command executions, file changes, MCP tool calls, web searches, and plan updates.

```bash
codex exec --json "summarize the repo structure" | jq
```

Sample JSON stream (each line is a JSON object):

```jsonl
{"type":"thread.started","thread_id":"0199a213-81c0-7800-8aa1-bbab2a035a53"}
{"type":"turn.started"}
{"type":"item.started","item":{"id":"item_1","type":"command_execution","command":"bash -lc ls","status":"in_progress"}}
{"type":"item.completed","item":{"id":"item_3","type":"agent_message","text":"Repo contains docs, sdk, and examples directories."}}
{"type":"turn.completed","usage":{"input_tokens":24763,"cached_input_tokens":24448,"output_tokens":122}}
```

### Structured output

Use `--output-schema <path>` to run Codex with a JSON Schema and receive structured JSON that conforms to it. Combine with `-o` to save the final JSON directly to disk.

`schema.json`

```json
{
  "type": "object",
  "properties": {
    "project_name": { "type": "string" },
    "programming_languages": {
      "type": "array",
      "items": { "type": "string" }
    }
  },
  "required": ["project_name", "programming_languages"],
  "additionalProperties": false
}
```

```bash
codex exec "Extract project metadata" \
  --output-schema ./schema.json \
  -o ./project-metadata.json
```

The final JSON respects the schema you provide, which is especially useful when feeding Codex output into scripts or CI pipelines.

Example final output (stdout):

```json
{
  "project_name": "Codex CLI",
  "programming_languages": ["Rust", "TypeScript", "Shell"]
}
```

### Git repository requirement

Codex requires commands to run inside a Git repository to prevent destructive changes. Override this check with `codex exec --skip-git-repo-check` if you know the environment is safe.

### Resuming non-interactive sessions

Resume a previous non-interactive run to continue the same conversation context:

```bash
codex exec "Review the change for race conditions"
codex exec resume --last "Fix the race conditions you found"
```

You can also target a specific session ID with `codex exec resume <SESSION_ID>`.

### Authentication

`codex exec` reuses the CLI’s authentication by default. To override the credential for a single run, set `CODEX_API_KEY`:

```bash
CODEX_API_KEY=your-api-key codex exec --json "triage open bug reports"
```

`CODEX_API_KEY` is only supported in `codex exec`.