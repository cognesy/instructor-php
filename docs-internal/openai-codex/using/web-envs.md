# Cloud environments

While Codex cloud tasks work out of the box, you can customize the agent's environment to e.g. install dependencies and tools. Having access to a fuller set of dependencies, linters, formatters, etc. often results in better agent performance.

Configure your environments in [Codex settings](https://chatgpt.com/codex/settings/environments).

## How Codex cloud tasks work

Under the hood, here's what happens when you submit a task:

1. We prepare a containerized environment with, your repo's code at the desired branch or sha, and your setup & maintenance scripts.
1. We [configure internet access](/codex/cloud/agent-internet) for the agent. Internet access is off by default, but you can configure the environment to have limited or full internet access.
1. The agent then runs terminal commands in a loop. It writes code, runs tests, and attempts to check its work. The agent attempts to honor any specified lint or test commands [you've defined in an `AGENTS.md` file](/AGENTS.md). The agent does not have access to any special tools outside of the terminal or CLI tools you provide.
1. When the agent is done, it presents its answer and a diff of any code it modified.
1. You can choose to open a PR or ask for followups.

## Default universal image

The Codex agent runs in a default container image called `universal`, which comes pre-installed with common languages, packages, and tools.

_Set package versions_ in environment settings can be used to configure the version of Python, Node.js, etc.

<DocsTip>
  For details on what's installed, see
  [openai/codex-universal](https://github.com/openai/codex-universal) for a
  reference Dockerfile and an image that can be pulled and tested locally.
</DocsTip>

While `codex-universal` comes with languages pre-installed for speed and convenience, you can also install additional packages to the container using [setup scripts](#manual-setup).

## Environment variables and secrets

**Environment variables** can be specified and are set for the full duration of the task.

**Secrets** can also be specified and are similar to environment variables, except:

- They are stored with an additional layer of encryption and are only decrypted for task execution.
- They are only available to setup scripts. For security reasons, secrets are removed from the environment when the agent is running.

## Automatic setup

For projects using common package managers (`npm`, `yarn`, `pnpm`, `pip`, `pipenv`, and `poetry`), Codex can automatically install dependencies and tools.

## Manual setup

If your development setup is more complex, you can also provide a custom setup script. For example:

```bash
# Install type checker
pip install pyright
# Install dependencies
poetry install --with test
pnpm install
```

<DocsTip>
  Setup scripts are run in a separate bash session than the agent, so commands
  like `export` do not persist. You can persist environment variables by adding
  them to `~/.bashrc`.
</DocsTip>

## Container Caching

Codex caches container state to make running new tasks and followups faster. Environments that are cached will have the repository cloned with the default branch checked out. Then the setup script is run, and the resulting container state is cached for up to 12 hours. When a container is resumed from the cache, we check out the branch specified for the task, and then run the maintenance script. The maintenance script is optional, and helpful to update dependencies for cached containers where the setup script was run on an older commit.

We will automatically invalidate the cache and remove any cached containers if there are changes to the setup script, maintenance script, environment variables, or secrets. If there are changes in the repository that would cause backwards incompatibility issues, you can manually invalidate the cache with the "Reset cache" button on the environment page.

<DocsTip>
  For Business and Enterprise users, caches are shared across all users who have
  access to the environment. Invalidating the cache will affect all users of the
  environment in your workspace.
</DocsTip>

## Internet access and network proxy

Internet access is available to install dependencies during the setup script phase. During the agent phase, the network access is disabled by default, but you can configure the environment to have limited or full access to the internet. [Learn more about configuring your agent's internet access](/codex/cloud/agent-internet).

Environments run behind an HTTP/HTTPS network proxy for security and abuse prevention purposes. All outbound internet traffic passes through this proxy.

## Using the Codex CLI to run Codex in the cloud

If you're running into challenges making your development setup work in Codex's cloud environment, you can consider running the Codex CLI locally or in a background envionments such as devboxes or CI.