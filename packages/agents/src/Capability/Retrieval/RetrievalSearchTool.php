<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Retrieval;

use Cognesy\Agents\Tool\Tools\SimpleTool;
use Cognesy\Polyglot\Inference\Data\ToolDefinition;
use Cognesy\Retrieval\Context\ContextAssembler;
use Cognesy\Retrieval\Contracts\CanRetrieveText;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;
use InvalidArgumentException;
use Override;

final class RetrievalSearchTool extends SimpleTool
{
    public const TOOL_NAME = 'retrieval_search';

    public function __construct(
        private readonly CanRetrieveText $retriever,
        private readonly ContextAssembler $assembler,
        private readonly RetrievalToolPolicy $policy,
    ) {
        parent::__construct(new RetrievalSearchToolDescriptor());
    }

    #[Override]
    public function __invoke(mixed ...$args): RetrievalSearchResult {
        $query = $this->arg($args, 'query', 0, '');
        $limit = $this->arg($args, 'limit', 1, $this->policy->maxResults);
        if (!is_string($query) || trim($query) === '') {
            throw new InvalidArgumentException("'query' must be a non-empty string");
        }
        if (strlen($query) > $this->policy->maxQueryBytes) {
            throw new InvalidArgumentException("'query' exceeds the configured byte limit");
        }
        if (!is_int($limit) || $limit < 1) {
            throw new InvalidArgumentException("'limit' must be a positive integer");
        }
        $hits = $this->retriever->retrieve($query, min($limit, $this->policy->maxResults))->get();
        $context = $this->assembler->assemble($hits, $this->policy->contextBudget());
        return RetrievalSearchResult::fromContext($context, $this->policy->maxOutputBytes);
    }

    #[Override]
    public function toToolSchema(): ToolDefinition {
        return ToolDefinition::fromArray(ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::string('query', 'Natural-language search query.'),
                    JsonSchema::integer('limit', 'Maximum number of evidence items to return.'),
                ])
                ->withRequiredProperties(['query']),
        )->toArray());
    }
}
