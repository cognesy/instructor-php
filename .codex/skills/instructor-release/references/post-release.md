# Post-Release Workflow

## After `scripts/release/publish-ver.sh`

Verify:

1. the main GitHub release exists
2. the tag exists remotely
3. `.github/workflows/split.yml` has started for the tag
4. Mintlify and GitHub Pages provenance identify the released source SHA
5. both release-note indexes link the new version

## Announcement Drafting

Do not improvise a posting workflow inside the release skill.

Use the release-note highlights and the template below to draft the
announcement locally. Do not assume that a separate announcement or posting
tool is installed.

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
