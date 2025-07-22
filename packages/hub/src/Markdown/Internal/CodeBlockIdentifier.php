<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Markdown\Internal;

final class CodeBlockIdentifier
{
    /**
     * Extracts snippet ID from codeblock content using language-appropriate comment syntax
     */
    public static function extractId(string $content): string {
        $patterns = [
            '/\/\/\s*@snippetId="([a-zA-Z0-9_-]+)"/',  // // style
            '/#\s*@snippetId="([a-zA-Z0-9_-]+)"/',     // # style
            '/<!--\s*@snippetId="([a-zA-Z0-9_-]+)"/',  // <!-- style
            '/\/\*\s*@snippetId="([a-zA-Z0-9_-]+)"/',  // /* style
            '/--\s*@snippetId="([a-zA-Z0-9_-]+)"/',    // -- style
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                return $matches[1];
            }
        }
        
        return '';
    }

    /**
     * Checks if content already has a snippet ID embedded
     */
    public static function hasId(string $content): bool {
        return self::extractId($content) !== '';
    }

    /**
     * Embeds snippet ID into content using language-appropriate comment syntax
     */
    public static function embedId(string $content, string $snippetId, string $language): string {
        if (self::hasId($content)) {
            return $content;
        }

        $commentSyntax = self::getCommentSyntax($language);
        
        if (!empty(trim($content))) {
            return "{$commentSyntax} @snippetId=\"{$snippetId}\"\n{$content}";
        } else {
            return "{$commentSyntax} @snippetId=\"{$snippetId}\"";
        }
    }

    /**
     * Generates a new 4-character hex ID
     */
    public static function generateId(): string {
        return bin2hex(random_bytes(2));
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