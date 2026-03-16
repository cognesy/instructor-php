---
title: Troubleshooting
description: Common causes of broken requests in Polyglot and how to resolve them.
---

This section covers the most common issues you may encounter when working with Polyglot, along with practical guidance for resolving them quickly.

## Common Categories

Most problems fall into one of these categories:

1. **[Authentication Issues](/troubleshooting/issues-authentication)** -- Missing or invalid API keys, wrong environment variables, or incorrect key formats for a given provider.

2. **[Configuration Issues](/troubleshooting/issues-configuration)** -- Preset files that cannot be found, missing required fields, or type mismatches in configuration values.

3. **[Connection Issues](/troubleshooting/issues-connection)** -- Network failures, incorrect API URLs, proxy or firewall rules blocking outbound requests, and DNS resolution problems.

4. **[Rate Limits](/troubleshooting/issues-rate-limits)** -- HTTP 429 responses from providers, quota exhaustion, and strategies for retry and throttling.

5. **[Model-Specific Issues](/troubleshooting/issues-model-specific)** -- Capability differences between models such as tool support, JSON schema output, context length limits, and vision features.

6. **[Provider-Specific Issues](/troubleshooting/issues-provider-specific)** -- Quirks unique to individual providers like Anthropic's message format, Gemini's native API, OpenAI organization IDs, and local Ollama setup.

7. **[Streaming Issues](/troubleshooting/issues-streaming)** -- Premature stream termination, stream reuse errors, output buffering, and connection timeouts during long-running streams.

8. **[Debugging](/troubleshooting/debugging)** -- Using events, wiretapping, and HTTP-level inspection to understand what Polyglot sends and receives.

## Quick Diagnosis Checklist

Before diving into specific pages, run through this checklist:

- **Is the API key set?** Check the environment variable referenced in your preset (e.g. `OPENAI_API_KEY`, `ANTHROPIC_API_KEY`).
- **Is the preset name correct?** The name passed to `Inference::using('...')` must match a YAML file in the preset directory.
- **Does the model support the feature you are requesting?** Not all models support tools, JSON schema output, or streaming. Try a plain text request first.
- **Are provider-specific options bleeding across providers?** Remove custom `options` entries and add them back one at a time.
- **Is the stream being consumed only once?** Calling `deltas()` a second time throws a `LogicException`.

When in doubt, attach a `wiretap()` listener to the runtime to see every event Polyglot dispatches during the request lifecycle. See the [Debugging](/troubleshooting/debugging) page for details.
