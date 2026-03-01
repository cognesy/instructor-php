# HTTP Client Package

Framework-agnostic HTTP transport layer used by Instructor for sync, streaming, and pooled requests.

## Example

```php
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\HttpClient;

$client = HttpClient::default();

$request = new HttpRequest(
    url: 'https://api.example.com/health',
    method: 'GET',
    headers: ['Accept' => 'application/json'],
    body: '',
    options: [],
);

$response = $client->withRequest($request)->get();
echo $response->statusCode();
```

## Documentation

For usage details, read the package docs:

- `packages/http-client/docs/1-overview.md`
- `packages/http-client/docs/2-getting-started.md`
- `packages/http-client/docs/_meta.yaml` (navigation order)

2.0 API scope notes:
- `packages/http-client/V2_API_SURFACE.md`
- `packages/http-client/V2_CORE_SCOPE.md`
