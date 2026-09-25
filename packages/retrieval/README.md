# InstructorPHP Retrieval

Vector storage, document indexing, semantic retrieval, and bounded RAG context
for InstructorPHP. The package follows Symfony AI's composition model: one
replaceable store contract is shared by direct vector persistence, the document
processor/indexer, and retrieval. Instructor's facade and pending execution
model remains the public orchestration surface.

## Installation

```bash
composer require cognesy/instructor-retrieval
```

The package bundles `memory`, `pgvector`, `qdrant`, `typesense`, `meilisearch`,
`weaviate`, and `milvus`. The HTTP-backed drivers use Instructor's shared HTTP
client; Pgvector requires `ext-pdo` and a configured PDO connection. Backend
management is explicit through `CanManageStore`—constructing a store never
creates or drops remote resources.

## Configure bundled drivers

`StoreConfig::metric` selects the package-level metric; backend connection and
schema values belong in `options`. `StoreFactory` resolves the configured name
through the same replaceable registry used by the other InstructorPHP driver
packages.

```php
use Cognesy\Retrieval\Config\StoreConfig;
use Cognesy\Retrieval\Config\StoreProvider;
use Cognesy\Retrieval\Contracts\CanManageStore;
use Cognesy\Retrieval\Creation\StoreFactory;
use Cognesy\Retrieval\Data\DistanceMetric;

$store = StoreFactory::fromProvider(new StoreProvider(new StoreConfig(
    driver: 'typesense',
    metric: DistanceMetric::Cosine,
    options: [
        'endpoint' => 'http://localhost:8108',
        'collection' => 'documents',
        'dimensions' => 1536,
        'api_key' => getenv('TYPESENSE_API_KEY') ?: '',
    ],
)));

if ($store instanceof CanManageStore) {
    $store->setup(); // Explicit provisioning, normally run by deployment code.
}
```

All drivers support bounded `VectorQuery` and reject ranked continuations. Their
additional capabilities are deliberately backend-specific:

<!-- markdownlint-disable MD013 -->

| Driver | Metrics | Fetch | Scan | Search |
| --- | --- | ---: | ---: | --- |
| `memory` | cosine, dot product, Euclidean | yes | yes | exact |
| `pgvector` | cosine, dot product, Euclidean | yes | yes | exact or configured HNSW |
| `qdrant` | cosine, dot product, Euclidean | yes | yes | approximate |
| `typesense` | cosine, dot product | yes | yes | approximate |
| `meilisearch` | cosine | yes | yes | approximate |
| `weaviate` | cosine, dot product, Euclidean | yes | yes | approximate |
| `milvus` | cosine, dot product, Euclidean | yes | no | approximate |

<!-- markdownlint-enable MD013 -->

The required and driver-specific options are:

<!-- markdownlint-disable MD013 -->

| Driver | Required `options` | Optional `options` |
| --- | --- | --- |
| `memory` | none | none |
| `pgvector` | `pdo`, `table`, `dimensions` | `approximate`; `relaxed_ordering` must remain false |
| `qdrant` | `endpoint`, `collection`, `dimensions` | `api_key`, `max_response_bytes` |
| `typesense` | `endpoint`, `collection`, `dimensions` | `api_key`, `max_response_bytes`, `max_scan_page_size` |
| `meilisearch` | `endpoint`, `index`, `dimensions` | `api_key`, `embedder`, `max_response_bytes`, `task_timeout_milliseconds`, `task_poll_milliseconds`, `max_scan_page_size` |
| `weaviate` | `endpoint`, `collection`, `dimensions` | `api_key`, `max_response_bytes`, `max_scan_page_size` |
| `milvus` | `endpoint`, `collection`, `dimensions` | `token`, `database`, `max_response_bytes`, `max_fetch_ids`, `max_content_bytes` |

<!-- markdownlint-enable MD013 -->

