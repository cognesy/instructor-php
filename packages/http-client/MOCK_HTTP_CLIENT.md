# Mock HttpClient Cheatsheet

Pragmatic, dense guide for building HTTP/REST and LLM API tests without network calls. Uses `HttpClientBuilder` + `MockHttpDriver` and `MockHttpResponseFactory`. Includes streaming/SSE helpers and a fluent expectation DSL.

## Quick Start

- Create a mock-backed client:

```php
use Cognesy\Http\Creation\HttpClientBuilder;

$http = (new HttpClientBuilder())
    ->withMock(function ($mock) {
        // define expectations here
    })
    ->create();
```

- Or construct directly:

```php
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;

$mock = new MockHttpDriver();
$http = (new HttpClientBuilder())->withDriver($mock)->create();
```

## Fluent Expectations (Recommended)

Use `on()`/`expect()` to define matchers, then a reply. Expectations are checked in the order they're defined.

```php
$mock->on()
    ->post('https://api.openai.com/v1/chat/completions')
    ->withJsonSubset(['model' => 'gpt-4o-mini'])
    ->replyJson(['id' => 'cmpl_1', 'choices' => [[
        'message' => ['content' => 'Hello!']
    ]]]);
```

Common matchers:
- Method: `get()`, `post()`, `put()`, `patch()`, `delete()`, or `method('POST')`
- URL: `url('https://...')`, `urlStartsWith('https://api...')`, `urlMatches('/\/v1\/chat\/completions$/')`, `path('/v1/chat/completions')`
- Headers: `header('authorization', 'Bearer X')`, `headers(['content-type' => fn($v) => str_contains($v, 'json')])`
- Body:
  - `bodyEquals('{"a":1}')`, `bodyContains('"stream":true')`, `bodyMatchesRegex('/"temperature":\s*0\.7/')`
  - `withJsonSubset(['stream' => true, 'messages' => [[ 'role' => 'user' ]]])`
- Streaming flag: `withStream(true|false|null)`
- Times: `times(1)` to limit usage

Replies:
- `reply($responseOrCallable)` (callable receives `HttpRequest`)
- `replyJson($data, $status=200, $headers=[])`
- `replyText($text, $status=200, $headers=[])`
- `replyStreamChunks($chunks, $status=200, $headers=[])`
- `replySSEFromJson($payloads, $done=true, $status=200, $headers=[])`

Example (SSE streaming for LLM deltas):

```php
$mock->on()
    ->post('https://api.openai.com/v1/chat/completions')
    ->withStream(true)
    ->withJsonSubset(['stream' => true])
    ->replySSEFromJson([
        ['choices' => [['delta' => ['content' => 'Hel']]]],
        ['choices' => [['delta' => ['content' => 'lo']]]],
    ]);
```

## Legacy addResponse (Still Supported)

```php
use Cognesy\Http\Drivers\Mock\MockHttpResponseFactory;

$mock->addResponse(
    MockHttpResponseFactory::json(['ok' => true]),
    url: 'https://example.com',
    method: 'GET',
    body: null
);
```

## MockHttpResponseFactory Helpers

- `MockHttpResponseFactory::json($data, $status=200, $headers=[])`
- `MockHttpResponseFactory::success($status=200, $headers=[], $body='')`
- `MockHttpResponseFactory::error($status=500, $headers=[], $body='')`
- `MockHttpResponseFactory::streaming($status=200, $headers=[], array $chunks)`
- `MockHttpResponseFactory::sse(array $payloads, bool $addDone=true, $status=200, $headers=[])`

SSE encodes each payload as `"data: {json}\n\n"` and appends `"data: [DONE]\n\n"` if `$addDone` is true.

## Inspecting Requests

```php
$last = $mock->getLastRequest();
$all  = $mock->getReceivedRequests();
$mock->reset();           // clear received requests
$mock->clearResponses();  // clear both expectations and legacy responses
```

## Pool Requests (Note)

The `MockHttpDriver` does **not** implement pool functionality (`CanHandleRequestPool`). Pool-based tests should either:
- Mock individual sequential requests (the pool will call `handle()` multiple times)
- Use a different driver for integration tests that require actual concurrent execution
- Test pool logic separately from HTTP mocking concerns

If you need to test code that uses `HttpClient::pool()` or `HttpClient::withPool()`, the mock driver will handle each request in the pool individually through the standard `handle()` method. The mock simply needs expectations for each request in sequence.

Example with sequential pool requests:

```php
use Cognesy\Http\Collections\HttpRequestList;

$mock->on()->post('https://api.example.com/batch/1')->replyJson(['result' => 1]);
$mock->on()->post('https://api.example.com/batch/2')->replyJson(['result' => 2]);
$mock->on()->post('https://api.example.com/batch/3')->replyJson(['result' => 3]);

$requests = HttpRequestList::of(
    new HttpRequest('https://api.example.com/batch/1', 'POST'),
    new HttpRequest('https://api.example.com/batch/2', 'POST'),
    new HttpRequest('https://api.example.com/batch/3', 'POST'),
);

// This will call handle() for each request - mock doesn't execute concurrently
$results = $http->pool($requests);
```

