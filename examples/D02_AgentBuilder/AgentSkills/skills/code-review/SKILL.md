---
name: code-review
description: Review code for quality, bugs, and best practices. Use when asked to review or audit code.
license: MIT
allowed-tools: Read Grep Glob
---

When reviewing code, check for:

1. **Logic errors** and unhandled edge cases
2. **Security vulnerabilities** (injection, XSS, auth issues)
3. **Performance** issues (N+1 queries, unnecessary allocations)
4. **Style consistency** with the rest of the codebase

Provide specific line references and concrete fix suggestions.
Focus on $ARGUMENTS if provided.
