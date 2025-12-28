# Codex GitHub Action

Use the Codex GitHub Action (`openai/codex-action@v1`) when you need Codex to participate in CI/CD jobs, apply patches, or post reviews straight from a GitHub Actions workflow. The action installs the Codex CLI, starts the Responses API proxy when you provide an API key, and then runs `codex exec` under the permissions you specify.

Reach for the action when you want to:

- Automate Codex feedback on pull requests or releases without managing the CLI yourself.
- Gate changes on Codex-driven quality checks as part of your CI pipeline.
- Run repeatable Codex tasks (code review, release prep, migrations) from a workflow file.

Learn how to apply this to failing CI runs with the [autofix CI guide](/codex/autofix-ci) and explore the source in the [openai/codex-action repository](https://github.com/openai/codex-action).

### Prerequisites

- Store your OpenAI key as a GitHub secret (for example `OPENAI_API_KEY`) and reference it in the workflow.
- Ensure the job runs on a Linux or macOS runner; Windows is supported only with `safety-strategy: unsafe`.
- Check out your code before invoking the action so Codex can read the repository contents.
- Decide which prompts you want to run. You can provide inline text via `prompt` or point to a file committed in the repo with `prompt-file`.

### Example workflow

The sample workflow below reviews new pull requests, captures Codex’s response, and posts it back on the PR.

```yaml
name: Codex pull request review
on:
  pull_request:
    types: [opened, synchronize, reopened]

jobs:
  codex:
    runs-on: ubuntu-latest
    permissions:
      contents: read
      pull-requests: write
    outputs:
      final_message: ${{ steps.run_codex.outputs.final-message }}
    steps:
      - uses: actions/checkout@v5
        with:
          ref: refs/pull/${{ github.event.pull_request.number }}/merge

      - name: Pre-fetch base and head refs
        run: |
          git fetch --no-tags origin \
            ${{ github.event.pull_request.base.ref }} \
            +refs/pull/${{ github.event.pull_request.number }}/head

      - name: Run Codex
        id: run_codex
        uses: openai/codex-action@v1
        with:
          openai-api-key: ${{ secrets.OPENAI_API_KEY }}
          prompt-file: .github/codex/prompts/review.md
          output-file: codex-output.md
          safety-strategy: drop-sudo
          sandbox: workspace-write

  post_feedback:
    runs-on: ubuntu-latest
    needs: codex
    if: needs.codex.outputs.final_message != ''
    steps:
      - name: Post Codex feedback
        uses: actions/github-script@v7
        with:
          github-token: ${{ github.token }}
          script: |
            await github.rest.issues.createComment({
              owner: context.repo.owner,
              repo: context.repo.repo,
              issue_number: context.payload.pull_request.number,
              body: process.env.CODEX_FINAL_MESSAGE,
            });
        env:
          CODEX_FINAL_MESSAGE: ${{ needs.codex.outputs.final_message }}
```

Replace `.github/codex/prompts/review.md` with your own prompt file or use the `prompt` input for inline text. The example also writes the final Codex message to `codex-output.md` for later inspection or artifact upload.

### Configure Codex Exec

Fine-tune how Codex runs by setting the action inputs that map to `codex exec` options:

- `prompt` or `prompt-file` (choose one) — inline instructions or a repository path to Markdown or text with your task. Consider storing prompts in `.github/codex/prompts/`.
- `codex-args` — extra CLI flags. Provide a JSON array (for example `["--full-auto"]`) or a shell string (`--full-auto --sandbox danger-full-access`) to allow edits, streaming, or MCP configuration.
- `model` and `effort` — pick the Codex agent configuration you want; leave empty for defaults.
- `sandbox` — match the sandbox mode (`workspace-write`, `read-only`, `danger-full-access`) to the permissions Codex needs during the run.
- `output-file` — save the final Codex message to disk so later steps can upload or diff it.
- `codex-version` — pin a specific CLI release. Leave blank to use the latest published version.
- `codex-home` — point to a shared Codex home directory if you want to reuse config files or MCP setups across steps.

### Manage privileges

Codex inherits substantial access on GitHub-hosted runners unless you restrict it. Use these inputs to control exposure:

- `safety-strategy` (default `drop-sudo`) removes `sudo` before running Codex. This is irreversible for the job and protects secrets in memory. On Windows you must set `safety-strategy: unsafe`.
- `unprivileged-user` pairs `safety-strategy: unprivileged-user` with `codex-user` to run Codex as a specific account. Ensure the user can read and write the repository checkout (see `.cache/codex-action/examples/unprivileged-user.yml` for an ownership fix).
- `read-only` keeps Codex from changing files or using the network, but it still runs with elevated privileges. Do not rely on `read-only` alone to protect secrets.
- `sandbox` limits filesystem and network access within Codex itself. Choose the narrowest option that still lets the task complete.
- `allow-users` and `allow-bots` restrict who can trigger the workflow. By default only users with write access can run the action; list extra trusted accounts explicitly or leave the field empty for the default behavior.

### Capture outputs

The action emits the last Codex message through the `final-message` output. Map it to a job output (as shown above) or handle it directly in later steps. Combine `output-file` with the uploaded artifacts feature if you prefer to collect the full transcript from the runner. When you need structured data, pass `--output-schema` through `codex-args` to enforce a JSON shape.

### Security checklist

- Limit who can start the workflow. Prefer trusted events or explicit approvals instead of allowing everyone to run Codex against your repository.
- Sanitize prompt inputs from pull requests, commit messages, or issue bodies to avoid prompt injection. Review HTML comments or hidden text before feeding it to Codex.
- Protect your `OPENAI_API_KEY` by keeping `safety-strategy` on `drop-sudo` or moving Codex to an unprivileged user. Never leave the action in `unsafe` mode on multi-tenant runners.
- Run Codex as the last step in a job so subsequent steps do not inherit any unexpected state changes.
- Rotate keys immediately if you suspect the proxy logs or action output exposed secret material.

### Troubleshooting

- **Only one of prompt or prompt-file may be specified** — remove the duplicate input so exactly one source remains.
- **responses-api-proxy did not write server info** — confirm the API key is present and valid; the proxy only starts when `openai-api-key` is set.
- **Expected sudo to be disabled, but sudo succeeded** — ensure no earlier step re-enabled `sudo` and that the runner OS is Linux or macOS. Re-run with a fresh job.
- **Permission errors after `drop-sudo`** — grant write access before the action runs (for example with `chmod -R g+rwX "$GITHUB_WORKSPACE"` or by using the unprivileged-user pattern).
- **Unauthorized trigger blocked** — adjust `allow-users` or `allow-bots` inputs if you need to permit service accounts beyond the default write collaborators.