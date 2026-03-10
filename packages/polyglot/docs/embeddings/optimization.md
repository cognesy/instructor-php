---
title: Optimization
description: Batch processing, retry policies, caching strategies, and best practices for production embeddings.
---

Embeddings requests are typically fast and inexpensive, but at scale the details matter. This page covers the key patterns for keeping your embeddings pipeline efficient and reliable.

## Batch Inputs

The single most impactful optimization is batching. Instead of making one request per document, send multiple texts in a single call. This reduces HTTP overhead and is often cheaper per token:

```php
<?php

use Cognesy\Polyglot\Embeddings\Embeddings;

$response = Embeddings::using('openai')
    ->withInputs([
        'Document one',
        'Document two',
        'Document three',
    ])
    ->get();

$vectors = $response->toValuesArray();
```

Each provider has a maximum number of inputs per request (configured as `maxInputs` in the preset). For OpenAI this defaults to 2048; for Cohere it is 96. When processing large datasets, chunk your documents to stay within these limits.

### Processing Large Datasets

When you have more documents than a single batch can handle, process them in chunks:

```php
<?php

use Cognesy\Polyglot\Embeddings\Embeddings;

$embeddings = Embeddings::using('openai');
$allDocuments = [/* hundreds or thousands of documents */];

$batchSize = 25; // Stay well within provider limits
$vectors = [];

for ($i = 0; $i < count($allDocuments); $i += $batchSize) {
    $batch = array_slice($allDocuments, $i, $batchSize);

    try {
        $response = $embeddings->withInputs($batch)->get();
        $vectors = array_merge($vectors, $response->toValuesArray());

        $batchNum = (int) floor($i / $batchSize) + 1;
        $totalBatches = (int) ceil(count($allDocuments) / $batchSize);
        echo "Processed batch {$batchNum} of {$totalBatches}\n";
    } catch (\Exception $e) {
        echo "Error processing batch: " . $e->getMessage() . "\n";
    }

    // Small delay to avoid hitting rate limits
    usleep(100_000); // 100ms
}

echo "Processed " . count($vectors) . " embeddings in total.\n";
```

## Retry Policies

Network failures and rate limits are inevitable in production. Polyglot provides an `EmbeddingsRetryPolicy` that implements exponential backoff with configurable jitter:

```php
<?php

use Cognesy\Polyglot\Embeddings\Config\EmbeddingsRetryPolicy;
use Cognesy\Polyglot\Embeddings\Embeddings;

$response = Embeddings::using('openai')
    ->withInputs(['Document one'])
    ->withRetryPolicy(new EmbeddingsRetryPolicy(
        maxAttempts: 3,
        baseDelayMs: 250,
        maxDelayMs: 8000,
        jitter: 'full',
        retryOnStatus: [408, 429, 500, 502, 503, 504],
    ))
    ->get();
```

### Retry Policy Parameters

| Parameter | Default | Description |
|---|---|---|
| `maxAttempts` | `1` | Total number of attempts (1 = no retries) |
| `baseDelayMs` | `250` | Base delay in milliseconds before the first retry |
| `maxDelayMs` | `8000` | Maximum delay cap in milliseconds |
| `jitter` | `'full'` | Jitter strategy: `'none'`, `'full'`, or `'equal'` |
| `retryOnStatus` | `[408, 429, 500, 502, 503, 504]` | HTTP status codes that trigger a retry |
| `retryOnExceptions` | `[TimeoutException, NetworkException]` | Exception classes that trigger a retry |

The delay for each attempt is calculated as `baseDelayMs * 2^(attempt-1)`, capped at `maxDelayMs`, then jitter is applied:

- **`none`** -- Exact calculated delay, no randomization.
- **`full`** -- Random value between 0 and the calculated delay. Best for reducing thundering herd.
- **`equal`** -- Half the calculated delay plus a random value up to half. A middle ground.

> **Important:** Set `maxAttempts` to at least `3` in production to handle transient failures gracefully. The default of `1` means no retries.

## Caching Embeddings

Embedding the same text repeatedly is wasteful. For applications that frequently re-embed identical strings (such as search queries or template documents), a caching layer pays for itself quickly:

