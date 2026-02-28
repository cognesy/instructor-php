<?php declare(strict_types=1);

namespace Cognesy\Doctools\Doctest\Services;

use Cognesy\Doctools\Doctest\Data\ValidationResult;
use Cognesy\Doctools\Doctest\Data\BlockValidation;
use Cognesy\Doctools\Doctest\Internal\MarkdownInfo;
use Cognesy\Doctools\Markdown\MarkdownFile;
use Cognesy\Doctools\Markdown\Nodes\CodeBlockNode;
use Cognesy\Utils\ProgrammingLanguage;
use Symfony\Component\Filesystem\Path;

class ValidationService
{
    public function validateFile(string $filePath): ValidationResult {
        $sourceContent = file_get_contents($filePath);
        if ($sourceContent === false) {
            throw new \RuntimeException("Failed to read file: {$filePath}");
        }
        $markdown = MarkdownFile::fromString($sourceContent, $filePath);
        $markdownDir = dirname($filePath);

        $valid = [];
        $missing = [];
        $validatedCount = 0;

        foreach ($markdown->codeBlocks() as $codeBlock) {
            $expectedPath = $this->extractExpectedPath($codeBlock, $markdown, $markdownDir);

            if ($expectedPath === null) {
                continue; // Skip blocks without extraction directives
            }

            $validatedCount++;

            $exists = file_exists($expectedPath);
            $validation = new BlockValidation(
                id: $codeBlock->id,
                language: $codeBlock->language,
                expectedPath: $expectedPath,
                sourcePath: $filePath,
                lineNumber: $codeBlock->lineNumber,
                exists: $exists,
            );

            if ($exists) { $valid[] = $validation; } else { $missing[] = $validation; }
        }

        return new ValidationResult(
            filePath: $filePath,
            totalBlocks: $validatedCount,
            validBlocks: $valid,
            missingBlocks: $missing,
            duration: 0, // Command will handle timing
        );
    }

    // INTERNAL /////////////////////////////////////////////////////////

    private function extractExpectedPath(CodeBlockNode $codeBlock, MarkdownFile $markdown, string $markdownDir): ?string {
        // Check for newer include= metadata format first
        if ($codeBlock->hasMetadata('include')) {
            $includePath = $codeBlock->metadata('include');
            return Path::join($markdownDir, ltrim($includePath, './'));
        }

        // Check for older @doctest id= format in content
        if (preg_match('/\/\/\s*@doctest\s+id=["\']([^"\']+)["\']/', $codeBlock->content, $matches)) {
            $docTestPath = $matches[1];
            return Path::join($markdownDir, ltrim($docTestPath, './'));
        }

        // Derive expected path from current extraction rules (caseDir + casePrefix + id + ext)
        if (!empty($codeBlock->id) && !empty($codeBlock->language)) {
            $info = MarkdownInfo::from($markdown);
            $extension = ProgrammingLanguage::fileExtension($codeBlock->language);
            $filename = $info->casePrefix . $codeBlock->id . '.' . $extension;
            $relative = $info->caseDir . '/' . $filename;
            return Path::join($markdownDir, ltrim($relative, './'));
        }

        return null;
    }

}
