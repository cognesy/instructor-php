<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Retrieval;

use Cognesy\Agents\Capability\Retrieval\Contracts\CanReadRetrievalEvidence;
use Cognesy\Agents\Tool\Tools\SimpleTool;
use Cognesy\Polyglot\Inference\Data\ToolDefinition;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;
use InvalidArgumentException;
use Override;

final class RetrievalReadTool extends SimpleTool
{
    public const TOOL_NAME = 'retrieval_read';

    public function __construct(
        private readonly CanReadRetrievalEvidence $reader,
        private readonly RetrievalToolPolicy $policy,
    ) {
        parent::__construct(new RetrievalReadToolDescriptor());
    }

    #[Override]
    public function __invoke(mixed ...$args): string {
        $reference = $this->arg($args, 'reference', 0, '');
        if (!is_string($reference) || trim($reference) === '') {
            throw new InvalidArgumentException("'reference' must be a non-empty string");
        }
        if (strlen($reference) > $this->policy->maxQueryBytes) {
            throw new InvalidArgumentException("'reference' exceeds the configured byte limit");
        }
        $content = $this->reader->read($reference, $this->policy->maxReadBytes);
        if ($content === null) {
            return 'Evidence is unavailable or unauthorized.';
        }
        return self::truncateUtf8($content, $this->policy->maxReadBytes);
    }

    #[Override]
    public function toToolSchema(): ToolDefinition {
        return ToolDefinition::fromArray(ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::string('reference', 'Authorized evidence reference.'),
                ])
                ->withRequiredProperties(['reference']),
        )->toArray());
    }

    private static function truncateUtf8(string $content, int $maxBytes): string {
        $content = substr($content, 0, $maxBytes);
        while ($content !== '' && preg_match('//u', $content) !== 1) {
            $content = substr($content, 0, -1);
        }
        return $content;
    }
}
