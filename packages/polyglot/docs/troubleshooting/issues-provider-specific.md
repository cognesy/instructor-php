---
title: Provider-Specific Issues
description: Keep the request shape close to the selected driver.
---

If a request works on one provider and fails on another:

- remove provider-specific fields from `options`
- test again with plain text output
- add `responseFormat`, tools, or streaming back one feature at a time

Polyglot normalizes many differences, but it does not erase real provider capability differences.
