<?php declare(strict_types=1);

namespace Cognesy\Doctor\Doctest\Services;

use Cognesy\Doctor\Doctest\Data\ValidationResult;
use Cognesy\Doctor\Markdown\MarkdownFile;
use Cognesy\Doctor\Markdown\Nodes\CodeBlockNode;

class ValidationService
{
    public function validateFile(string $filePath): ValidationResult {
        $sourceContent = file_get_contents($filePath);
        $markdown = MarkdownFile::fromString($sourceContent, $filePath);
        $markdownDir = dirname($filePath);

        $valid = [];
        $missing = [];
        $validatedCount = 0;

        foreach ($markdown->codeBlocks() as $codeBlock) {
            $expectedPath = $this->extractExpectedPath($codeBlock, $markdownDir);

            if ($expectedPath === null) {
                continue; // Skip blocks without extraction directives
            }

            $validatedCount++;

            $validation = [
                'id' => $codeBlock->id,
                'language' => $codeBlock->language,
                'expectedPath' => $expectedPath,
                'sourcePath' => $filePath,
                'lineNumber' => $codeBlock->lineNumber,
                'exists' => file_exists($expectedPath),
            ];

            if ($validation['exists']) {
                $valid[] = $validation;
            } else {
                $missing[] = $validation;
            }
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

    private function extractExpectedPath(CodeBlockNode $codeBlock, string $markdownDir): ?string {
        // Check for newer include= metadata format first
        if ($codeBlock->hasMetadata('include')) {
            $includePath = $codeBlock->metadata('include');
            $resolvedPath = $markdownDir . '/' . ltrim($includePath, './');
            return $this->normalizePath($resolvedPath);
        }

        // Check for older @doctest id= format in content
        if (preg_match('/\/\/\s*@doctest\s+id=["\']([^"\']+)["\']/', $codeBlock->content, $matches)) {
            $docTestPath = $matches[1];
            $resolvedPath = $markdownDir . '/' . ltrim($docTestPath, './');
            return $this->normalizePath($resolvedPath);
        }

        return null;
    }

    private function normalizePath(string $path): string {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }
}