# Windows

Codex support for Windows is still early, but improving rapidly.
The easiest way to use Codex on Windows is to [set up the IDE extension](/codex/ide), or [install the CLI](/codex/cli) and run it from PowerShell.

When running natively on Windows, Codex supports a powerful Agent mode which can read files, write files, and run commands in your working folder. Agent mode uses an experimental Windows sandbox to limit filesystem access outside the working folder, as well as to prevent network access without your explicit approval. Use this if you're comfortable with the risks. [Learn more below](#windows-experimental-sandbox).

Alternately, you can install and use [Windows Subsystem for Linux](https://learn.microsoft.com/en-us/windows/wsl/install) (WSL2). WSL2 gives you a Linux shell, unix-style semantics, and tooling that match the majority of tasks that our models see in training. Importantly, the Codex sandbox implementation on Linux is mature.

## Windows Subsystem for Linux

### Launch VS Code from inside WSL

For a detailed walkthrough, follow the [official VS Code WSL tutorial](https://code.visualstudio.com/docs/remote/wsl-tutorial).

#### Prerequisites

- Windows with WSL installed - we recommend an Ubuntu distribution. Install by shift+clicking on Powershell to open as an Administrator, then running `wsl --install`.
- VS Code with the [WSL extension](https://marketplace.visualstudio.com/items?itemName=ms-vscode-remote.remote-wsl) installed.

#### Open VS Code from a WSL terminal

```bash
# From your WSL shell
cd ~/code/your-project
code .
```

This opens a WSL remote window, installs the VS Code Server if needed, and ensures integrated terminals run in Linux.

#### Confirm you’re connected to WSL

- Look for the green status bar that shows `WSL: <distro>`.
- Integrated terminals should display Linux paths (such as `/home/...`) instead of `C:\`.
- You can verify with:

  ```bash
  echo $WSL_DISTRO_NAME
  ```

  which should print your distribution name.

<DocsTip>
  If you don't see "WSL: ..." in the status bar, press `Ctrl+Shift+P`, pick
  `WSL: Reopen Folder in WSL`, and keep your repo under `/home/...` (not `C:\`)
  for best performance.
</DocsTip>

### Using Codex CLI with WSL

Run these commands in an elevated PowerShell or Windows Terminal:

```powershell
# Install default Linux distribution (like Ubuntu)
wsl --install

# Start a shell inside of Windows Subsystem for Linux
wsl

# https://learn.microsoft.com/en-us/windows/dev-environment/javascript/nodejs-on-wsl
# Install Node.js in WSL
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/master/install.sh | bash

# In a new tab or after exiting and running `wsl` again to install Node.js
nvm install 22

# Install and run Codex in WSL
npm i -g @openai/codex
codex
```

### Working on code inside WSL

- Working in Windows-mounted paths like <code>/mnt/c/...</code> can be slower than when working in them in Windows. Keep your repos under your Linux home directory (like <code>~/code/my-app</code>) for faster I/O and fewer symlink/permission issues:
  ```bash
  mkdir -p ~/code && cd ~/code
  git clone https://github.com/your/repo.git
  cd repo
  ```
- If you need Windows access to files, they’re under <code>\\wsl$\Ubuntu\home\&lt;user&gt;</code> in Explorer.

### Troubleshooting & FAQ

#### Installed extension, but it's unresponsive

Your system may be missing C++ development tools, which some native dependencies require:

- Visual Studio Build Tools (C++ workload)
- Microsoft Visual C++ Redistributable (x64)
- With winget: `winget install --id Microsoft.VisualStudio.2022.BuildTools -e`

Then fully restart VS Code after installation.

#### If it feels slow on large repos

- Make sure you’re not working under <code>/mnt/c</code>. Move the repo to WSL (e.g., <code>~/code/...</code>).
- Allocate more memory/CPU to WSL if constrained; update WSL to latest:
  ```powershell
  wsl --update
  wsl --shutdown
  ```

#### VS Code in WSL can’t find `codex`

Verify the binary exists and is on PATH inside WSL:

```bash
which codex || echo "codex not found"
```

If the binary is not found, try installing by [following the instructions](#install-codex-in-windows-subsystem-for-linux-wsl) earlier in this guide.