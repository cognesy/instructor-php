<?php declare(strict_types=1);

namespace Cognesy\Utils\Markdown;

use Symfony\Component\Yaml\Yaml;

final class FrontMatter
{
    private function __construct(
        private array $data,
        private string $document,
        private bool $hasFrontMatter,
        private ?string $error = null,
    ) {}

    public static function parse(string $text): self {
        $text = str_replace("\r\n", "\n", $text);
        $pattern = '/^---\s*\n(.*?)\n---\s*\n?(.*)$/s';

        if (!preg_match($pattern, $text, $matches)) {
            return new self([], $text, false);
        }

        $frontMatter = trim($matches[1]);
        $document = $matches[2];

        if ($frontMatter === '') {
            return new self([], $document, true);
        }

        try {
            $data = Yaml::parse($frontMatter, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        } catch (\Throwable $e) {
            return new self([], $document, true, $e->getMessage());
        }

        if (!is_array($data)) {
            return new self([], $document, true, 'Front matter must be a YAML map.');
        }

        if (array_is_list($data)) {
            return new self([], $document, true, 'Front matter must be a YAML map.');
        }

        return new self($data, $document, true);
    }

    public function data(): array {
        return $this->data;
    }

    public function document(): string {
        return $this->document;
    }

    public function hasFrontMatter(): bool {
        return $this->hasFrontMatter;
    }

    public function error(): ?string {
        return $this->error;
    }
}
