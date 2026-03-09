# HTTP Pool Cheat Sheet

## Start Here

```php
use Cognesy\HttpPool\Config\HttpPoolConfig;
use Cognesy\HttpPool\HttpPool;

$pool = HttpPool::fromConfig(new HttpPoolConfig(driver: 'symfony'));
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

## Notes

- requests and responses stay in `packages/http-client`
- `pool()` executes immediately
- `withRequests(...)->all()` defers execution
