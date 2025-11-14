# CurlNew Driver - Clean Architecture

Clean, performant curl-based HTTP driver with zero unnecessary data copies.

## Architecture

### Design Principles

1. **Single Responsibility**: Each class has one clear purpose
2. **Zero Copies**: Data flows directly from curl → consumer
3. **Lazy Evaluation**: Headers/status parsed only when accessed
4. **Resource Safety**: Automatic cleanup via destructors
5. **No Code Duplication**: Shared configuration and parsing logic

### Components

```
CurlNewDriver (orchestrator)
├── CurlFactory (configuration)
├── HeaderParser (parsing)
├── CurlHandle (resource management)
├── Response Adapters:
│   ├── SyncCurlResponse (blocking, internal curl_exec)
│   ├── PoolCurlResponse (pooled, external body via curl_multi_getcontent)
│   └── StreamingCurlResponse (progressive, zero-copy)
└── CurlNewPool (concurrent requests)
```

### Class Responsibilities

#### CurlHandle
- **Purpose**: Resource lifecycle management
- **Owns**: Native `CurlHandle` resource
- **Provides**: Clean API over curl operations
- **Cleanup**: Automatic via destructor

```php
$handle = CurlHandle::create($url, $method);
$handle->setOption(CURLOPT_TIMEOUT, 30);
$statusCode = $handle->statusCode();
// Handle automatically closed when $handle goes out of scope
```

#### CurlFactory
- **Purpose**: Handle configuration
- **Stateless**: No instance state
- **Reusable**: Single factory for all requests
- **Modular**: One method per concern (headers, timeouts, SSL, etc.)

```php
$factory = new CurlFactory($config);
$handle = $factory->createHandle($request);
// Handle fully configured and ready to execute
```

#### HeaderParser
- **Purpose**: HTTP header parsing
- **Stateful**: Accumulates headers during curl callbacks
- **Single Use**: New instance per request
- **Performance**: Parse once, cache results

```php
$parser = new HeaderParser();
curl_setopt($handle, CURLOPT_HEADERFUNCTION, fn($_, $line) => $parser->parse($line));
// ... after curl_exec ...
$headers = $parser->headers();
$statusCode = $parser->statusCode();
```

#### SyncCurlResponse
- **Purpose**: Adapter for blocking requests
- **Memory**: Executes curl_exec internally, stores body once
- **Lifecycle**: Executes on construction, no external body passing
- **Stream**: Simulates streaming by chunking stored body

```php
$response = new SyncCurlResponse($handle, $headerParser, $events);
// curl_exec called internally in constructor
echo $response->body(); // Returns internally captured body
foreach ($response->stream() as $chunk) { } // Chunks from stored body
```

#### PoolCurlResponse
- **Purpose**: Adapter for pooled requests via curl_multi
- **Memory**: Body passed as parameter (required by curl_multi architecture)
- **Why separate**: curl_exec doesn't work after curl_multi_exec; must use curl_multi_getcontent
- **Stream**: Simulates streaming by chunking stored body

```php
// Body must be retrieved before creating response
$body = curl_multi_getcontent($handle->native());
$response = new PoolCurlResponse($handle, $body, $headerParser, $events);
echo $response->body();
```

#### StreamingCurlResponse
- **Purpose**: Adapter for progressive consumption
- **Memory**: Zero copies - chunks flow directly from curl → consumer
- **Lifecycle**: Handles stay alive until stream consumed
- **Progressive**: Headers/status available before body complete

```php
$response = new StreamingCurlResponse($handle, $multi, $queue, $headerParser, $events);
foreach ($response->stream() as $chunk) {
    // Chunk comes directly from curl, no intermediate copies
}
// Handles cleaned up when stream exhausted or response destroyed
```

#### CurlNewPool
- **Purpose**: Concurrent request execution with curl_multi
- **Zero Duplication**: Uses CurlFactory for configuration
- **Resource Management**: Leverages CurlHandle lifecycle
- **Rolling Window**: Maintains max concurrency with queued requests
- **Result Wrapping**: Returns array of Result<HttpResponse, Throwable>

```php
$pool = new CurlNewPool($config, $events);

$requests = [
    new HttpRequest('https://api.example.com/users/1', 'GET', [], [], []),
    new HttpRequest('https://api.example.com/users/2', 'GET', [], [], []),
    new HttpRequest('https://api.example.com/users/3', 'GET', [], [], []),
];

// Execute with max 2 concurrent
$results = $pool->pool($requests, maxConcurrent: 2);

foreach ($results as $result) {
    if ($result->isSuccess()) {
        $response = $result->unwrap();
        echo $response->body();
    } else {
        $error = $result->error();
        echo "Error: " . $error->getMessage();
    }
}
```

## Memory Efficiency

### Sync Requests
- Body: 1 copy (unavoidable from `curl_exec`)
- Headers: Parsed once, cached
- Status: Queried from handle on demand

### Streaming Requests
- Body chunks: **Zero copies** (queue → BufferedStream → consumer)
- Headers: Parsed once, cached
- Status: Queried from handle on demand
- Progressive buffering for replay via BufferedStream

## Comparison with Old Driver

| Aspect | Old CurlDriver | CurlNew Driver |
|--------|---------------|----------------|
| Lines of code | ~375 (driver) + ~440 (pool) | ~150 (driver) + ~350 (pool) + ~270 (components) |
| Configuration | Duplicated sync/streaming/pool | Single `CurlFactory` |
| Header parsing | Duplicated | Single `HeaderParser` |
| Response classes | 2 (sync, streaming) | 3 (sync, pool, streaming) - clean separation |
| Body handling | Passed as parameter | Sync: internal curl_exec, Pool: curl_multi_getcontent, Stream: zero-copy |
| Pool implementation | Manual curl_multi management | Clean architecture with CurlFactory |
| Resource management | Manual cleanup | Automatic (destructors) |
| Data copies (streaming) | Potentially multiple | Zero |
| Code duplication | High | None |

## Usage

```php
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Drivers\CurlNew\CurlNewDriver;

$config = new HttpClientConfig(
    connectTimeout: 3,
    requestTimeout: 30,
    streamChunkSize: 256,
);

$driver = new CurlNewDriver($config, $eventDispatcher);

// Sync request
$response = $driver->handle($request);
echo $response->body();

// Streaming request
$response = $driver->handle($streamingRequest);
foreach ($response->stream() as $chunk) {
    echo $chunk;
}

// Concurrent requests with pool
$pool = new CurlNewPool($config, $eventDispatcher);
$requests = [
    new HttpRequest('https://api.example.com/endpoint1', 'GET', [], [], []),
    new HttpRequest('https://api.example.com/endpoint2', 'GET', [], [], []),
    new HttpRequest('https://api.example.com/endpoint3', 'GET', [], [], []),
];

$results = $pool->pool($requests, maxConcurrent: 2);
foreach ($results as $result) {
    if ($result->isSuccess()) {
        echo $result->unwrap()->body();
    }
}
```

## Testing

Run the test script:
```bash
php packages/http-client/src/Drivers/CurlNew/TEST.php
```

## Migration Path

1. Test CurlNew driver thoroughly
2. Update HttpClient to use CurlNew by default
3. Keep old CurlDriver for one release (deprecated)
4. Remove old CurlDriver in next major version

## Future Enhancements

- [ ] Connection pooling (reuse curl handles)
- [ ] HTTP/3 support (when available in curl)
- [ ] Multiplexing (HTTP/2 parallel requests)
- [ ] DNS caching
- [ ] Certificate pinning
