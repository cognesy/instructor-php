<?php declare(strict_types=1);

namespace Cognesy\Agents\Template;

use Cognesy\Agents\Template\Data\AgentDefinition;
use JsonException;
use Symfony\Component\Yaml\Yaml;

final readonly class AgentDefinitionSerializer
{
    public function toMarkdown(AgentDefinition $definition): string {
        $data = $this->compact($definition->canonicalArray());
        unset($data['systemPrompt']);
        $frontmatter = Yaml::dump($data, 8, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        return "---\n{$frontmatter}---\n{$definition->canonicalArray()['systemPrompt']}\n";
    }

    public function toYaml(AgentDefinition $definition): string {
        return Yaml::dump(
            $this->compact($definition->canonicalArray()),
            8,
            2,
            Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK,
        );
    }

    /** @throws JsonException */
    public function toJson(AgentDefinition $definition, bool $pretty = true): string {
        $flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }
        return json_encode($this->compact($definition->canonicalArray()), $flags) . "\n";
    }

    /** @return array<string, mixed> */
    private function compact(array $canonical): array {
        return array_filter(
            $canonical,
            static fn (mixed $value): bool => $value !== null && $value !== [],
        );
    }
}
