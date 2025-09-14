<?php declare(strict_types=1);

namespace Cognesy\Doctor\Markdown\Internal;

use Cognesy\Doctor\Markdown\Exceptions\MetadataConflictException;
use Cognesy\Utils\ProgrammingLanguage;

final class CodeBlockMetadataParser
{
    /**
     * Parses fence line parameters like: php param1=1 param2="xxx" param3=[33, "444"]
     */
    public static function parseFenceLineMetadata(string $fenceInfo): array {
        $fenceInfo = trim($fenceInfo);
        if ($fenceInfo === '') {
            return ['language' => '', 'metadata' => []];
        }

        $tokens = self::tokenizeFenceInfo($fenceInfo);
        $language = array_shift($tokens) ?? '';
        $metadata = [];

        foreach ($tokens as $token) {
            if (str_contains($token, '=')) {
                [$key, $value] = explode('=', $token, 2);
                $metadata[trim($key)] = self::parseValue(trim($value));
            } else {
                // Standalone values treated as boolean flags
                $metadata[trim($token)] = true;
            }
        }

        return ['language' => $language, 'metadata' => $metadata];
    }

    /**
     * Extracts @doctest metadata from code content
     */
    public static function extractDoctestMetadata(string $content, string $language): array {
        $commentSyntax = ProgrammingLanguage::commentSyntax($language);
        $escapedSyntax = preg_quote($commentSyntax, '/');
        
        // Pattern to match @doctest annotations (non-greedy, stop at line end)
        $pattern = "/^{$escapedSyntax}\s*@doctest\s+(.+?)(?=\n|$)/m";
        
        if (!preg_match($pattern, $content, $matches)) {
            return [];
        }

        $metadataString = trim($matches[1]);
        return self::parseParameterString($metadataString);
    }

    /**
     * Combines metadata from fence line and @doctest annotations.
     * Throws exception if the same key exists in both sources.
     * Extracted ID is used only if 'id' key is not present in either source.
     */
    public static function combineMetadata(
        array $fenceMetadata,
        array $doctestMetadata,
        string $extractedId = ''
    ): array {
        // Check for conflicts between fence and @doctest metadata
        $conflicts = array_intersect_key($fenceMetadata, $doctestMetadata);
        if (!empty($conflicts)) {
            $conflictKey = array_key_first($conflicts);
            throw new MetadataConflictException(
                $conflictKey,
                $fenceMetadata[$conflictKey],
                $doctestMetadata[$conflictKey]
            );
        }
        
        // Combine metadata from both sources
        $combined = array_merge($fenceMetadata, $doctestMetadata);
        
        // Add extracted ID only if 'id' key is not already present
        if ($extractedId !== '' && !isset($combined['id'])) {
            $combined['id'] = $extractedId;
        }
        
        return $combined;
    }

    // INTERNAL ////////////////////////////////////////////////////////////////

    /**
     * Tokenizes fence info string, respecting quotes and brackets
     */
    private static function tokenizeFenceInfo(string $fenceInfo): array {
        $tokens = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = null;
        $bracketDepth = 0;
        $inBrackets = false;

        $length = strlen($fenceInfo);
        for ($i = 0; $i < $length; $i++) {
            $char = $fenceInfo[$i];
            
            // Handle quotes
            if (!$inBrackets && !$inQuotes && ($char === '"' || $char === "'")) {
                $inQuotes = true;
                $quoteChar = $char;
                $current .= $char;
            } elseif ($inQuotes && $char === $quoteChar && ($i === 0 || $fenceInfo[$i - 1] !== '\\')) {
                $inQuotes = false;
                $quoteChar = null;
                $current .= $char;
            }
            // Handle brackets for arrays
            elseif (!$inQuotes && $char === '[') {
                $inBrackets = true;
                $bracketDepth++;
                $current .= $char;
            } elseif (!$inQuotes && $char === ']') {
                $bracketDepth--;
                if ($bracketDepth === 0) {
                    $inBrackets = false;
                }
                $current .= $char;
            }
            // Handle whitespace as token delimiter
            elseif (!$inQuotes && !$inBrackets && ctype_space($char)) {
                if ($current !== '') {
                    $tokens[] = $current;
                    $current = '';
                }
            } else {
                $current .= $char;
            }
        }
        
        if ($current !== '') {
            $tokens[] = $current;
        }

        return $tokens;
    }

    /**
     * Parses parameter string like: param1=1 param2="xxx" param3=[33, "444"]
     */
    private static function parseParameterString(string $paramString): array {
        $tokens = self::tokenizeFenceInfo($paramString);
        $metadata = [];

        foreach ($tokens as $token) {
            if (str_contains($token, '=')) {
                [$key, $value] = explode('=', $token, 2);
                $metadata[trim($key)] = self::parseValue(trim($value));
            } else {
                // Standalone values treated as boolean flags
                $metadata[trim($token)] = true;
            }
        }

        return $metadata;
    }

    /**
     * Parses a value string into appropriate PHP type
     */
    private static function parseValue(string $value): mixed {
        $value = trim($value);
        
        // Handle quoted strings - ALWAYS return as string, regardless of content
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            return substr($value, 1, -1); // Remove quotes and return as string
        }
        
        // Handle arrays [1, "two", 3]
        if (str_starts_with($value, '[') && str_ends_with($value, ']')) {
            return self::parseArray(substr($value, 1, -1));
        }
        
        // Handle booleans (only unquoted)
        if (strtolower($value) === 'true') {
            return true;
        }
        if (strtolower($value) === 'false') {
            return false;
        }
        
        // Handle null (only unquoted)
        if (strtolower($value) === 'null') {
            return null;
        }
        
        // Handle numbers (only unquoted)
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }
        
        // Default to string
        return $value;
    }

    /**
     * Parses array content like: 1, "two", 3
     */
    private static function parseArray(string $arrayContent): array {
        if (trim($arrayContent) === '') {
            return [];
        }

        $result = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = null;
        $bracketDepth = 0;

        $length = strlen($arrayContent);
        for ($i = 0; $i < $length; $i++) {
            $char = $arrayContent[$i];
            
            if (!$inQuotes && ($char === '"' || $char === "'")) {
                $inQuotes = true;
                $quoteChar = $char;
                $current .= $char;
            } elseif ($inQuotes && $char === $quoteChar && ($i === 0 || $arrayContent[$i - 1] !== '\\')) {
                $inQuotes = false;
                $quoteChar = null;
                $current .= $char;
            } elseif (!$inQuotes && $char === '[') {
                $bracketDepth++;
                $current .= $char;
            } elseif (!$inQuotes && $char === ']') {
                $bracketDepth--;
                $current .= $char;
            } elseif (!$inQuotes && $bracketDepth === 0 && $char === ',') {
                if (trim($current) !== '') {
                    $result[] = self::parseValue(trim($current));
                    $current = '';
                }
            } else {
                $current .= $char;
            }
        }
        
        if (trim($current) !== '') {
            $result[] = self::parseValue(trim($current));
        }

        return $result;
    }
}