## Using With Polyglot (LLMs)

Inject the mock-backed client into providers/facades to test without network.

Inference (non-streaming):

```php
use Cognesy\Polyglot\Inference\Inference;

$http = (new HttpClientBuilder())
    ->withMock(function ($mock) {
        $mock->on()
            ->post('https://api.openai.com/v1/chat/completions')
            ->withJsonSubset(['model' => 'gpt-4o-mini'])
            ->replyJson([
                'choices' => [[ 'message' => ['content' => 'Hi there!'] ]],
                'usage' => ['prompt_tokens' => 3, 'completion_tokens' => 2],
            ]);
    })
    ->create();

$content = (new Inference())
    ->withHttpClient($http)
    ->using('openai')
    ->withModel('gpt-4o-mini')
    ->withMessages('Hello')
    ->get();

// assertSame('Hi there!', $content);
```

Inference (streaming):

```php
$http = (new HttpClientBuilder())
    ->withMock(function ($mock) {
        $mock->on()
            ->post('https://api.openai.com/v1/chat/completions')
            ->withStream(true)
            ->withJsonSubset(['stream' => true])
            ->replySSEFromJson([
                ['choices' => [['delta' => ['content' => 'Foo']]]],
                ['choices' => [['delta' => ['content' => 'Bar']]]],
            ]);
    })
    ->create();

$stream = (new Inference())
    ->withHttpClient($http)
    ->using('openai')
    ->withModel('gpt-4o-mini')
    ->withMessages('Say hi')
    ->withStreaming(true)
    ->stream();

$parts = iterator_to_array($stream->responses());
// assert that parts assemble into the final expected content
```

Embeddings:

```php
use Cognesy\Polyglot\Embeddings\Embeddings;

$http = (new HttpClientBuilder())
    ->withMock(function ($mock) {
        $mock->on()
            ->post('https://api.openai.com/v1/embeddings')
            ->withJsonSubset(['model' => 'text-embedding-3-small'])
            ->replyJson([
                'data' => [[ 'embedding' => [0.1, 0.2, 0.3] ]],
                'usage' => ['prompt_tokens' => 3],
            ]);
    })
    ->create();

$vectors = (new Embeddings())
    ->withHttpClient($http)
    ->using('openai')
    ->withModel('text-embedding-3-small')
    ->withInputs(['hello'])
    ->vectors();
```

## Typical LLM Test Scenarios

- Basic chat completion: verify content, usage, finish reason
- Streaming deltas: ensure parser yields expected partials and assembles correctly
- Tool calls: match request `tools/tool_choice`, return tool call payloads in response
- JSON mode: assert `response_format` in request and JSON body fields in response
- Error paths: reply with `error(400, ...)` or `error(429, ...)` and assert exception + event dispatch
- Headers/auth: assert `authorization` header and API version; test missing/invalid cases
- Max tokens/temperature/options: `withJsonSubset([...])` in request; verify adapter mapping
- Multiple calls/sequencing: chain `.times(n)` or define ordered expectations

## Dynamic Responses By Request

```php
$mock->on()
    ->post('https://example.dev/llm')
    ->reply(function ($request) {
        $body = json_decode($request->body()->toString(), true) ?: [];
        $text = $body['messages'][0]['content'] ?? 'default';
        return MockHttpResponseFactory::json(['choices' => [[
            'message' => ['content' => strtoupper($text)],
        ]]]);
    });
```

## Matching Tips

- Prefer `withJsonSubset()` to keep tests resilient to extra fields/ordering differences
- Use `withStream(true)` for streaming tests to ensure the path exercises streaming code
- Combine `urlStartsWith()` and `path()` to avoid brittle full-URL matches
- Use `header('authorization', fn($v) => str_starts_with($v, 'Bearer '))` to avoid leaking real keys in tests

## Troubleshooting

- No match found: the driver throws `InvalidArgumentException("No mock match for ...")`
  - Log/inspect `$mock->getReceivedRequests()` to see what was actually sent
  - Loosen matchers using `withJsonSubset`, `urlStartsWith`, header predicates
- Streaming not triggering: ensure your code sets request `options['stream'] = true` (Polyglot does this in `PendingHttpResponse->stream()`)
- Multiple matches: expectations are evaluated in definition order; use `times()` to consume only N matches
- Pool requests not working: Remember mock driver doesn't implement concurrent pools - it processes each request sequentially via `handle()`

## API Changes (Latest)

**Breaking Changes from Previous Versions:**
- Pool methods now accept/return typed collections (`HttpRequestList`/`HttpResponseList`) instead of raw arrays
- `MockHttpResponse` renamed to `MockHttpResponseFactory` for clarity
- Pool functionality is not implemented in mock driver - sequential handling only

**Migration Example:**
```php
// Old (pre-collections):
$results = $http->pool([$request1, $request2]);
$first = $results[0];

// New (with collections):
use Cognesy\Http\Collections\HttpRequestList;

$requests = HttpRequestList::of($request1, $request2);
$results = $http->pool($requests);
$first = $results->all()[0];
// or iterate:
foreach ($results as $result) {
    // $result is a Result object
}
```

