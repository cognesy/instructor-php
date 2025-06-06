Instructor offers several ways to debug it's internal state and execution flow.

## Events

Instructor emits events at various points in its lifecycle, which you can listen to
and react to. You can use these events to debug execution flow and to inspect
data at various stages of processing.

For more details see the [Events](events.mdx) section.


## HTTP Debugging

The `StructuredOutput` class has a `withDebug()` method that can be used to debug the request and response.

```php
$result = (new StructuredOutput)
    ->withDebugPreset('on')
    ->with(
        messages: "Jason is 25 years old",
        responseModel: User:class,
    )
    ->get();
```

It displays detailed information about the request being sent to LLM API and response received from it,
including:

 - request headers, URI, method and body,
 - response status, headers, and body.

This is useful for debugging the request and response when you are not getting the expected results.


