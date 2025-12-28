# Sandboxing

## Sandbox

Codex runs local tasks by default in a sandbox environment meaning the model is limited in which files it can access, which commands it can run without or even with approval and even control internet access. For Windows, we recommend you to run Codex locally in [Windows Subsystem for Linux (WSL)](https://learn.microsoft.com/en-us/windows/wsl/install) or a Docker container to provide secure isolation.

To learn more about the sandbox and what options you have to control the sandbox, check out the [security guide](/codex/security).

## Windows experimental sandbox

The Windows sandbox support is experimental. How it works:

- Launches commands inside a restricted token derived from an AppContainer profile.
- Grants only specifically requested filesystem capabilities by attaching capability SIDs to that profile.
- Disables outbound network access by overriding proxy-related environment variables and inserting stub executables for common network tools.

Its primary limitation is that it cannot prevent file writes, deletions, or creations in any directory where the Everyone SID already has write permissions (for example, world-writable folders). When using the Windows sandbox, Codex will scan for folders where Everyone has write access, and will recommend you remove that access. For more, see [Windows Sandbox Security Details](https://github.com/openai/codex/blob/main/docs/windows_sandbox_security.md).