Typesense rejects Euclidean configuration. Meilisearch uses a `userProvided`
embedder and supports cosine only; setup and every mutation wait for terminal
task success before returning. Weaviate requires an endpoint with `/v1/graphql`
enabled. Milvus intentionally does not expose `CanScanDocuments`: its REST
offset cap cannot satisfy the package's large-corpus traversal contract.

Weaviate and Milvus expose squared L2 values on the wire; their adapters return
the square root so `SearchScore` consistently represents the package's actual
Euclidean distance. Vectors remain omitted unless requested with `Projection`.

## Store precomputed embeddings

Embedding generation is not required when vectors already exist.

```php
use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Retrieval\Data\VectorDocument;
use Cognesy\Retrieval\Data\VectorDocuments;
use Cognesy\Retrieval\Drivers\InMemory\InMemoryStore;
use Cognesy\Retrieval\Query\VectorQuery;

$store = new InMemoryStore();
$store->upsert(VectorDocuments::of(
    new VectorDocument('a', new Vector([1.0, 0.0]), content: 'Alpha'),
    new VectorDocument('b', new Vector([0.0, 1.0]), content: 'Beta'),
));

$hits = $store->query(new VectorQuery(new Vector([1.0, 0.0]), maxResults: 5));
```

`VectorDocument` is deliberately generic. Text, source/chunk provenance, and
even content itself are optional. Search results omit vectors unless the caller
explicitly requests them with `Projection`.

## Index text and retrieve by meaning

`DocumentIndexer` delegates to `DocumentProcessor`; the processor owns filtering,
transformation, bounded batching, vectorization, persistence, and stale-chunk
cleanup. `Vectorizer` is a thin adapter over Polyglot `Embeddings`.

```php
use Cognesy\Logging\EventLog;
use Cognesy\Polyglot\Embeddings\Embeddings;
use Cognesy\Retrieval\Drivers\InMemory\InMemoryStore;
use Cognesy\Retrieval\Indexing\Data\TextDocument;
use Cognesy\Retrieval\Indexing\DocumentIndexer;
use Cognesy\Retrieval\Indexing\DocumentProcessor;
use Cognesy\Retrieval\Indexing\Transformation\TextSplitter;
use Cognesy\Retrieval\Retrieval;
use Cognesy\Retrieval\SemanticRetriever;
use Cognesy\Retrieval\Vectorization\SemanticQueryVectorizer;
use Cognesy\Retrieval\Vectorization\Vectorizer;

$store = new InMemoryStore();
$vectorizer = new Vectorizer(
    embeddings: Embeddings::using('openai'),
    embeddingSpace: 'openai:text-embedding-3-small:v1',
    model: 'text-embedding-3-small',
);

$indexer = new DocumentIndexer(new DocumentProcessor(
    store: $store,
    vectorizer: $vectorizer,
    events: EventLog::root('retrieval.indexing'),
    transformers: [new TextSplitter(maxCharacters: 1200)],
));
$indexer->index(new TextDocument(
    id: 'claims-policy',
    content: $policyText,
    sourceVersion: '2026-09-18',
));

$retrieval = Retrieval::fromStore(
    store: $store,
    queryPreparer: new SemanticQueryVectorizer($vectorizer),
);
$pending = (new SemanticRetriever(
    retrieval: $retrieval,
    embeddingSpace: 'openai:text-embedding-3-small:v1',
))->retrieve('How are claims escalated?', maxResults: 5);

// No query embedding or store I/O happened before this line.
$hits = $pending->get();
$sameHits = $pending->get(); // memoized; no second execution
```

Use separate document/query `Vectorizer` instances when a provider requires
different task options, while keeping both roles in the same declared embedding
space. Known dimension or embedding-space mismatches fail explicitly.

## Compose RAG without hiding generation

Retrieval never invokes an answering model. `ContextAssembler` selects textual
evidence under exact rendered byte, token, evidence-count, and excerpt limits;
the application then passes that untrusted evidence to `Inference` or
`StructuredOutput`.

