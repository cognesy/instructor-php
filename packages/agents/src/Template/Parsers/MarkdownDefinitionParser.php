<?php declare(strict_types=1);

namespace Cognesy\Agents\Template\Parsers;

use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Utils\Markdown\FrontMatter;
use InvalidArgumentException;

final readonly class MarkdownDefinitionParser implements CanParseAgentDefinition
{
    #[\Override]
    public function parse(mixed $data): AgentDefinition {
        if (!is_string($data)) {
            throw new InvalidArgumentException('Markdown agent definition must be a string.');
        }

        $frontmatter = FrontMatter::parse($data);
        if (!$frontmatter->hasFrontMatter()) {
            throw new InvalidArgumentException(
                "Invalid markdown format: missing YAML frontmatter (expected ---\\n...\\n---)"
            );
        }
        if ($frontmatter->error() !== null) {
            throw new InvalidArgumentException("Invalid YAML: {$frontmatter->error()}");
        }

        $systemPrompt = trim($frontmatter->document());
        if ($systemPrompt === '') {
            throw new InvalidArgumentException(
                "Invalid markdown format: system prompt (content after frontmatter) cannot be empty"
            );
        }

        $array = $frontmatter->data();
        $array['systemPrompt'] = $systemPrompt;
        return AgentDefinition::fromArray($array);
    }
}
