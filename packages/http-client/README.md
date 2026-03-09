# HTTP Client Package

Minimal HTTP transport for sync and streaming requests.

## Example

```php
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\HttpClient;

$client = HttpClient::default();

$response = $client->send(new HttpRequest(
    url: 'https://api.example.com/health',
    method: 'GET',
    headers: ['Accept' => 'application/json'],
    body: '',
    options: [],
))->get();

echo $response->statusCode();
```

## Docs

- `packages/http-client/docs/1-overview.md`
- `packages/http-client/docs/2-getting-started.md`
- `packages/http-client/docs/3-making-requests.md`
- `packages/http-client/docs/4-handling-responses.md`
- `packages/http-client/docs/5-streaming-responses.md`
- `packages/http-client/docs/10-middleware.md`

Pooling lives in `packages/http-pool`.
