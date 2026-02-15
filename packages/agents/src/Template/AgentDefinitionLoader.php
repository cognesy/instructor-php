<?php declare(strict_types=1);

namespace Cognesy\Agents\Template;

use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Parsers\CanParseAgentDefinition;
use Cognesy\Agents\Template\Parsers\JsonDefinitionParser;
use Cognesy\Agents\Template\Parsers\MarkdownDefinitionParser;
use Cognesy\Agents\Template\Parsers\YamlDefinitionParser;
use InvalidArgumentException;
use RuntimeException;

final class AgentDefinitionLoader
{
    /** @var array<string, CanParseAgentDefinition> */
    private array $parsers;

    /** @param array<string, CanParseAgentDefinition>|null $parsers */
    public function __construct(?array $parsers = null) {
        $yaml = new YamlDefinitionParser();

        $this->parsers = $parsers ?? [
            'md' => new MarkdownDefinitionParser(),
            'json' => new JsonDefinitionParser(),
            'yaml' => $yaml,
            'yml' => $yaml,
        ];
    }

    public function loadFile(string $path): AgentDefinition {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException("Failed to read agent definition file: {$path}");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("Failed to read agent definition file: {$path}");
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $parser = $this->parsers[$extension] ?? null;
        if ($parser === null) {
            throw new InvalidArgumentException(
                "Unsupported file extension '.{$extension}' for agent definition: {$path}"
            );
        }

        return $parser->parse($content);
    }
}
