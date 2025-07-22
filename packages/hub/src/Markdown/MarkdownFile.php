<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Markdown;

use Cognesy\InstructorHub\Markdown\Internal\CodeBlockManipulator;
use Cognesy\InstructorHub\Markdown\Internal\Lexer;
use Cognesy\InstructorHub\Markdown\Internal\Parser;
use Cognesy\InstructorHub\Markdown\Nodes\CodeBlockNode;
use Cognesy\InstructorHub\Markdown\Nodes\ContentNode;
use Cognesy\InstructorHub\Markdown\Nodes\DocumentNode;
use Cognesy\InstructorHub\Markdown\Nodes\HeaderNode;
use Cognesy\InstructorHub\Markdown\Nodes\Node;
use Cognesy\InstructorHub\Markdown\Visitors\ToStringVisitor;
use Iterator;
use Symfony\Component\Yaml\Yaml;
use Webuni\FrontMatter\FrontMatter;

final readonly class MarkdownFile
{
    private function __construct(
        private DocumentNode $document,
        private array $metadata = [],
        private string $path = '',
    ) {}

    public static function fromString(
        string $text,
        string $path = '',
        array $metadata = [],
    ): self {
        return new self(
            document: self::parseMarkdown($text),
            metadata: $metadata ?: FrontMatter::createYaml()->parse($text)->getData(),
            path: $path,
        );
    }

    public function codeBlock(string $id): CodeBlockManipulator {
        return new CodeBlockManipulator($this, $id);
    }

    /** @return Iterator<CodeBlockNode> */
    public function codeBlocks(): Iterator {
        return $this->collectNodes(CodeBlockNode::class);
    }

    /** @return Iterator<string> */
    public function codeQuotes(): Iterator {
        return \iter\values(\iter\flatten(\iter\map(
            fn(ContentNode $node) => $node->codeQuotes(),
            $this->collectNodes(ContentNode::class)
        )));
    }

    /** @return Iterator<HeaderNode> */
    public function headers(): Iterator {
        return $this->collectNodes(HeaderNode::class);
    }

    public function hasCodeblocks(): bool {
        return !$this->codeBlocks()->isEmpty();
    }

    public function root(): DocumentNode {
        return $this->document;
    }

    public function withRoot(DocumentNode $document): self {
        return new self(
            document: $document,
            metadata: $this->metadata,
            path: $this->path,
        );
    }

    public function metadata(string $key, mixed $default = null): mixed {
        return $this->metadata[$key] ?? $default;
    }

    public function hasMetadata(string $key): bool {
        return isset($this->metadata[$key]);
    }

    public function withMetadata(string $key, mixed $value): self {
        return new self(
            document: $this->document,
            metadata: array_merge($this->metadata, [$key => $value]),
        );
    }

    public function path(): string {
        return $this->path;
    }

    public function withPath(string $path): self {
        return new self(
            document: $this->document,
            metadata: $this->metadata,
            path: $path,
        );
    }

    public function toString(): string {
        $content = $this->document->accept(new ToStringVisitor());
        return match(true) {
            !empty($this->metadata) => $this->makeFrontMatter() . $content,
            default => $content,
        };
    }

    // INTERNAL ////////////////////////////////////////////////////////

    private static function parseMarkdown(string $text) : DocumentNode {
        $lexer = new Lexer();
        $parser = new Parser();
        $tokens = $lexer->tokenize($text);
        $nodes = iterator_to_array($parser->parse($tokens));
        return new DocumentNode(...$nodes);
    }

    /**
     * @param class-string<Node> $type
     * @return Iterator<Node>
     */
    private function collectNodes(string $type): Iterator {
        return \iter\values(\iter\filter(
            fn($node) => $node instanceof $type,
            $this->document->children,
        ));
    }

    private function makeFrontMatter() : string {
        return "---\n"
            . Yaml::dump($this->metadata)
            . "---\n\n";
    }
}