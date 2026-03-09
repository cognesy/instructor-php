---
title: Connection Issues
description: Network failures sit below Polyglot's request layer.
---

If requests cannot reach the provider:

- verify the configured `apiUrl` and `endpoint`
- verify outbound network access from the app environment
- inject your own HTTP client if you need custom timeouts, proxies, or transport debugging

Polyglot itself only consumes the shared HTTP transport contract.
