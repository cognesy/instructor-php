<?php declare(strict_types=1);

namespace Cognesy\Doctor\Markdown\Internal;

final class CodeBlockIdentifier
{
    /**
     * Extracts ID from @doctest annotation using language-appropriate comment syntax
     * Returns the raw ID without the 'codeblock_' prefix
     */
    public static function extractId(string $content): string {
        $allSyntax = ['//','#','<!--','/*','--']; // All possible comment styles
        
        foreach ($allSyntax as $syntax) {
            $escapedSyntax = preg_quote($syntax, '/');
            // Match @doctest with id parameter - stop at line end
            $pattern = "/^{$escapedSyntax}\s*@doctest\s+.*?id=([\"']?)([^\"'\\s]+)\\1.*?$/m";
            
            if (preg_match($pattern, $content, $matches)) {
                return $matches[2]; // Return the ID value
            }
        }
        
        return '';
    }

    /**
     * Generates a new 4-character hex ID
     */
    public static function generateId(): string {
        return bin2hex(random_bytes(2));
    }

    /**
     * Creates a codeblock ID - uses provided ID or generates a new one if none provided
     */
    public static function createCodeBlockId(?string $id = null): string {
        return $id ?? self::generateId();
    }

    /**
     * Returns the ID as-is (no longer needed since there's no prefix)
     */
    public static function extractRawId(string $codeblockId): string {
        return $codeblockId;
    }

    /**
     * Validates if a given codeblock ID is in the correct format
     * Accepts both human-readable IDs, generated hex IDs, and file paths
     */
    public static function isValid(string $codeblockId): bool {
        return preg_match('/^[a-zA-Z0-9_.\/-]+$/', $codeblockId) === 1;
    }

}