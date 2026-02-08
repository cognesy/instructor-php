<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentTemplate\Definitions;

use Symfony\Component\Yaml\Yaml;

final readonly class MarkdownFrontmatter
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public array $data,
        public string $body,
    ) {}

    public static function parse(string $content): ?self
    {
        $content = str_replace("\r\n", "\n", $content);

        if (!str_starts_with($content, '---')) {
            return null;
        }

        $endPos = strpos($content, "\n---", 3);
        if ($endPos === false) {
            return null;
        }

        $yaml = substr($content, 4, $endPos - 4);

        try {
            $parsed = Yaml::parse(trim($yaml));
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($parsed)) {
            return null;
        }

        $body = trim(substr($content, $endPos + 4));

        return new self(data: $parsed, body: $body);
    }
}
