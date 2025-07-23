<?php

namespace Cognesy\Utils;

class FileExtension {
    public static function forLanguage(string $language) : string {
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
}