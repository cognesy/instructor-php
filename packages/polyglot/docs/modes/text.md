---
title: Plain Text Responses
description: Plain text is the default inference path.
---

If you do not set `responseFormat`, Polyglot asks for a normal text response.

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

$text = Inference::using('openai')
    ->withMessages('What is the single responsibility principle?')
    ->get();
```
