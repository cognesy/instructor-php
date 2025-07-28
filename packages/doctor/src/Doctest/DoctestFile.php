<?php declare(strict_types=1);

namespace Cognesy\Doctor\Doctest;

use Cognesy\Doctor\Doctest\Internal\DoctestLexer;
use Cognesy\Doctor\Doctest\Internal\DoctestParser;
use Cognesy\Doctor\Doctest\Internal\MarkdownInfo;
use Cognesy\Doctor\Doctest\Nodes\DoctestIdNode;
use Cognesy\Doctor\Doctest\Nodes\DoctestNode;
use Cognesy\Doctor\Doctest\Nodes\DoctestRegionNode;
use Cognesy\Doctor\Markdown\MarkdownFile;
use Cognesy\Doctor\Markdown\Nodes\CodeBlockNode;
use Cognesy\Utils\ProgrammingLanguage;
use Iterator;

class DoctestFile
{
    public function __construct(
        public ?string $id,
        public string $language,
        public int $linesOfCode,
        public string $code,
        public string $codePath,
        public MarkdownInfo $sourceMarkdown,
    ) {}

    /**
     * Create a Doctest instances from a MarkdownFile.
     *
     * @param MarkdownFile $markdownFile
     * @return Iterator<DoctestFile>
     */
    public static function fromMarkdown(MarkdownFile $markdownFile): Iterator {
        $markdownInfo = MarkdownInfo::from($markdownFile);

        foreach ($markdownFile->codeBlocks() as $codeBlock) {
            $doctest = self::make($markdownInfo, $codeBlock);
            if (!self::shouldBeIncluded($doctest)) {
                continue;
            }
            yield $doctest;
        }
    }

    public function toFileContent(?string $region = null): string {
        $content = $this->code;
        // Extract specific region if requested
        if ($region !== null && $this->hasRegions()) {
            $extractedRegion = $this->extractRegion($region);
            if ($extractedRegion !== null) {
                $content = $extractedRegion;
            }
        }
        $template = ProgrammingLanguage::fileTemplate($this->language);
        return sprintf($template, $this->id, $content);
    }

    /**
     * Get all available regions in this doctest's code
     *
     * @return array<string> List of region names
     */
    public function getAvailableRegions(): array {
        $parsedRegions = $this->parseRegions();
        return array_map(fn($region) => $region->name, $parsedRegions);
    }

    /**
     * Check if this doctest has regions
     */
    public function hasRegions(): bool {
        $parsedRegions = $this->parseRegions();
        return !empty($parsedRegions);
    }

    /**
     * Extract specific region content using the new parser
     */
    public function extractRegion(string $regionName): ?string {
        $parsedRegions = $this->parseRegions();
        foreach ($parsedRegions as $region) {
            if ($region->name === $regionName) {
                return $region->content;
            }
        }
        return null;
    }

    /**
     * Get the doctest ID from code using the new parser
     */
    public function getIdFromCode(): ?string {
        $parsedNodes = $this->parseCode();
        foreach ($parsedNodes as $node) {
            if ($node instanceof DoctestIdNode) {
                return $node->id;
            }
        }
        return null;
    }


    // INTERNAL //////////////////////////////////////////////////////////

    /**
     * Create a Doctest instance from a CodeBlockNode.
     *
     * @param MarkdownInfo $markdownInfo
     * @param CodeBlockNode $node
     * @return DoctestFile
     */
    private static function make(MarkdownInfo $markdownInfo, CodeBlockNode $node): self {
        $id = $node->id;
        $language = $node->language;
        $linesOfCode = ProgrammingLanguage::linesOfCode($language, $node->content);

        return new self(
            id: $id,
            language: $language,
            linesOfCode: $linesOfCode,
            code: $node->content,
            codePath: self::makeTargetPath($markdownInfo, $id, $language),
            sourceMarkdown: $markdownInfo,
        );
    }

    private static function shouldBeIncluded(DoctestFile $doctest): bool {
        // Exclude doctests with no ID
        if (empty($doctest->id)) {
            return false;
        }
        // Exclude doctests with no code
        if ($doctest->linesOfCode === 0 || $doctest->linesOfCode < $doctest->sourceMarkdown->minLines) {
            return false;
        }
        // Exclude doctests with no language or not included language
        if ($doctest->language === '' || !in_array($doctest->language, $doctest->sourceMarkdown->includedTypes)) {
            return false;
        }
        return true;
    }

    private static function makeTargetPath(MarkdownInfo $markdownInfo, mixed $id, string $language): string {
        // Keep codePath as relative path for backward compatibility
        // Actual resolution happens when the path is used
        return $markdownInfo->caseDir
            . '/'
            . $markdownInfo->casePrefix
            . $id
            . '.'
            . ProgrammingLanguage::fileExtension($language);
    }

    /**
     * Get the effective case directory for a markdown file (with defaults applied)
     */
    public static function getEffectiveCaseDir(MarkdownFile $markdown): string {
        $markdownInfo = MarkdownInfo::from($markdown);
        return $markdownInfo->caseDir;
    }

    /**
     * Get the effective case prefix for a markdown file (with defaults applied)
     */
    public static function getEffectiveCasePrefix(MarkdownFile $markdown): string {
        $markdownInfo = MarkdownInfo::from($markdown);
        return $markdownInfo->casePrefix;
    }

    /**
     * Parse code using the new DoctestParser and return all nodes
     *
     * @return array<DoctestNode>
     */
    private function parseCode(): array {
        $lexer = new DoctestLexer($this->language);
        $parser = new DoctestParser();

        $tokens = $lexer->tokenize($this->code);
        return iterator_to_array($parser->parse($tokens));
    }

    /**
     * Parse code and extract only region nodes
     *
     * @return array<DoctestRegionNode>
     */
    private function parseRegions(): array {
        $nodes = $this->parseCode();
        return array_filter($nodes, fn($node) => $node instanceof DoctestRegionNode);
    }
}