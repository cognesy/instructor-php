# Codex cloud

Codex is OpenAI's coding agent that can read, modify, and run code. It helps you build faster, squash bugs, and understand unfamiliar code. Codex can work on many tasks in the background, in parallel, and even proactively, using its own environment in the cloud.

## Getting started

Start by browsing to [chatgpt.com/codex](https://chatgpt.com/codex), where you can connect your GitHub account so that Codex can work with the code in your repositories, and so that you can create pull requests from its work.

Codex is included in your Plus, Pro, Business, Edu, or Enterprise plan. [Learn more about what's included](https://help.openai.com/en/articles/11369540-codex-in-chatgpt). Note that some Enterprise workspaces may require [admin setup](/codex/enterprise) before you can access Codex.

## Delegating to Codex

You can ask Codex to read, write, and execute code in your repositories, in order to answer questions or draft PRs.

When you start a cloud task, Codex provisions a sandboxed cloud container for just that task, provisioned with the code and dependencies you can specify in an environment. This means Codex can work in the background, on many tasks in parallel, and can be triggered from different devices or services such as your phone or GitHub. [Learn more about how to configure cloud environments](/codex/cloud/environments).

You can delegate work to Codex from most Codex clients: web, the IDE extension, the Codex tab in iOS, and or even tagging `@codex` in GitHub. (CLI support for cloud delegation is coming soon.)

### Example prompts

Use ask mode to get advice and insights on your code, no changes applied.

1. **Refactoring suggestions**
   Codex can help brainstorm structural improvements, such as splitting files, extracting functions, and tightening documentation.

```
Take a look at <hairiest file in my codebase>.
Can you suggest better ways to split it up, test it, and isolate functionality?
```

2. **Q\&A and architecture understanding**
   Codex can answer deep questions about your codebase and generate diagrams.

```
Document and create a mermaidjs diagram of the full request flow from the client
endpoint to the database.
```

Use code mode when you want Codex to actively modify code and prepare a pull request.

1. **Security vulnerabilities**
   Codex excels at auditing intricate logic and uncovering security flaws.

```
There's a memory-safety vulnerability in <my package>. Find it and fix it.
```

2. **Code review**
   Append `.diff` to any pull request URL and include it in your prompt. Codex loads the patch inside the container.

```
Please review my code and suggest improvements. The diff is below:
<diff>
```

3. **Adding tests**
   After implementing initial changes, follow up with targeted test generation.

```
From my branch, please add tests for the following files:
<files>
```

4. **Bug fixing**
   A stack trace is usually enough for Codex to locate and correct the problem.

```
Find and fix a bug in <my package>.
```

5. **Product and UI fixes**
   Although Codex cannot render a browser, it can resolve minor UI regressions and you can provide images as input to provide additional context.

```
The modal on our onboarding page isn't centered. Can you fix it?
```

## Account Security and Multi-Factor Authentication

Because Codex interacts directly with your codebase, it requires a higher level of account security compared to many other ChatGPT features.

### Social Login (Google, Microsoft, Apple)

If you use a social login provider (Google, Microsoft, Apple), you are not required to enable multi-factor authentication (MFA) on your ChatGPT account. However, we strongly recommend setting it up with your social login provider if you have not already.

More information about setting up multi-factor authentication with your social login provider can be found here:

- [Google](https://support.google.com/accounts/answer/185839)
- [Microsoft](https://support.microsoft.com/en-us/topic/what-is-multifactor-authentication-e5e39437-121c-be60-d123-eda06bddf661)
- [Apple](https://support.apple.com/en-us/102660)

### Single Sign-On (SSO)

If you access ChatGPT via Single Sign-On (SSO), your organization's SSO administrator should ensure MFA is enforced for all users if not already configured.

### Email and Password

If you log in using an email and password, you will be required to set up MFA on your account before accessing Codex.

### Multiple Login Methods

If your account supports multiple login methods and one of those login methods is by using an email and password, you must set up MFA regardless of the method you currently use to log in before accessing Codex.