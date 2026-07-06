# Git helpers — conventional commit message generation (uses Claude CLI).

# Auto-commit current changes with a generated conventional commit message.
[group('git'), doc('Auto-commit with a generated conventional message')]
git-auto-commit:
    bash ./scripts/git/auto-commit.sh

# Generate (print) a conventional commit message for current changes.
[group('git'), doc('Generate a conventional commit message')]
git-commit-msg:
    bash ./scripts/git/generate-commit-message.sh

# Generate a commit message spanning all changed packages.
[group('git'), doc('Commit message across all changed packages')]
git-commit-msg-all:
    bash ./scripts/git/commit-msg-all.sh

# Generate a commit message for one package.
[group('git'), doc('Commit message for one package')]
git-commit-msg-package pkg:
    bash ./scripts/git/commit-msg-package.sh {{pkg}}
