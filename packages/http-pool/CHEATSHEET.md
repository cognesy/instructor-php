# HTTP Pool Cheat Sheet

## Start Here

```php
$pool = HttpPool::fromConfig(new HttpClientConfig(driver: 'symfony'));
$responses = $pool->pool($requests, maxConcurrent: 4);
```

## Main Types

- `HttpPool`
- `PendingHttpPool`
- `HttpPoolBuilder`
- `HttpPoolRegistry`

## Built In Drivers

- `curl`
- `exthttp`
- `guzzle`
- `symfony`
- `laravel`

## Notes

- requests and responses stay in `packages/http-client`
- `pool()` executes immediately
- `withRequests(...)->all()` defers execution
