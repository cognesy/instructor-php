<?php declare(strict_types=1);

use Cognesy\Agents\Builder\AgentConfigurator;
use Cognesy\Agents\Capability\Retrieval\Contracts\CanReadRetrievalEvidence;
use Cognesy\Agents\Capability\Retrieval\RetrievalReadTool;
use Cognesy\Agents\Capability\Retrieval\RetrievalSearchTool;
use Cognesy\Agents\Capability\Retrieval\RetrievalToolPolicy;
use Cognesy\Agents\Capability\Retrieval\UseRetrieval;
use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Retrieval\Context\ContextAssembler;
use Cognesy\Retrieval\Contracts\CanRetrieveText;
use Cognesy\Retrieval\Data\VectorDocument;
use Cognesy\Retrieval\Data\VectorDocuments;
use Cognesy\Retrieval\Drivers\InMemory\InMemoryStore;
use Cognesy\Retrieval\PendingRetrieval;
use Cognesy\Retrieval\Query\VectorQuery;
use Cognesy\Retrieval\Retrieval;
use Cognesy\Utils\Tokenization\Contracts\CanCountTokens;

it('installs bounded search and optional authorized read tools without changing AgentLoop', function () {
    $capability = new UseRetrieval(
        new AgentTestRetriever(),
        new AgentTestEvidenceReader(),
        new ContextAssembler(new AgentTestTokenCounter()),
        new RetrievalToolPolicy(maxContextBytes: 500, maxContextTokens: 500),
    );
    $configured = $capability->configure(AgentConfigurator::base());

    expect($configured->tools()->names())->toContain(
        RetrievalSearchTool::TOOL_NAME,
        RetrievalReadTool::TOOL_NAME,
    );
});

it('returns stateless citation-labelled evidence without vectors or metadata', function () {
    $retriever = new AgentTestRetriever();
    $tool = new RetrievalSearchTool(
        $retriever,
        new ContextAssembler(new AgentTestTokenCounter()),
        new RetrievalToolPolicy(
            maxResults: 2,
            maxContextBytes: 200,
            maxContextTokens: 200,
            maxOutputBytes: 500,
        ),
    );

    $first = $tool(query: 'first', limit: 99);
    $second = $tool(query: 'second', limit: 1);
    $rendered = (string) $first;

    expect($retriever->limits)->toBe([2, 1])
        ->and($first->context)->toContain('first evidence')
        ->and($second->context)->toContain('second evidence')
        ->and($second->context)->not->toContain('first evidence')
        ->and($rendered)->toContain('"citation":"S1"')
        ->and($rendered)->not->toContain('vector')
        ->and($rendered)->not->toContain('secret')
        ->and(strlen($rendered))->toBeLessThanOrEqual(500);
});

it('enforces authorized read and output byte boundaries', function () {
    $reader = new AgentTestEvidenceReader();
    $tool = new RetrievalReadTool($reader, new RetrievalToolPolicy(maxReadBytes: 5));

    expect($tool(reference: 'allowed'))->toBe('abcde')
        ->and($tool(reference: 'denied'))->toBe('Evidence is unavailable or unauthorized.')
        ->and($reader->requestedBytes)->toBe([5, 5]);
});

final class AgentTestRetriever implements CanRetrieveText
{
    /** @var list<int> */
    public array $limits = [];

    public function retrieve(string $query, int $maxResults = 5): PendingRetrieval {
        $this->limits[] = $maxResults;
        $store = new InMemoryStore();
        $store->upsert(VectorDocuments::of(new VectorDocument(
            id: 'doc-' . $query,
            vector: new Vector([1.0]),
            metadata: ['secret' => 'must-not-leak'],
            content: $query . ' evidence',
        )));
        return Retrieval::fromStore($store)
            ->withQuery(new VectorQuery(new Vector([1.0]), maxResults: $maxResults))
            ->pending();
    }
}

final class AgentTestEvidenceReader implements CanReadRetrievalEvidence
{
    /** @var list<int> */
    public array $requestedBytes = [];

    public function read(string $reference, int $maxBytes): ?string {
        $this->requestedBytes[] = $maxBytes;
        return $reference === 'allowed' ? 'abcdefghij' : null;
    }
}

final readonly class AgentTestTokenCounter implements CanCountTokens
{
    public function tokenCount(string $text): int {
        return strlen($text);
    }
}
