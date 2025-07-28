<?php declare(strict_types=1);

namespace Cognesy\Utils;

use Symfony\Component\Yaml\Yaml;

final class FrontMatter
{
    private function __construct(
        private array $data,
        private string $document
    ) {}

    public static function parse(string $text): self
    {
        $pattern = '/^---\s*\n(.*?)\n---\s*\n(.*)$/s';
        
        if (!preg_match($pattern, $text, $matches)) {
            return new self([], $text);
        }
        
        $frontMatter = trim($matches[1]);
        $document = $matches[2];
        
        $data = empty($frontMatter) ? [] : Yaml::parse($frontMatter);
        
        return new self($data ?? [], $document);
    }

    public function data(): array
    {
        return $this->data;
    }

    public function document(): string
    {
        return $this->document;
    }
}