# Agent Skills

Agent Skills let you extend Codex with task-specific capabilities. A skill packages instructions, resources, and optional scripts so Codex can perform a specific workflow reliably. You can share skills across teams or the community, and they build on the [open Agent Skills standard](http://agentskills.io).

Skills are available in both the Codex CLI and IDE extensions.

## What are Agent Skills

A skill captures a capability expressed through markdown instructions inside a `SKILL.md` file accompanied by optional scripts, resources, and assets that Codex uses to perform a specific task.

<FileTree
  class="mt-4"
  tree={[
    {
      name: "my-skill/",
      open: true,
      children: [
        {
          name: "SKILL.md",
          comment: "Required: instructions + metadata",
        },
        {
          name: "scripts/",
          comment: "Optional: executable code",
        },
        {
          name: "references/",
          comment: "Optional: documentation",
        },
        {
          name: "assets/",
          comment: "Optional: templates, resources",
        },
      ],
    },
  ]}
/>

Skills use **progressive disclosure** to manage context efficiently. At startup, Codex loads the name and description of each available skill. Codex can then activate and use a skill in two ways:

1. **Explicit invocation:** You can include skills directly as part of your prompt. To select one, run the `/skills` slash command, or start typing `$` to mention a skill. (Codex web and iOS don't support explicit invocation yet, but you can still prompt Codex to use any skill checked into the repo.)

<div class="not-prose my-2 mb-4 grid gap-4 lg:grid-cols-2">
  <div>
    <img
      src="/images/codex/skills/skills-selector-cli-light.webp"
      alt=""
      class="block w-full lg:h-64 rounded-lg border border-default my-0 object-contain bg-[#F0F1F5] dark:hidden"
    />
    <img
      src="/images/codex/skills/skills-selector-cli-dark.webp"
      alt=""
      class="hidden w-full lg:h-64 rounded-lg border border-default my-0 object-contain bg-[#1E1E2E] dark:block"
    />
  </div>
  <div>
    <img
      src="/images/codex/skills/skills-selector-ide-light.webp"
      alt=""
      class="block w-full lg:h-64 rounded-lg border border-default my-0 object-contain bg-[#E8E9ED] dark:hidden"
    />
    <img
      src="/images/codex/skills/skills-selector-ide-dark.webp"
      alt=""
      class="hidden w-full lg:h-64 rounded-lg border border-default my-0 object-contain bg-[#181824] dark:block"
    />
  </div>
</div>

2. **Implicit invocation:** Codex can decide to use an available skill when the user’s task matches the skill’s description.

In either method, Codex reads the full instructions of the invoked skills and any extra references checked into the skill.

## Where to save skills

Codex loads skills from these locations. A skill’s location defines its scope.

When Codex loads available skills from these locations, it overwrites skills with the same name from a scope of lower precedence. The list below shows skill scopes and locations in order of precedence (high to low):

| Skill Scope | Location                                                                                                                                     | Suggested Use                                                                                                                                                                                                |
| :---------- | :------------------------------------------------------------------------------------------------------------------------------------------- | :----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `REPO`      | `$CWD/.codex/skills` <br/> Current Working Directory: where you launch Codex.                                                                | If in a repository or code environment, teams can check in skills most relevant to a working folder here. For instance, skills only relevant to a microservice or a code module.                             |
| `REPO`      | `$CWD/../.codex/skills` <br/> A folder above CWD when you launch Codex inside a git repository.                                              | If in a repository with nested folders, organizations can check in skills most relevant to a shared area in a parent folder.                                                                                 |
| `REPO`      | `$REPO_ROOT/.codex/skills` <br /> The top-most root folder when you launch Codex inside a git repository.                                    | If in a repository with nested folders, organizations can check in skills that are relevant to everyone using the repository. These serve as root skills that any subfolder in the repository can overwrite. |
| `USER`      | `$CODEX_HOME/skills` <br /> <small>(Mac/Linux default: `~/.codex/skills`)</small> <br /> Any skills checked into the user’s personal folder. | Use to curate skills relevant to a user that apply to any repository the user may work in.                                                                                                                   |
| `ADMIN`     | `/etc/codex/skills` <br /> Any skills checked into the machine or container in a shared, system location.                                    | Use for SDK scripts, automation, and for checking in default admin skills available to each user on the machine.                                                                                             |
| `SYSTEM`    | Bundled with Codex.                                                                                                                          | Useful skills relevant to a broad audience such as the skill-creator and plan skills. Available to everyone when they start Codex and can be overwritten by any layer above.                                 |

## Create a skill

To create a new skill, use the built-in `$skill-creator` skill inside Codex. Describe what you want your skill to do, and Codex will start bootstrapping your skill. If you combine it with the `$create-plan` skill (experimental; install it first with `$skill-installer create-plan`), Codex will first create a plan for your skill.

For a step-by-step walkthrough, see [Create custom skills](/codex/skills/create-skill).

You can also create a skill manually by creating a folder with a `SKILL.md` file inside a valid skill location. A `SKILL.md` must contain a `name` and `description` to help Codex select the skill:

```md
---
name: skill-name
description: Description that helps Codex select the skill
metadata:
  short-description: Optional user-facing description
---

Skill instructions for the Codex agent to follow when using this skill.
```

Codex skills build on the [Agent Skills specification](https://agentskills.io/specification). Check out the documentation to learn more.

## Install new skills

To expand on the list of built-in skills, you can download skills from a [curated set of skills on GitHub](https://github.com/openai/skills) using the `$skill-installer` skill:

```
$skill-installer linear
```

You can also prompt the installer to download skills from other repositories.

## Skill examples

### Plan a new feature

`$create-plan` is an experimental skill that you can install with `$skill-installer` to have Codex research and create a plan to build a new feature or solve a complex problem:

```
$skill-installer create-plan
```

### Access Linear context for Codex tasks

```
$skill-installer linear
```

<div class="not-prose my-4">
  <video
    class="w-full rounded-lg border border-default"
    controls
    playsinline
    preload="metadata"
  >
    <source
      src="https://cdn.openai.com/codex/docs/linear-example.mp4"
      type="video/mp4"
    />
  </video>
</div>

### Have Codex access Notion for more context

```
$skill-installer notion-spec-to-implementation
```

<div class="not-prose my-4">
  <video
    class="w-full rounded-lg border border-default"
    controls
    playsinline
    preload="metadata"
  >
    <source
      src="https://cdn.openai.com/codex/docs/notion-spec-example.mp4"
      type="video/mp4"
    />
  </video>
</div>