<?php declare(strict_types=1);

namespace Cognesy\Schema\TypeString;

class TypeStringParser
{
    public function getTypes(string $typeString) : array {
        return $this->process($typeString);
    }

    // INTERNAL //////////////////////////////////////////////////////////////////////

    private function process(string $typeString): array {
        $parsedTypes = $this->parse($typeString);
        if (empty($parsedTypes)) {
            return [];
        }
        // preprocess to ensure we have unique types
        $uniqueTypes = array_values(array_unique($parsedTypes));
        if (count($parsedTypes) === 1 && in_array('', $parsedTypes, true)) {
            return [];
        }
        if (count($parsedTypes) === 1 && in_array('null', $parsedTypes, true)) {
            return ['null'];
        }
        // sort alphabetically to ensure consistent order
        sort($uniqueTypes);
        return $uniqueTypes;
    }

    /**
     * @return list<string>
     */
    private function parse(string $type): array
    {
        $type = trim($type);

        if (str_starts_with($type, '|')) {
            $type = substr($type, 1);
        }

        if (str_ends_with($type, '|')) {
            $type = substr($type, 0, -1);
        }

        if ($type === '?') {
            throw new \InvalidArgumentException(
                "Type string '$type' is incomplete. It is just a '?' without a base type."
            );
        }

        if ($this->isUnfinishedArray($type)) {
            throw new \InvalidArgumentException(
                "Type string '$type' is incomplete. It ends with an opening generic delimiter '<'."
            );
        }

        if ($type === '') {
            return [];
        }

        // First, split the type by the union operator '|'. This is the highest precedence operation.
        // `self::split(...)` correctly handles nested generics, e.g., in `int|array<string|bool>`.
        $unionParts = $this->splitOnTopLevelDelimiter($type, '|');

        if (count($unionParts) > 1) {
            // If it's a union, parse each part and flatten the results.
            return array_merge(...array_map($this->parse(...), $unionParts));
        }

        // From here, we are dealing with a single, non-union type part.
        $part = trim($type);

        // Special case: handle 'array[]' as just 'array'
        if ($part === 'array[]') {
            return ['array'];
        }

        // 1. Handle nullable types, e.g., "?int". This is syntax sugar for `int|null`.
        if (str_starts_with($part, '?')) {
            // A nullable type is a union of 'null' and the base type.
            return array_merge(['null'], $this->parse(substr($part, 1)));
        }

        // 2. Handle generic array-like syntax, e.g., "array<T>", "list<T>", "iterable<K, V>".
        // This regex is strategic: it only captures the container type and its inner content.
        // It does not try to parse the complex inner content itself.
        if (preg_match('/^(array|list|iterable)\s*<(.+)>$/i', $part, $matches)) {
            $genericContent = trim($matches[2]);

            // For `array<K, V>`, we only care about the value type `V`.
            // We find the last top-level comma to get the value type.
            $genericParams = $this->splitOnTopLevelDelimiter($genericContent, ',');
            $valueType = trim(end($genericParams));

            // Recursively parse the value type, which may be a union itself (e.g., `string|bool`).
            $subTypes = $this->parse($valueType);

            $result = [];
            foreach ($subTypes as $subType) {
                // If the inner type is 'null' (from a `?` modifier), it stays 'null'.
                // Otherwise, it becomes an array of that type, e.g., 'string' -> 'string[]'.
                if ($subType === 'null') {
                    $result[] = 'null';
                } else {
                    $result[] = $subType . '[]';
                }
            }
            return $result;
        }

        // 3. Handle simple types ("int"), pre-normalized arrays ("int[]"), and the plain "array" type.
        // No further processing is needed for these.
        return [$part];
    }

    /**
     * Splits a string by a delimiter, but only at the top level (i.e., not inside '<' and '>').
     *
     * @return list<string>
     */
    private function splitOnTopLevelDelimiter(string $subject, string $delimiter): array
    {
        if ($subject === '') {
            return [];
        }

        // Special handling for pipe-related edge cases
        if ($subject === '|' || $subject === '||') {
            return [];
        }

        // Handle leading pipe ('|int')
        if (str_starts_with($subject, '|')) {
            return [trim(substr($subject, 1))];
        }

        // Handle trailing pipe ('int|')
        if (str_ends_with($subject, '|')) {
            return [trim(substr($subject, 0, -1))];
        }

        $parts = [];
        $buffer = '';
        $depth = 0;
        $length = strlen($subject);

        for ($i = 0; $i < $length; $i++) {
            $char = $subject[$i];

            if ($char === '<') {
                $depth++;
            } elseif ($char === '>') {
                // Ensure depth doesn't go below zero on malformed input.
                $depth = max(0, $depth - 1);
            }

            if ($char === $delimiter && $depth === 0) {
                $parts[] = $buffer;
                $buffer = '';
            } else {
                $buffer .= $char;
            }
        }

        // Add the final part to the array.
        $parts[] = $buffer;

        // Filter out empty parts
        $parts = array_map('trim', $parts);
        $parts = array_filter($parts, fn($part) => $part !== '');

        return array_values($parts);
    }

    private function isUnfinishedArray(mixed $trimmed) : bool {
        return (preg_match('/^(array|list|iterable)\s*<$/i', $trimmed)) > 0;
    }
}
