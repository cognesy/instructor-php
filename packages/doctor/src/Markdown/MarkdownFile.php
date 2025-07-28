<?php declare(strict_types=1);

namespace Cognesy\Doctor\Markdown;

use Cognesy\Doctor\Markdown\Enums\MetadataStyle;
use Cognesy\Doctor\Markdown\Internal\CodeBlockManipulator;
use Cognesy\Doctor\Markdown\Internal\Lexer;
use Cognesy\Doctor\Markdown\Internal\Parser;
use Cognesy\Doctor\Markdown\Nodes\CodeBlockNode;
use Cognesy\Doctor\Markdown\Nodes\ContentNode;
use Cognesy\Doctor\Markdown\Nodes\DocumentNode;
use Cognesy\Doctor\Markdown\Nodes\HeaderNode;
use Cognesy\Doctor\Markdown\Nodes\Node;
use Cognesy\Doctor\Markdown\Visitors\ReplaceCodeBlockByCallable;
use Cognesy\Doctor\Markdown\Visitors\ToString;
use Iterator;
use Symfony\Component\Yaml\Yaml;
use Cognesy\Utils\FrontMatter;

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
        $parsedDocument = FrontMatter::parse($text);
        return new self(
            document: self::parseMarkdown($parsedDocument->document()),
            metadata: $metadata ?: $parsedDocument->data(),
            path: $path,
        );
    }

    public function codeBlock(string $id): CodeBlockManipulator {
        return new CodeBlockManipulator($this, $id);
    }

    /** @return Iterator<CodeBlockNode> */
    public function codeBlocks(): Iterator {
        return $this->collectNodes(CodeBlockNode::class, $this->document->children);
    }

    /** @return Iterator<string> */
    public function codeQuotes(): Iterator {
        return \iter\values(\iter\flatten(\iter\map(
            fn(ContentNode $node) => $node->codeQuotes(),
            $this->collectNodes(ContentNode::class, $this->document->children)
        )));
    }

    /** @return Iterator<HeaderNode> */
    public function headers(): Iterator {
        return $this->collectNodes(HeaderNode::class, $this->document->children);
    }

    public function hasCodeblocks(): bool {
        return iterator_count($this->codeBlocks()) > 0;
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

    public function toString(MetadataStyle $metadataStyle = MetadataStyle::Comments): string {
        $content = $this->document->accept(new ToString($metadataStyle));
        return match(true) {
            !empty($this->metadata) => $this->makeFrontMatter($this->metadata) . $content,
            default => $content,
        };
    }

    public function withReplacedCodeBlocks(callable $replacer) : self {
        return new self(
            document: $this->document->accept(new ReplaceCodeBlockByCallable($replacer)),
            metadata: $this->metadata,
            path: $this->path,
        );
    }

    public function withInlinedCodeBlocks(): self {
        return $this->tryInlineCodeblocks($this, dirname($this->path()));
    }

    // INTERNAL ////////////////////////////////////////////////////////

    private function tryInlineCodeblocks(MarkdownFile $markdownFile, string $markdownDir): ?MarkdownFile {
        $madeReplacements = false;
        $newMarkdown = $markdownFile->withReplacedCodeBlocks(function (CodeBlockNode $codeblock) use ($markdownDir, &$madeReplacements) {
            $includePath = $codeblock->metadata('include');
            if (empty($includePath)) {
                return $codeblock;
            }
            $includeDir = trim($includePath, '\'"');
            // Resolve path relative to markdown file
            $path = $markdownDir . '/' . ltrim($includeDir, './');
            if (!file_exists($path)) {
                throw new \Exception("Codeblock include file '$path' does not exist (resolved from markdown: {$markdownDir})");
            }
            $content = file_get_contents($path);
            if ($content === false) {
                throw new \Exception("Failed to read codeblock include file '$path'");
            }
            $madeReplacements = true;
            return $codeblock->withContent($content);
        });
        return $madeReplacements ? $newMarkdown : $markdownFile;
    }

    private static function parseMarkdown(string $text) : DocumentNode {
        $lexer = new Lexer();
        $parser = new Parser();
        $tokens = $lexer->tokenize($text);
        return DocumentNode::fromIterator($parser->parse($tokens));
    }

    /**
     * @param class-string<Node> $type
     * @param Node[] $nodes
     * @return Iterator<Node>
     */
    private function collectNodes(string $type, array $nodes): Iterator {
        return \iter\values(\iter\filter(
            fn($node) => $node instanceof $type,
            $nodes,
        ));
    }

    private function makeFrontMatter(array $metadata) : string {
        return "---\n"
            . Yaml::dump($metadata)
            . "---\n"
            . "\n";
    }
}