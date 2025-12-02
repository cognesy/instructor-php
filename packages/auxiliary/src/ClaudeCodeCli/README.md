# ClaudeCodeCli (scaffolding)

Purpose: controlled, sandboxed invocation of the `claude` CLI with a dev-friendly PHP API, mirroring Beads patterns.

Current components:

- `Domain\Value\Argv`, `CommandSpec`, `PathList`: typed wrappers for argv/stdin and directory lists.
- `Domain\Enum\OutputFormat`, `PermissionMode`, `InputFormat`: map to CLAUDE CLI flags.
- `Application\Builder\ClaudeCommandBuilder`: builds headless `claude -p` argv with permission mode, output/input formats, streaming flags, resume/continue, model, system prompt options, agents, additional directories, verbose logging, permission prompt tool, and permission skip opt-in.
- `Application\Parser\ResponseParser` + `Application\Dto\ClaudeResponse`: parse `ExecResult` stdout for `json` or `stream-json` output into decoded object collections.
- `Infrastructure\Execution\ExecutionPolicy`: wraps sandbox policy with claude defaults (60s timeout, network on, capped stdout/stderr, project read/write).
- `Infrastructure\Execution\SandboxCommandExecutor`: runs commands via Sandbox driver (host/docker/podman/firejail/bubblewrap) with optional retries and backoff.
- `Infrastructure\Execution\CommandExecutor` interface + `SandboxDriver` enum: driver selection and policy access.
- Subagents: `--agents` JSON flag is supported for dynamic subagent definitions (description, prompt, tools, model) as documented in `docs-internal/claude-code/build/subagents.md`.

Intended usage (outline):

```php
use Cognesy\Auxiliary\ClaudeCodeCli\Application\Builder\ClaudeCommandBuilder;
use Cognesy\Auxiliary\ClaudeCodeCli\Application\Dto\ClaudeRequest;
use Cognesy\Auxiliary\ClaudeCodeCli\Domain\Enum\InputFormat;
use Cognesy\Auxiliary\ClaudeCodeCli\Domain\Enum\OutputFormat;
use Cognesy\Auxiliary\ClaudeCodeCli\Domain\Enum\PermissionMode;
use Cognesy\Auxiliary\ClaudeCodeCli\Infrastructure\Execution\SandboxCommandExecutor;

$request = new ClaudeRequest(
    prompt: 'summarize src/App.php',
    outputFormat: OutputFormat::Json,
    permissionMode: PermissionMode::Plan,
    maxTurns: 3,
    includePartialMessages: true,
    inputFormat: InputFormat::StreamJson,
    verbose: true,
    permissionPromptTool: 'mcp__auth__prompt',
    continueMostRecent: true,
    stdin: '{"type":"user","message":{"role":"user","content":[{"type":"text","text":"Summarize"}]}}',
);

$spec = (new ClaudeCommandBuilder())->buildHeadless($request);
$executor = SandboxCommandExecutor::default();
$execResult = $executor->execute($spec); // ExecResult with stdout/stderr/exitCode

// Parse structured output when using json/stream-json
$response = (new ResponseParser())->parse($execResult, OutputFormat::Json);
$decoded = $response->decoded(); // DecodedObjectCollection
```

Next steps:

- Extend request/builders for resume/continue modes, permission prompt tools, and structured stdin streaming.
- Wire into higher-level services and more comprehensive tests (integration against real claude binary when available).

Usage examples:

- Headless JSON run with plan mode:

```php
$req = new ClaudeRequest(
    prompt: 'summarize src/App.php',
    outputFormat: OutputFormat::Json,
    permissionMode: PermissionMode::Plan,
    maxTurns: 4,
);
```

- Streaming with partials, stdin, and resume/continue:

```php
$req = new ClaudeRequest(
    prompt: 'continue session',
    outputFormat: OutputFormat::StreamJson,
    includePartialMessages: true,
    inputFormat: InputFormat::StreamJson,
    continueMostRecent: true,
    verbose: true,
    stdin: '{"type":"user","message":{"role":"user","content":[{"type":"text","text":"continue"}]}}',
);
```

- Custom subagent and directories:

```php
$req = new ClaudeRequest(
    prompt: 'scan shared libs',
    outputFormat: OutputFormat::Json,
    agentsJson: '{"explorer":{"description":"Scan shared libs","prompt":"Focus on shared/","tools":["Read","Grep"]}}',
    additionalDirs: PathList::of(['../shared']),
);
```
