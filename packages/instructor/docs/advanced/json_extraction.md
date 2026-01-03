---
title: 'JSON Extraction Strategies'
description: 'How InstructorPHP extracts JSON from LLM responses'
---

# JSON Extraction Strategies

InstructorPHP uses multiple strategies to extract JSON from LLM responses,
handling various edge cases where the LLM might return JSON wrapped in
markdown, text, or malformed.

## Extraction Pipeline

When processing an LLM response, InstructorPHP tries multiple extraction
strategies in order:

### 1. Direct Parsing (Try As-Is)

Attempts to parse the response directly as JSON:

```text
LLM response:
{"name": "John", "age": 30}

✅ Parsed successfully
```

### 2. Markdown Code Block Extraction

Extracts JSON from markdown fenced code blocks:

```text
LLM response:
Here's the data you requested:

```json
{"name": "John", "age": 30}
```

✅ Extracts content between ```json and ```
```

### 3. Bracket Matching

Finds first `{` and last `}` to extract JSON:

```text
LLM response:
The user data is {"name": "John", "age": 30} as extracted from the text.

✅ Extracts from first { to last }
```

### 4. Smart Brace Matching

Handles nested braces and escaped quotes:

```text
LLM response:
Here is {"user": {"name": "John \"The Great\"", "age": 30}} extracted.

✅ Correctly handles:
   - Nested braces
   - Escaped quotes
   - String boundaries
```

## Parsing Strategies

After extraction, multiple parsers attempt to handle malformed JSON:

### 1. Standard JSON Parser

Native `json_decode` with strict error handling.

```php
json_decode($json, true, 512, JSON_THROW_ON_ERROR)
```

### 2. Resilient Parser

Applies automatic repairs before parsing:

- **Balance quotes** - Adds missing closing quotes
- **Remove trailing commas** - Fixes `{"a": 1,}`
- **Balance braces** - Adds missing `}` or `]`

```text
Malformed JSON:
{"name": "John", "age": 30

Resilient parser repairs:
{"name": "John", "age": 30}  // ✅ Added missing }
```

### 3. Partial JSON Parser

Handles incomplete JSON during streaming:

```text
Partial JSON from streaming:
{"name": "John", "age":

✅ Completes to:
{"name": "John", "age": null}
```

## Implementation Details

**Location:** `packages/utils/src/Json/JsonParser.php`

```php
class JsonParser {
    public function findCompleteJson(string $input): string {
        $extractors = [
            fn($text) => [$text],                          // Direct
            fn($text) => $this->findByMarkdown($text),     // Markdown
            fn($text) => [$this->findByBrackets($text)],   // Brackets
            fn($text) => $this->findJSONLikeStrings($text),// Smart braces
        ];

        foreach ($extractors as $extractor) {
            foreach ($extractor($input) as $candidate) {
                if ($parsed = $this->tryParse($candidate)) {
                    return json_encode($parsed);
                }
            }
        }

        return '';
    }

    private function tryParse(string $maybeJson): mixed {
        $parsers = [
            fn($json) => json_decode($json, true, 512, JSON_THROW_ON_ERROR),
            fn($json) => (new ResilientJsonParser($json))->parse(),
            fn($json) => (new PartialJsonParser)->parse($json),
        ];
        // ... try each parser
    }
}
```

## Why This Matters

LLMs don't always return clean JSON:

- **Claude** sometimes wraps in markdown
- **GPT-4** may add explanations
- **Gemini** might include partial responses during streaming
- **Custom prompts** can lead to unexpected formats

InstructorPHP's multi-strategy approach ensures maximum compatibility.

## Common Scenarios

### Scenario 1: LLM Adds Explanation

```text
LLM response:
Based on the text, I extracted the following information:

{"name": "John Doe", "age": 30, "email": "john@example.com"}

This represents the user data found in the document.
```

✅ **Strategy 3 (Bracket Matching)** extracts the JSON successfully

### Scenario 2: Markdown Wrapped Response

```text
LLM response:
Sure! Here's the structured data:

```json
{
  "name": "Jane Smith",
  "age": 25
}
```

I've extracted the user information as requested.
```

✅ **Strategy 2 (Markdown Extraction)** handles this case

### Scenario 3: Malformed JSON

```text
LLM response:
{"name": "Bob", "age": 35, "active": true,}
```

✅ **Resilient Parser** removes the trailing comma and parses successfully

### Scenario 4: Streaming Partial Response

```text
Streaming chunk:
{"name": "Alice", "email": "alice@
```

✅ **Partial Parser** completes to:
```json
{"name": "Alice", "email": "alice@"}
```

## Error Handling

If all strategies fail, InstructorPHP:

1. Returns an empty string from `findCompleteJson()`
2. Triggers a validation error
3. Initiates retry mechanism (if configured)
4. Provides error feedback to LLM for self-correction

## Performance Considerations

**Extraction overhead:**
- Direct parsing: ~0.1ms
- Markdown extraction: ~0.5ms (regex)
- Bracket matching: ~0.2ms (string ops)
- Smart brace matching: ~1-2ms (character iteration)

Most responses succeed on first strategy (direct parsing).

## Custom Extraction Strategies

You can add custom extraction strategies to handle non-standard response formats:

```php
use Cognesy\Instructor\Extraction\Contracts\ExtractionStrategy;
use Cognesy\Utils\Result\Result;

class XmlCdataJsonStrategy implements ExtractionStrategy
{
    public function extract(string $content): Result
    {
        if (preg_match('/<!\[CDATA\[(.*?)\]\]>/s', $content, $matches)) {
            $json = trim($matches[1]);
            try {
                json_decode($json, flags: JSON_THROW_ON_ERROR);
                return Result::success($json);
            } catch (\JsonException) {
                return Result::failure('Invalid JSON in CDATA');
            }
        }
        return Result::failure('No CDATA found');
    }

    public function name(): string
    {
        return 'xml_cdata';
    }
}
```

### Using Custom Strategies

**For sync responses:**
```php
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Extraction\Strategies\DirectJsonStrategy;

$result = (new StructuredOutput)
    ->withExtractionStrategies(
        new DirectJsonStrategy(),
        new XmlCdataJsonStrategy(),
    )
    ->withResponseClass(User::class)
    ->with(messages: 'Extract user')
    ->get();
```

**For streaming responses:**
```php
$stream = (new StructuredOutput)
    ->withStreamingExtractionStrategies(
        new DirectJsonStrategy(),
        new XmlCdataJsonStrategy(),
    )
    ->withResponseClass(User::class)
    ->with(messages: 'Extract user')
    ->stream();
```

See: [Output Formats - Pluggable Extraction](output_formats.md#pluggable-extraction) for comprehensive documentation and examples.

## Related Documentation

- [Response Models](../internals/response_models.md) - How schemas are processed
- [Validation](../essentials/validation.md) - What happens after extraction
- [Retry Mechanisms](../essentials/retry.md) - Error handling and retries