```php
<?php

use Cognesy\Polyglot\Embeddings\Embeddings;

class CachedEmbeddings
{
    private Embeddings $embeddings;
    /** @var array<string, float[]> */
    private array $cache = [];

    public function __construct(?Embeddings $embeddings = null)
    {
        $this->embeddings = $embeddings ?? Embeddings::using('openai');
    }

    /**
     * Get the embedding for a single text, using cache when available.
     *
     * @return float[]
     */
    public function embed(string $text, array $options = []): array
    {
        $key = $this->cacheKey($text, $options);

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $vector = $this->embeddings
            ->withInputs($text)
            ->withOptions($options)
            ->first()
            ->values();

        $this->cache[$key] = $vector;

        return $vector;
    }

    /**
     * Embed multiple texts, fetching only uncached ones from the API.
     *
     * @param string[] $texts
     * @return float[][]
     */
    public function embedMany(array $texts, array $options = []): array
    {
        $results = [];
        $uncachedTexts = [];
        $uncachedIndices = [];

        foreach ($texts as $i => $text) {
            $key = $this->cacheKey($text, $options);
            if (isset($this->cache[$key])) {
                $results[$i] = $this->cache[$key];
            } else {
                $uncachedTexts[] = $text;
                $uncachedIndices[] = $i;
            }
        }

        if ($uncachedTexts !== []) {
            $response = $this->embeddings
                ->withInputs($uncachedTexts)
                ->withOptions($options)
                ->get();

            foreach ($response->toValuesArray() as $j => $vector) {
                $i = $uncachedIndices[$j];
                $results[$i] = $vector;
                $this->cache[$this->cacheKey($texts[$i], $options)] = $vector;
            }
        }

        ksort($results);
        return $results;
    }

    private function cacheKey(string $text, array $options): string
    {
        return md5($text . serialize($options));
    }
}
```

Usage:

```php
<?php

$cached = new CachedEmbeddings(Embeddings::using('openai'));

// First call hits the API
$vector = $cached->embed('What is machine learning?');

// Second call returns from cache instantly
$vector = $cached->embed('What is machine learning?');

// Batch with partial cache hits
$vectors = $cached->embedMany([
    'What is machine learning?',  // cached
    'How do neural networks work?', // API call
]);
```

> **Tip:** For persistent caching across requests, replace the in-memory array with Redis, Memcached, or a database-backed store.

## Choosing the Right Model

Model selection has a direct impact on both cost and quality. Here are the key trade-offs:

| Factor | Smaller Models | Larger Models |
|---|---|---|
| **Dimensions** | Fewer (e.g., 256-1536) | More (e.g., 3072) |
| **Speed** | Faster response times | Slower response times |
| **Cost** | Lower per-token cost | Higher per-token cost |
| **Quality** | Good for general use | Better for nuanced similarity |
| **Storage** | Less memory per vector | More memory per vector |

Some providers (like OpenAI's `text-embedding-3` models) support requesting a specific number of dimensions, letting you trade precision for storage efficiency:

```php
<?php

use Cognesy\Polyglot\Embeddings\Embeddings;

// Full-dimension embedding (3072 dimensions)
$full = Embeddings::using('openai')
    ->withModel('text-embedding-3-large')
    ->withInputs('Sample text')
    ->first();

// Reduced-dimension embedding (256 dimensions, less storage)
$compact = Embeddings::using('openai')
    ->withModel('text-embedding-3-large')
    ->withInputs('Sample text')
    ->withOptions(['dimensions' => 256])
    ->first();

echo "Full: " . count($full->values()) . " dimensions\n";
echo "Compact: " . count($compact->values()) . " dimensions\n";
```

## Best Practices

**Batch whenever possible.** A single request with 100 texts is faster and cheaper than 100 individual requests.

**Set retry policies in production.** Rate limits (HTTP 429) and transient server errors are common. Configure at least 3 attempts with jitter to handle them gracefully.

**Cache aggressively.** Embeddings for the same text and model are deterministic. Cache them to avoid redundant API calls and reduce latency.

**Monitor token usage.** Use the `usage()` method on responses to track consumption and detect unexpected spikes:

```php
<?php

use Cognesy\Polyglot\Embeddings\Embeddings;

$response = Embeddings::using('openai')
    ->withInputs($documents)
    ->get();

$usage = $response->usage();
echo "Tokens used: " . $usage->total() . "\n";
```

**Match dimensions to your storage.** If you are storing millions of vectors, reducing dimensions from 3072 to 256 can cut storage costs by over 90% with only modest quality loss.
