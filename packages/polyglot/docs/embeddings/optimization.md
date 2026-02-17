## Optimization

### Batch Processing for Efficiency

When processing many documents, it's more efficient to batch them:

```php
<?php
use Cognesy\Polyglot\Embeddings\Embeddings;

$embeddings = new Embeddings();
$allDocuments = [/* large array of documents */];

// Process in batches of 25 (check provider-specific limits)
$batchSize = 25;
$vectors = [];

for ($i = 0; $i < count($allDocuments); $i += $batchSize) {
    $batch = array_slice($allDocuments, $i, $batchSize);

    try {
        $response = $embeddings->with($batch)->get();
        $batchVectors = $response->toValuesArray();

        // Add to our vectors array
        $vectors = array_merge($vectors, $batchVectors);

        echo "Processed batch " . (floor($i / $batchSize) + 1) . " of " . ceil(count($allDocuments) / $batchSize) . "\n";
    } catch (\Exception $e) {
        echo "Error processing batch: " . $e->getMessage() . "\n";
    }

    // Optional: Add a small delay to avoid hitting rate limits
    usleep(100000); // 100ms
}

echo "Processed " . count($vectors) . " embeddings in total.\n";
```

### Retry Policy Options

You can enable automatic retries with exponential backoff and jitter via options:

```php
<?php
use Cognesy\Polyglot\Embeddings\Embeddings;

$embeddings = new Embeddings();

$response = $embeddings->with(
    input: ['doc one', 'doc two'],
    options: [
        'retryPolicy' => [
            'maxAttempts' => 3,
            'baseDelayMs' => 200,
            'maxDelayMs' => 4000,
            'jitter' => 'full',
            'retryOnStatus' => [429, 500, 502, 503, 504],
        ],
    ],
)->get();
```


### Caching Embeddings

For better performance, you can cache embeddings to avoid regenerating them:

```php
<?php
use Cognesy\Polyglot\Embeddings\Embeddings;

class CachedEmbeddings {
    private $embeddings;
    private $cache = [];

    public function __construct(?Embeddings $embeddings = null) {
        $this->embeddings = $embeddings ?? new Embeddings();
    }

    public function create($input, array $options = []): array {
        if (is_string($input)) {
            // Single string input
            $cacheKey = $this->getCacheKey($input, $options);

            if (isset($this->cache[$cacheKey])) {
                return $this->cache[$cacheKey];
            }

            $response = $this->embeddings->with($input, $options)->get();
            $vector = $response->first()->values();

            $this->cache[$cacheKey] = $vector;
            return $vector;
        } else {
            // Array of strings
            $results = [];
            $uncachedInputs = [];
            $uncachedIndices = [];

            // Check cache for each input
            foreach ($input as $i => $text) {
                $cacheKey = $this->getCacheKey($text, $options);

                if (isset($this->cache[$cacheKey])) {
                    $results[$i] = $this->cache[$cacheKey];
                } else {
                    $uncachedInputs[] = $text;
                    $uncachedIndices[] = $i;
                }
            }

            // Generate embeddings for uncached inputs
            if (!empty($uncachedInputs)) {
                $response = $this->embeddings->with($uncachedInputs, $options)->get();
                $vectors = $response->toValuesArray();

                foreach ($vectors as $j => $vector) {
                    $i = $uncachedIndices[$j];
                    $results[$i] = $vector;

                    // Update cache
                    $cacheKey = $this->getCacheKey($input[$i], $options);
                    $this->cache[$cacheKey] = $vector;
                }
            }

            // Sort by original indices
            ksort($results);
            return $results;
        }
    }

    private function getCacheKey(string $input, array $options): string {
        $model = $options['model'] ?? '';
        return md5($input . serialize($options) . $model);
    }
}

// Usage
$cachedEmbeddings = new CachedEmbeddings((new Embeddings())->using('openai'));

// First call will generate embeddings
$vector1 = $cachedEmbeddings->create("This is a test");
echo "First call completed, generated vector with " . count($vector1) . " dimensions.\n";

// Second call will use the cache
$vector2 = $cachedEmbeddings->create("This is a test");
echo "Second call completed (from cache).\n";

// Compare vectors to verify they're the same
$equal = (serialize($vector1) === serialize($vector2));
echo "Vectors are " . ($equal ? "identical" : "different") . ".\n";
```
