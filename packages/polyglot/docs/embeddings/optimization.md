## Optimization

### Batch processing

```php
<?php
use Cognesy\Polyglot\Embeddings\Embeddings;

$embeddings = Embeddings::using('openai');
$allDocuments = [/* large array */];

$batchSize = 25;
$vectors = [];

for ($i = 0; $i < count($allDocuments); $i += $batchSize) {
    $batch = array_slice($allDocuments, $i, $batchSize);
    $response = $embeddings->withInputs($batch)->get();
    $vectors = array_merge($vectors, $response->toValuesArray());
}
```

### Retry policy

Set retries explicitly with `withRetryPolicy(...)`.

```php
<?php
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsRetryPolicy;
use Cognesy\Polyglot\Embeddings\Embeddings;

$retryPolicy = new EmbeddingsRetryPolicy(
    maxAttempts: 3,
    baseDelayMs: 200,
    maxDelayMs: 4000,
    retryOnStatus: [429, 500, 502, 503, 504],
);

$response = Embeddings::using('openai')
    ->withInputs(['doc one', 'doc two'])
    ->withRetryPolicy($retryPolicy)
    ->get();
```

### Caching embeddings in-memory

```php
<?php
use Cognesy\Polyglot\Embeddings\Embeddings;

final class CachedEmbeddings {
    public function __construct(
        private Embeddings $embeddings = new Embeddings(),
        private array $cache = [],
    ) {}

    public function create(string $input): array {
        if (array_key_exists($input, $this->cache)) {
            return $this->cache[$input];
        }

        $vector = $this->embeddings
            ->withInputs([$input])
            ->get()
            ->first()?->values() ?? [];

        $this->cache[$input] = $vector;
        return $vector;
    }
}

$cachedEmbeddings = new CachedEmbeddings(Embeddings::using('openai'));
$vector1 = $cachedEmbeddings->create('This is a test');
$vector2 = $cachedEmbeddings->create('This is a test');
```
