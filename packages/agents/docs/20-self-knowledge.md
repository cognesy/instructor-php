# Agent Self-Knowledge

Self-knowledge lets an agent locate and read the documentation shipped with
`cognesy/agents`. It is an opt-in profile capability: it adds documentation paths
and topic routing, but it does not add a file-reading tool or contact an LLM.

## Enabling Self-Knowledge

Install a readable tool, `UseSelfKnowledge`, and `UseSystemPrompt`:

```php
use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\File\UseFileTools;
use Cognesy\Agents\Capability\Prompt\UseSystemPrompt;
use Cognesy\Agents\Capability\SelfKnowledge\UseSelfKnowledge;

$agent = AgentBuilder::base()
    ->withCapability(new UseFileTools('/workspace'))
    ->withCapability(new UseSelfKnowledge())
    ->withCapability(new UseSystemPrompt())
    ->build();
```

`UseSelfKnowledge` contributes data to the resolved `AgentProfile`.
`UseSystemPrompt` is the runtime wiring that renders profile sections into the
state's system prompt before inference. Calling `SystemPromptComposer` manually
only renders text for inspection and does not install that lifecycle behavior.

## What It Contributes

When enabled, `profile()->metadata->get('selfKnowledge')` contains:

- `docsPath`: absolute path to the curated package documentation
- `readmePath`: absolute path to the package README
- `examplesPath`: monorepo examples path when available, otherwise `null`
- `topics`: targeted topic-to-file routes

The prompt section tells the model to resolve documentation links under the
installed docs root and to read the relevant files completely before acting.
The same safe summary is exposed by `AgentLoop::describe()`.

## Read-Tool Gate

The default `requireReadTool: true` contributes nothing unless the resolved
profile contains `read`, `read_file`, or a tool tagged with both `file` and
`read`. A generic `read` tag on an introspection or definition tool does not
qualify. This prevents a prompt from advertising files the agent cannot access.

Applications that provide an equivalent read mechanism outside the tool list can
disable the gate explicitly:

```php
new UseSelfKnowledge(requireReadTool: false);
```

Disabling the gate does not grant filesystem access. The host remains responsible
for making the documented paths readable.

## Installed Documentation

The agent-facing files are mirrored from `docs/` to `resources/docs/`, which is
included in Composer distributions. `PackageDocs::installed()` resolves paths for
monorepo development, split-package checkouts, and vendor installs.

After changing a routed source document, synchronize and verify the mirror:

```bash
composer --working-dir=packages/agents docs:self-knowledge:write
composer --working-dir=packages/agents docs:self-knowledge:check
```

The repository QA checks that every routed file exists, every mirrored document
has a topic, and the source and installed copies are byte-for-byte identical.

## Inspecting the Result

No inference is needed to inspect the resolved metadata:

```php
$description = $agent->describe()->toArray();
$selfKnowledge = $description['selfKnowledge'];
```

To verify the actual prompt used at runtime, execute the loop with a deterministic
driver and inspect the state's context system prompt after the prompt hook has
run. The `tell describe --prompt` command follows that same lifecycle path.

## Related

- [Agent Builder](13-agent-builder.md)
- [Hooks](08-hooks.md)
- [Observing Agent Execution](18-observing-agent-execution.md)
