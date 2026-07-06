# Instructor-PHP — task runner and single entrypoint for all workflows.
#
#   just              list every available command, grouped
#   just <cmd>        run a command
#   just --list       same catalog (alias of `just`)
#   just <cmd> --help nothing; use `just --show <cmd>` to see a recipe
#
# Commands are organized into modules under ./just/*.just and delegate to
# composer scripts or ./scripts/<group>/*.sh. Heavy logic lives in scripts/,
# recipes stay thin so this file is the map, not the implementation.

set shell := ["bash", "-c"]

# Show the grouped command catalog (default when you run bare `just`).
[group('help'), doc('List all commands, grouped')]
default:
    @just --list --unsorted

import 'just/setup.just'
import 'just/test.just'
import 'just/qa.just'
import 'just/docs.just'
import 'just/packages.just'
import 'just/release.just'
import 'just/git.just'
import 'just/cli.just'
import 'just/examples.just'

# Pre-push check: fast tests + full QA (the preferred local flow).
[group('workflow'), doc('Fast tests + full QA — run before pushing')]
verify: test qa

# Docs-sensitive check: fast tests + QA + docs QA.
[group('workflow'), doc('Fast tests + QA + docs QA')]
verify-docs: test qa docs-qa

# Release-sensitive check: all tests + QA + docs QA.
[group('workflow'), doc('All tests + QA + docs QA (release-sensitive)')]
verify-full: test-all qa docs-qa
