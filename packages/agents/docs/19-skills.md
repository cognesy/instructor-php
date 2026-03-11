---
title: 'Skills'
description: 'Extend agents with reusable, portable skill modules following the Agent Skills Open Standard'
---

# Skills

Skills are reusable instruction modules that extend what an agent can do. Each skill is a directory containing a `SKILL.md` file with YAML frontmatter and markdown instructions. The agent discovers available skills at startup, advertises their descriptions to the LLM, and loads full skill content on demand via tool call.

The skill system follows the [Agent Skills Open Standard](https://agentskills.io), a portable specification adopted by 30+ AI tools including Claude Code, OpenAI Codex, Cursor, GitHub Copilot, and others. Skills written for this framework are compatible with those tools and vice versa.

## Directory Structure

Each skill lives in its own directory under a skills root:

```
skills/
├── code-review/
│   ├── SKILL.md           # Main instructions (required)
│   ├── examples/
│   │   └── sample.md      # Example output
│   └── scripts/
│       └── lint.sh         # Helper script
├── deploy/
│   └── SKILL.md
└── api-conventions/
    ├── SKILL.md
    └── references/
        └── openapi.yaml
```

Resource folders (`scripts/`, `references/`, `assets/`, `examples/`) are automatically discovered and listed in the skill's `resources` property.

## SKILL.md Format

Every skill needs a `SKILL.md` file with optional YAML frontmatter between `---` markers, followed by markdown content:

```yaml
---
name: code-review
description: Review code for quality, bugs, and best practices
argument-hint: "[file-or-directory]"
license: MIT
---

When reviewing code, check for:

1. Logic errors and edge cases
2. Security vulnerabilities
3. Performance issues
4. Style consistency

Focus on $ARGUMENTS if provided.
```

### Frontmatter Fields

#### Agent Skills Open Standard (portable)

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `name` | string | directory name | Skill name (lowercase, hyphens, max 64 chars) |
| `description` | string | `''` | What the skill does and when to use it |
| `license` | string | `null` | License (e.g. `MIT`, `Apache-2.0`) |
| `compatibility` | string | `null` | Environment requirements |
| `metadata` | map | `[]` | Arbitrary key-value pairs |
| `allowed-tools` | string/list | `[]` | Space/comma-delimited or YAML list of allowed tools |

#### Cross-platform Extensions

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `disable-model-invocation` | bool | `false` | Prevent the model from auto-loading this skill |
| `user-invocable` | bool | `true` | Whether to show in user-facing skill listings |
| `argument-hint` | string | `null` | Hint for expected arguments (e.g. `[issue-number]`) |

#### Execution Context Extensions

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `model` | string | `null` | Override model when skill is active |
| `context` | string | `null` | Set to `fork` for subagent execution |
| `agent` | string | `null` | Subagent type when `context: fork` |

Unknown frontmatter fields are silently ignored, ensuring forward compatibility.

## Setting Up Skills

### Creating a SkillLibrary

The `SkillLibrary` scans a directory for skill subdirectories:

```php
use Cognesy\Agents\Capability\Skills\SkillLibrary;

$library = SkillLibrary::inDirectory(__DIR__ . '/skills');

// List all skills (name + description)
$skills = $library->listSkills();

// Check and load a specific skill
if ($library->hasSkill('code-review')) {
    $skill = $library->getSkill('code-review');
}
```

Skills are lazy-loaded: only frontmatter is read during discovery, full content is loaded on first `getSkill()` call and cached thereafter.

### Wiring Into an Agent

The `UseSkills` capability registers the `load_skill` tool and injects skill metadata via a hook:

```php
use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Skills\SkillLibrary;
use Cognesy\Agents\Capability\Skills\UseSkills;

$library = SkillLibrary::inDirectory(__DIR__ . '/skills');

$agent = AgentBuilder::base()
    ->withCapability(new UseSkills($library))
    ->build();
```

This does two things:

1. **Registers `load_skill` tool** — the LLM can call `load_skill(skill_name: "code-review")` to load full skill content, or `load_skill(list_skills: true)` to see available skills.
2. **Injects metadata hook** — `AppendSkillMetadataHook` prepends a system message listing skill names and descriptions so the LLM knows what's available.

## Argument Substitution

When loading a skill with arguments, placeholders in the body are replaced:

| Placeholder | Replaced with |
|-------------|--------------|
| `$ARGUMENTS` | Full argument string |
| `$ARGUMENTS[N]` | Nth argument (0-based) |
| `$N` | Shorthand for `$ARGUMENTS[N]` |

If no placeholder is present, arguments are appended as `ARGUMENTS: <value>`.

```yaml
---
name: fix-issue
description: Fix a GitHub issue
argument-hint: "[issue-number]"
---

Fix GitHub issue $ARGUMENTS following our coding standards.
```

When loaded with `load_skill(skill_name: "fix-issue", arguments: "123")`, the body becomes "Fix GitHub issue 123 following our coding standards."

## Invocation Control

Two flags control who can invoke a skill:

| Configuration | Model sees it | User sees it | Use case |
|--------------|--------------|-------------|----------|
| *(default)* | Yes | Yes | General-purpose skills |
| `disable-model-invocation: true` | No | Yes | Side-effect workflows (deploy, commit) |
| `user-invocable: false` | Yes | No | Background knowledge (legacy system context) |

```yaml
---
name: deploy
description: Deploy to production
disable-model-invocation: true
---

Deploy the application:
1. Run tests
2. Build
3. Push to production
```

## Components

### Skill

Immutable value object holding parsed skill data:

```php
$skill->name;                  // string
$skill->description;           // string
$skill->body;                  // string (markdown content)
$skill->path;                  // string (absolute path to SKILL.md)
$skill->license;               // ?string
$skill->compatibility;         // ?string
$skill->metadata;              // array<string, string>
$skill->allowedTools;          // list<string>
$skill->disableModelInvocation; // bool
$skill->userInvocable;         // bool
$skill->argumentHint;          // ?string
$skill->model;                 // ?string
$skill->context;               // ?string
$skill->agent;                 // ?string
$skill->resources;             // list<string>

$skill->render();              // Full skill content with XML tags
$skill->render('arg1 arg2');   // With argument substitution
$skill->renderMetadata();      // "[name]: description"
$skill->toArray();             // All non-null fields as array
```

### SkillLibrary

Discovery and lazy-loading of skills from a directory:

```php
$library = SkillLibrary::inDirectory($path);
$library->listSkills();                           // All skills
$library->listSkills(modelInvocable: true);       // Exclude disabled
$library->listSkills(userInvocable: true);        // Exclude background
$library->hasSkill('name');                       // bool
$library->getSkill('name');                       // ?Skill
$library->renderSkillList();                      // Formatted list
```

### LoadSkillTool

Tool exposed to the LLM for loading skills:

```
load_skill(skill_name: "code-review")              // Load a skill
load_skill(skill_name: "fix-issue", arguments: "123")  // With args
load_skill(list_skills: true)                      // List available
```

### AppendSkillMetadataHook

Before the first agent step, injects a system message listing available model-invocable skills with their descriptions and argument hints.

## Shell Preprocessing

Skills can embed shell commands using the `` !`command` `` syntax. When a `SkillPreprocessor` is configured, these patterns are executed and replaced with their output before argument substitution occurs.

```yaml
---
name: project-info
description: Show project context
---

Project version: !`cat VERSION`
Current branch: !`git branch --show-current`
Recent changes: !`git log --oneline -5`

Review the code in $ARGUMENTS.
```

When loaded, the `` !`...` `` patterns are replaced with live command output, giving the LLM up-to-date context.

### Enabling Preprocessing

Pass a `SkillPreprocessor` to `UseSkills`:

```php
use Cognesy\Agents\Capability\Skills\SkillLibrary;
use Cognesy\Agents\Capability\Skills\SkillPreprocessor;
use Cognesy\Agents\Capability\Skills\UseSkills;

$library = SkillLibrary::inDirectory(__DIR__ . '/skills');
$preprocessor = new SkillPreprocessor(
    workingDirectory: getcwd(),  // optional, defaults to cwd
    timeoutSeconds: 10,          // optional, default 10s
);

$agent = AgentBuilder::base()
    ->withCapability(new UseSkills($library, $preprocessor))
    ->build();
```

Commands that fail or time out are replaced with `[error: ...]` markers instead of crashing the skill load.

## Cross-Platform Compatibility

The portable subset that works across all Agent Skills-compatible tools:

- `name` and `description` in frontmatter
- Markdown instructions in the body
- Directory-per-skill layout with `SKILL.md` entry point

Extension fields (`disable-model-invocation`, `context`, `model`, etc.) are tool-specific. Unknown fields are ignored gracefully by all compliant tools, so skills with extensions remain portable — the extensions simply don't activate in tools that don't support them.
