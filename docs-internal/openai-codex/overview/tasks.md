# Tasks & Prompts

## Local tasks

Codex can perform two types of tasks for you: local tasks and [cloud tasks](#cloud-tasks).

Codex completes local tasks directly on your machine. This can be your personal laptop, desktop, or even a server you have access to.

For local tasks, Codex directly interacts with your local file system to change files and run commands. This means you can see which files are changing in real time, let Codex use your local tools, and have it jump into parts of your codebase that you are currently working on.

To [limit the risk of Codex modifying files outside of your workspace](/codex/security), or perform other undesired actions, Codex runs local tasks in a [sandbox](#sandbox) environment by default.

## Cloud tasks

The alternative to local tasks is cloud tasks, which are helpful when you want Codex to work on tasks in parallel or when inspiration strikes on the go.

Codex runs each cloud task in an isolated [environment](/codex/cloud/environments) that allows the Codex agent to work on the task in a secure and isolated way. To set up the environment, Codex will clone your repository and check out the relevant branch it's working on. To use Codex for cloud tasks, push your code to GitHub first. If you haven't pushed your code to GitHub yet, you can also use the Codex CLI or IDE extension to [delegate tasks from your local machine](/codex/ide/cloud-tasks), which includes the current code you are working on.

By default, environments come with common programming languages and dependency management tools. To get the most out of Codex cloud tasks, you can also install more packages and enable internet access by [customizing the environment](/codex/cloud/environments) for your project.

## Codex interfaces

Codex is available through a range of interfaces depending on your use case. You can use Codex in [your terminal](/codex/cli), [your IDE](/codex/ide), on [GitHub](/codex/integrations/github), in [Slack](/codex/integrations/slack), and more. The goal is for Codex to be available wherever you are, whenever you need it. 

[Codex Web](/codex/cloud) is our web interface available at [chatgpt.com/codex](https://chatgpt.com/codex). You can use Codex Web to configure your cloud task environments, delegate tasks to Codex, and track [code reviews](/codex/integrations/github).

## Prompting Codex

Just like ChatGPT, Codex is only as effective as the instructions you give it. Here are some tips we find helpful when prompting Codex:

- Codex produces higher-quality outputs when it can verify its work. Provide **steps to reproduce an issue, validate a feature, and run any linter or pre-commit checks**. If additional packages or custom setups are needed, see [Environment configuration](/codex/cloud/environments).

- Like a human engineer, Codex handles really complex work better when it's broken into smaller, focused steps. Smaller tasks are easier for Codex to test and for you to review. You can even ask Codex to help break tasks down.