# Post-Release Workflow

## After `scripts/publish-ver.sh`

Verify:

1. the main GitHub release exists
2. the tag exists remotely
3. `.github/workflows/split.yml` has started for the tag
4. docs bundles were attached to the GitHub release

## Announcement Drafting

Do not improvise a posting workflow inside the release skill.

Use:

- `$tweet-package` to draft the release announcement package
- `$xurl` only if the user explicitly wants to post

Recommended announcement shape:

- one sentence or one highlight
- mention the version
- mention the strongest user-facing win
- include the release-notes or docs URL if available

Example direction:

- “InstructorPHP vX.Y.Z is out: [strongest highlight]. Release notes: …”

## Posting Gate

- Drafting is allowed as part of release completion.
- Actual posting requires explicit user approval in the current conversation.
