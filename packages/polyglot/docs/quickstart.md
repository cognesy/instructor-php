---
title: Quickstart
description: 'Run your first inference request with Polyglot.'
meta:
  - name: 'has_code'
    content: true
---

Install the package:

```bash
composer require cognesy/instructor-polyglot
```

Set an API key for a bundled preset such as OpenAI:

```bash
export OPENAI_API_KEY=...
```

Then run a request:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

$text = Inference::using('openai')
    ->withMessages('Explain dependency injection in one short paragraph.')
    ->get();
```

That is the standard flow:

1. Pick a preset with `using(...)`.
2. Add messages.
3. Execute with `get()`, `response()`, `asJsonData()`, or `stream()`.

Next:

- use [Setup](setup) to define your own presets
- use [Essentials](essentials/overview) for the request and response API
- use [Embeddings](embeddings/overview) for vector generation
