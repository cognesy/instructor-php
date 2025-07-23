<?php

namespace Cognesy\InstructorHub\Doctest;

use Cognesy\InstructorHub\Markdown\MarkdownFile;
use Cognesy\InstructorHub\Markdown\Nodes\CodeBlockNode;
use Cognesy\Utils\FileExtension;
use Iterator;

class Doctest {
    private function __construct(
        public ?string $id,
        public string $language,
        public int $linesOfCode,
        //
        public string $markdownPath,
        public string $codePath,
        public string $code,
        //
        private string $markdownTitle,
        private string $markdownDescription,
        private string $caseDir,
        private string $casePrefix,
        private int $minLines,
        private array $includedTypes,
    ) {}

    /**
     * Create a Doctest instances from a MarkdownFile.
     *
     * @param MarkdownFile $markdownFile
     * @return Iterator<Doctest>
     */
    public static function fromMarkdown(MarkdownFile $markdownFile) : Iterator {
        foreach ($markdownFile->codeBlocks() as $codeBlock) {
            $doctest = self::make($markdownFile, $codeBlock);
            if (!self::shouldBeIncluded($doctest)) {
                continue;
            }
            yield $doctest;
        }
    }

    public function toFileContent() : string {
        $template = match($this->language) {
            'php' => "<?php\n// @doctest id=%s\n%s\n?>\n",
            'python' => "# @doctest id=%s\n%s\n",
            'ruby' => "# @doctest id=%s\n%s\n",
            'bash' => "# @doctest id=%s\n%s\n",
            'sh' => "# @doctest id=%s\n%s\n",
            'javascript' => "// @doctest id=%s\n%s\n",
            'java' => "// @doctest id=%s\n%s\n",
            'csharp' => "// @doctest id=%s\n%s\n",
            'go' => "// @doctest id=%s\n%s\n",
            'c' => "// @doctest id=%s\n%s\n",
            'cpp' => "// @doctest id=%s\n%s\n",
            'typescript' => "// @doctest id=%s\n%s\n",
            'cs' => "// @doctest id=%s\n%s\n",
            'rust' => "// @doctest id=%s\n%s\n",
            default => "// @doctest id=%s\n%s\n",
        };
        return sprintf($template, $this->id, $this->code);
    }

    // INTERNAL //////////////////////////////////////////////////////////

    /**
     * Create a Doctest instance from a CodeBlockNode.
     *
     * @param MarkdownFile $markdown
     * @param CodeBlockNode $node
     * @return Doctest
     */
    private static function make(MarkdownFile $markdown, CodeBlockNode $node) : self {
        $id = $node->metadata['id'] ?? null;
        $language = $node->language;
        $linesOfCode = count(explode("\n", $node->content));

        return new self(
            id: $id,
            language: $language,
            linesOfCode: $linesOfCode,
            markdownPath: $markdown->path(),
            codePath: self::makeTargetPath($markdown, $id, $language),
            code: $node->content,
            markdownTitle: $markdown->metadata('title', ''),
            markdownDescription: $markdown->metadata('description', ''),
            caseDir: $markdown->metadata('doctest_case_dir', ''),
            casePrefix: $markdown->metadata('doctest_case_prefix', ''),
            minLines: $markdown->metadata('doctest_min_lines', 0),
            includedTypes: $markdown->metadata('doctest_included_types', []),
        );
    }

    private static function shouldBeIncluded(Doctest $doctest) : bool {
        // Exclude doctests with no ID
        if (empty($doctest->id)) {
            return false;
        }
        // Exclude doctests with no code
        if ($doctest->linesOfCode === 0 || $doctest->linesOfCode < $doctest->minLines) {
            return false;
        }
        // Exclude doctests with no language or not included language
        if ($doctest->language === '' || !in_array($doctest->language, $doctest->includedTypes)) {
            return false;
        }
        return true;
    }

    private static function makeTargetPath(MarkdownFile $markdown, mixed $id, string $language) : string {
        return $markdown->metadata('doctest_case_dir', '')
            . '/'
            . $markdown->metadata('doctest_case_prefix', '')
            . $id
            . '.'
            . FileExtension::forLanguage($language);
    }
}