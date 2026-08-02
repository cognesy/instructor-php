# Rollout and Verification

## Controlled Cutover

1. Add generation, parity, Mintlify validation, and strict MkDocs build commands.
2. Prove them locally against the current source tree.
3. Land and push the unified workflow while retaining `main/builds/docs-build`, the
   legacy MkDocs workflow, and the current provider settings.
4. Populate `docs-mintlify` with a validated artifact and retain the current
   `gh-pages` publication as rollback state.
5. Switch Mintlify Git settings to `docs-mintlify` and `/`; verify production.
6. Switch GitHub Pages source to GitHub Actions, run the Pages deployment, and verify
   production.
7. Only after both replacements are live, land a second cleanup commit removing
   tracked generated trees, root generated config, and legacy workflows.
8. Run the complete clean-checkout verification set and live smoke checks against the
   cleanup commit.

The infrastructure landing and cleanup landing must remain separate. If a provider
cutover cannot be completed, do not remove the old live source and do not proceed to
the cleanup landing.

## Verification Matrix

| Requirement | Evidence |
|---|---|
| builds are ephemeral | `git ls-files builds` is empty and `git check-ignore builds/probe` succeeds |
| source generation works | both generator commands exit zero in a clean checkout |
| release parity | parity verifier exits zero and fails against an injected missing page fixture |
| Mintlify validity | pinned `mintlify validate` exits zero in generated root |
| MkDocs validity | pinned `mkdocs build --strict --clean` exits zero |
| PR protection | combined docs check is present and required on `main` |
| Mintlify deployment | `docs-mintlify` head identifies the source SHA and Mintlify deployment succeeds |
| MkDocs deployment | `github-pages` deployment uses the uploaded artifact and succeeds |
| current release live | both v2.5.2 pages return HTTP 200, both indexes list v2.5.2, and both provenance files match the expected source SHA |
| release enforcement | missing/unindexed version makes release preflight fail before mutation |
| clean release state | release script rejects any pre-existing staged or unstaged change |
| obsolete paths gone | targeted `rg` finds no stale command or deployment references |

## Local and CI Commands

The implementation must provide canonical commands for:

```bash
composer qa:docs-sites
composer qa:docs-sites -- --release 2.5.2
just docs-sites-check
scripts/docs/smoke-release-sites.sh 2.5.2
```

The exact wrappers may evolve during implementation, but one Composer entrypoint and
one release-version-aware smoke command are required.

## Rollback

- Mintlify before cleanup: point Git Settings back to `main` and the previous path.
- Mintlify after cleanup: keep Git Settings on `docs-mintlify` and publish the previous
  known-good generated tree as a new linear rollback commit; pointing back to `main`
  is invalid after generated content is removed.
- GitHub Pages: restore the previous Pages publishing source or rerun the last known
  successful artifact deployment.
- Repository: revert the migration commit; generated content can always be recreated
  from the source commit and pinned toolchain.

Rollback is provider configuration or commit reversal. It must never depend on local
untracked contents of `builds/`.
