---
name: project-context
description: Inject live project context via shell preprocessing
argument-hint: "[file-or-directory]"
---

## Project Context

Current date: !`date +%Y-%m-%d`
Git branch: !`git branch --show-current 2>/dev/null || echo unknown`

Use this context when reviewing $ARGUMENTS.
