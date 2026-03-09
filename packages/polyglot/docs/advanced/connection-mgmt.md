---
title: Preset Management
description: Switch providers by changing presets, not request code.
---

Polyglot is designed so the request shape stays the same while the preset changes.

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

$prompt = 'Explain dependency injection in one sentence.';

$openai = Inference::using('openai')->withMessages($prompt)->get();
$anthropic = Inference::using('anthropic')->withMessages($prompt)->get();
```

This is the main provider-switching story in Polyglot:

- keep request code stable
- swap presets
- override model per request only when needed

Fallback behavior belongs in application code. Polyglot does not impose a fallback policy.
