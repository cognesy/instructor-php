# Streaming Miscellaneous

## Streaming JSON

You can stream a request that asks for native JSON object output by setting `responseFormat` explicitly.

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$stream = Inference::using('openai')
    ->with(
        messages: 'Return JSON with name and population for Paris.',
        responseFormat: ['type' => 'json_object'],
        options: ['stream' => true],
    )
    ->stream();
```

For tool-call streaming, use `tools` and `toolChoice` instead of any mode flag.
