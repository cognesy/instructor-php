<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Markdown\Internal;

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
            $pattern = "/^{$escapedSyntax}\s*@doctest\s+.*?id=([\"']?)([a-zA-Z0-9_-]+)\\1.*?$/m";
            
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
     * Creates a complete codeblock ID with the 'codeblock_' prefix
     * Uses provided ID or generates a new one if none provided
     */
    public static function createCodeBlockId(?string $id = null): string {
        $actualId = $id ?? self::generateId();
        return "codeblock_{$actualId}";
    }

    /**
     * Extracts the raw ID part from a full codeblock ID (removes 'codeblock_' prefix)
     */
    public static function extractRawId(string $codeblockId): string {
        return str_replace('codeblock_', '', $codeblockId);
    }

    /**
     * Validates if a given codeblock ID is in the correct format
     * Accepts both human-readable IDs and generated hex IDs
     */
    public static function isValid(string $codeblockId): bool {
        return preg_match('/^codeblock_[a-zA-Z0-9_-]+$/', $codeblockId) === 1;
    }

    /**
     * Gets the appropriate comment syntax for a given language
     */
    public static function getCommentSyntax(string $language): string {
        return match (strtolower($language)) {
            'python', 'py', 'bash', 'sh', 'shell', 'yaml', 'yml', 'ruby', 'r', 'perl', 'makefile', 'dockerfile', 'toml', 'conf', 'ini' => '#',
            'html', 'xml', 'svg' => '<!--',
            'css', 'scss', 'sass', 'less' => '/*',
            'sql' => '--',
            'lua' => '--',
            'haskell', 'hs' => '--',
            'elm' => '--',
            default => '//', // Default for most C-style languages: js, ts, java, c, cpp, php, go, rust, etc.
        };
    }
}