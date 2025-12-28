# Quickstart

Codex is OpenAI's coding agent that can read, modify, and run code. It helps you build faster, squash bugs, and understand unfamiliar code.

It meets you where you are: in your terminal, in your IDE, or you can also run tasks in the cloud, in the Codex interface or in GitHub.

Codex is included in ChatGPT Plus, Pro, Business, Edu, and Enterprise plans as well as available using API Credits on the OpenAI API Platform.

## Setup

You'll need a ChatGPT Plus, Pro, Business, Edu, or Enterprise plan to use Codex on every surface. This is the recommended way to authenticate with Codex as it includes the latest models and features.

If you prefer to use Codex locally with an OpenAI API key, you can follow the steps in the [Using Codex with your API key](#using-codex-with-your-api-key) section below.

Once you're set up, you can sign in with your ChatGPT account and use Codex in different ways:

- **Cloud agent**: navigate to [chatgpt.com/codex](https://chatgpt.com/codex) or tag `@codex` in a GitHub PR to use Codex in the cloud (requires sign in with ChatGPT).
- **IDE extension**: install the Codex extension for your IDE and use it in your editor.
- **CLI**: install the Codex CLI and use it in your terminal.

If you authenticate with your ChatGPT account, you can also delegate tasks to the cloud agent from the IDE extension.

## Cloud agent

To use Codex in the cloud, you should start by configuring a new environment for Codex to work in.
You can do this by navigating to the environment settings page at [chatgpt.com/codex](https://chatgpt.com/codex/settings/environments) and following the steps there to connect a GitHub repository.

<DocsTip>
  You can learn more about how to configure environments in the [dedicated
  page](/codex/cloud/environments).
</DocsTip>

Once your environment is set up, you can launch coding tasks from the [interface](https://chatgpt.com/codex), and follow progress there.
You can inspect logs in real-time to follow along while Codex is working, or you can let it run in the background.

When a task is done, you will be able to review the proposed changes in the interface in the form of diffs, iterate if needed, and create a PR in your GitHub repository.

Codex will show you a preview of the changes and you're welcome to accept the PR as is, or you can check out the branch locally and test the changes.

You can do this by running the following commands (assuming you have already cloned your repository):

```bash
git fetch
git checkout branch-name
```

To learn more about how to delegate tasks to Codex in the cloud, refer to our [dedicated guide](/codex/cloud).

## IDE extension

You can install the Codex extension for your IDE and use it in your editor:

- [Download for Visual Studio Code](vscode:extension/openai.chatgpt)
- [Download for Cursor](cursor:extension/openai.chatgpt)
- [Download for Windsurf](windsurf:extension/openai.chatgpt)
- [Download for Visual Studio Code Insiders](https://marketplace.visualstudio.com/items?itemName=openai.chatgpt)

Once installed, you'll find the extension in your sidebar next to other extensions - it might be hidden in the collapsed section.
Most people like dragging "Codex" to the right side of the editor.

You will be prompted to sign in with your ChatGPT account to get started ([you can also use your API key](#using-codex-with-your-api-key)).

Once signed in, you will be able to use Codex in your editor. By default, it will run in "Agent" mode, which means it can read files, make edits, and run commands in the current directory.

You can undo edits from the editor, but we recommend creating git checkpoints before and after each task to be able to revert to a previous state if needed.

You can learn more about how to use the IDE extension in our [dedicated guide](/codex/ide).

## CLI

The Codex CLI is a coding agent that you can run locally from your terminal and that can read, modify, and run code on your machine.

<DocsTip>
  The Codex CLI officially supports macOS and Linux. Windows support is still
  experimental—we recommend running in WSL.
</DocsTip>

### Installation

Install the Codex CLI with your preferred package manager:

#### Install with npm

```bash
npm install -g @openai/codex
```

#### Install with Homebrew

```bash
brew install codex
```

### Usage

Run `codex` in your terminal to get started:

```bash
codex
```

This will run the Codex CLI with default settings, and you will be prompted to authenticate.
We recommend signing in with your ChatGPT account, as you have included usage credits.

You will then be able to ask Codex to perform tasks in the current directory.

As Codex can make edits to your codebase, we recommend creating git checkpoints before and after each task to be able to revert to a previous state if needed.

You can configure which model, approval mode, prompt or other parameters to use directly from the CLI.

Refer to our [Codex CLI overview](/codex/cli) page for more details.

## Working with Codex

You are now ready to start using Codex in your preferred environment.

A typical workflow looks like this:

1. Start with the Codex CLI to generate code for a new project
2. Open your preferred IDE to make edits, assisted by the Codex IDE extension
3. If you want to build new features that are pretty much independent from the current codebase, you can delegate this to the Codex cloud agent (e.g. adding auth, connecting to a database, adding new pages, etc.)
4. Review the changes in the Codex interface and create PRs on GitHub
5. Check out the PR locally and test the changes
6. If changes are needed, you can iterate on the PR on GitHub by tagging `@codex` in a comment
7. While this is happening, you can continue working on other tasks in your IDE
8. Once you are satisfied with the changes, you can merge the PR
9. Repeat the process with other tasks

### Next steps

You can learn more about how to use Codex in these different environements in the dedicated guides:

- [Codex CLI](/codex/cli)
- [Codex IDE](/codex/ide)
- [Codex Cloud](/codex/cloud)

You can also dive deeper into how to prompt Codex in our [prompting guide](/codex/prompting), or how to configure Codex for enterprise in our [enterprise admin guide](/codex/enterprise).

## Using Codex with your API key

Using Codex with ChatGPT is recommended because it includes the newest models and full access to all Codex features. For [headless automation](/codex/sdk), scripting, or if you prefer to use API credits from the OpenAI API platform, you can authenticate with an API key instead.

Set up your API key access:

1. Make sure you have [API credits](https://platform.openai.com/account/credits) available on your OpenAI platform account.
2. Generate a key in the [API keys dashboard](https://platform.openai.com/api-keys) and export it as `OPENAI_API_KEY` in your shell profile.
3. In the CLI, set `preferred_auth_method = "apikey"` in `~/.codex/config.toml`, or run `codex --config preferred_auth_method="apikey"` for a single session.
4. In the IDE extension, choose **Use API key** when prompted and ensure your environment variable is set.

You can switch back to ChatGPT sign-in anytime (the default) by running `codex --config preferred_auth_method="chatgpt"` in the CLI or selecting the ChatGPT option in the IDE prompt.