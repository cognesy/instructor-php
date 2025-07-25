<?php declare(strict_types=1);

namespace Cognesy\Utils;

class ProgrammingLanguage {
    public static function fileExtension(string $language) : string {
        return match ($language) {
            'python' => 'py',
            'javascript' => 'js',
            'typescript' => 'ts',
            'java' => 'java',
            'csharp' => 'cs',
            'ruby' => 'rb',
            'php' => 'php',
            'go' => 'go',
            'c' => 'c',
            'cpp' => 'cpp',
            'rust' => 'rs',
            'bash', 'sh' => 'sh',
            'html' => 'html',
            'css' => 'css',
            'sql' => 'sql',
            'lua' => 'lua',
            'haskell' => 'hs',
            'perl' => 'pl',
            'dockerfile' => 'dockerfile',
            'yaml', 'yml' => 'yml',
            default => $language,
        };
    }

    /**
     * Gets the appropriate comment syntax for a given language
     */
    public static function commentSyntax(string $language): string {
        return match (strtolower($language)) {
            'python', 'py', 'bash', 'sh', 'shell', 'yaml', 'yml', 'ruby', 'r', 'perl', 'makefile', 'dockerfile', 'toml', 'conf', 'ini' => '#',
            'html', 'xml', 'svg' => '<!--',
            'css', 'scss', 'sass', 'less' => '/*',
            'haskell', 'hs', 'lua', 'sql', 'elm' => '--',
            // Default for most C-style languages: js, ts, java, c, cpp, php, go, rust, etc.
            default => '//',
        };
    }

    public static function fileTemplate(string $language) : string {
        return match($language) {
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
    }

    public static function isCommentLine(string $language, string $line) : bool {
        $language = strtolower($language);

        // Handle different comment styles based on language
        return match (true) {
            // C-style languages (PHP, JS, Java, C#, etc.)
            in_array($language, ['php', 'javascript', 'js', 'java', 'c', 'cpp', 'csharp', 'c#', 'typescript', 'ts']) =>
                str_starts_with($line, '//') || str_starts_with($line, '/*') || str_starts_with($line, '*'),

            // Python, Ruby, Bash, etc.
            in_array($language, ['python', 'py', 'ruby', 'rb', 'bash', 'shell', 'sh']) =>
                str_starts_with($line, '#') && !str_starts_with($line, '#!'),

            // HTML/XML
            in_array($language, ['html', 'xml']) =>
            str_starts_with($line, '<!--'),

            // CSS
            $language === 'css' =>
                str_starts_with($line, '/*') || str_starts_with($line, '*'),

            // SQL
            $language === 'sql' =>
                str_starts_with($line, '--') || str_starts_with($line, '/*') || str_starts_with($line, '*'),

            // Default: no comment detection for unknown languages
            default => false,
        };
    }

    public static function linesOfCode(string $language, string $code): int {
        $lines = explode("\n", $code);
        $count = 0;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            // Skip empty lines
            if ($trimmed === '') {
                continue;
            }
            // Skip comment lines based on language
            if (self::isCommentLine($language, $trimmed)) {
                continue;
            }
            $count++;
        }
        return $count;
    }
}