```php
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Retrieval\Context\ContextAssembler;
use Cognesy\Retrieval\Context\ContextBudget;

$evidence = (new ContextAssembler())->assemble(
    $hits,
    new ContextBudget(maxBytes: 12_000, maxTokens: 3_000, maxEvidence: 5),
);

$answer = Inference::using('openai')
    ->withMessages(Messages::fromString(<<<PROMPT
Answer only from the untrusted evidence below. Cite sources as [S1], [S2], etc.

Question: How are claims escalated?

Evidence:
{$evidence->text}
PROMPT))
    ->get()
    ->content()
    ->toString();
```

The returned `AssembledContext` includes the exact citation map. Empty or
content-free hits produce an explicit empty context; the application chooses
whether to abstain, ask a follow-up question, or answer without evidence.

## Traverse large corpora

Ranked retrieval always returns one bounded page. Corpus traversal is a separate
scan capability with a forward-only cursor that releases previous pages.

```php
use Cognesy\Retrieval\Cursor\DocumentCursor;
use Cognesy\Retrieval\Data\ScanRequest;

$cursor = new DocumentCursor($store, new ScanRequest(pageSize: 100));
foreach ($cursor as $document) {
    consume($document);
    if (shouldStop()) {
        $cursor->close();
    }
}
```

Do not use ranked search as an export API. Drivers expose truthful capabilities
for ranked continuation, scan, fetch, exact/approximate behavior, and ordering.

## Agents

`packages/agents` depends on this package and provides `UseRetrieval`. It installs
a stateless, bounded `retrieval_search` tool and optionally a `retrieval_read`
tool. Reads require an application-supplied `CanReadRetrievalEvidence`
implementation, which is the authorization boundary for execution, tenant, and
collection scope. The generic `AgentLoop` has no vector-store dependency.

## Multimodal content

The storage contract already accepts vectors for products, images, audio, or any
other modality because a record does not require text. Applications can store
externally generated multimodal embeddings and keep typed asset references in
metadata. The bundled document processor, `SemanticQueryVectorizer`, and
`ContextAssembler` are intentionally text-first in this release because
Polyglot's current embeddings request is string-based. They do not silently
flatten binary/image/audio inputs into text.

First-party multimodal vectorization should be added when Polyglot exposes a
typed multimodal embeddings input. Multimodal RAG will then need a content-part
evidence representation and modality-aware budgets; until then, hydrate an
authorized asset reference in application code and pass it to the existing
multimodal inference APIs.

## Observability and safety

Retrieval and indexing dispatch lifecycle, store-attempt, and progress events
through the shared events/logging stack. `RetrievalTelemetryProjector` maps a
retrieval lifecycle to trace-ready data. Default events contain correlation IDs,
driver/query types, counts, durations, and error classes—not vectors, credentials,
query text, or retrieved content.

All HTTP-driver response bodies are capped before complete JSON decoding.
Pgvector reads rows incrementally and closes PDO cursors explicitly. Store scans
and retrieval tools remain bounded even when the backing collection is large.

## Live backend tests

Integration tests are skipped unless their backend endpoint is configured:

<!-- markdownlint-disable MD013 -->

| Driver | Environment variables |
| --- | --- |
| Typesense | `RETRIEVAL_TYPESENSE_URL`, optional `RETRIEVAL_TYPESENSE_API_KEY` |
| Meilisearch | `RETRIEVAL_MEILISEARCH_URL`, optional `RETRIEVAL_MEILISEARCH_API_KEY` |
| Weaviate | `RETRIEVAL_WEAVIATE_URL`, optional `RETRIEVAL_WEAVIATE_API_KEY` |
| Milvus | `RETRIEVAL_MILVUS_URL`, optional `RETRIEVAL_MILVUS_TOKEN` |

<!-- markdownlint-enable MD013 -->

Each live test owns a randomly named collection or index and exercises explicit
setup, replacement, filtered query, projection, fetch, deletion, clear, and
drop. Scan is additionally exercised where the driver advertises it.
