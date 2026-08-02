# Release & publishing lifecycle.
# The split workflow (.github/workflows/split.yml) auto-splits packages to their
# individual repos after a vX.Y.Z tag is pushed by `just release`.

# Full release for version X.Y.Z: sync versions, validate and publish both docs
# sites, then tag, create the GitHub release, and trigger package splitting.
# Requires a clean checkout and an indexed docs/release-notes/vX.Y.Z.mdx.
[group('release'), doc('Cut a release after both docs sites verify the final SHA')]
release version:
    bash ./scripts/release/publish-ver.sh {{version}}

# Generate combined release notes for all changed packages (uses Claude CLI).
[group('release'), doc('Generate combined release notes (all changed pkgs)')]
release-notes:
    bash ./scripts/release/release-notes-all.sh

# Release-notes summary for one package since the last tag (uses Claude CLI).
[group('release'), doc('Release-notes summary for one package')]
release-notes-package pkg:
    bash ./scripts/release/release-notes-summary.sh {{pkg}}

# Raw git diff for one package since the last tag.
[group('release'), doc('Git diff for one package since last tag')]
release-diff pkg:
    bash ./scripts/release/release-notes-diff.sh {{pkg}}
