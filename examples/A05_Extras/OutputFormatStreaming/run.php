---
title: 'Streaming with array output format'
docname: 'output_format_streaming'
---

## Overview

When streaming responses, you often want real-time updates as objects (for
validation and deduplication), but the final result as an array (for database
storage or API responses).

The `intoArray()` method works seamlessly with streaming - partial updates
are objects during streaming, but the final value is returned as an array.


## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

// Define schema for extraction
class Article {
    public string $title;
    public string $author;
    public int $wordCount;
    /** @var string[] */
    public array $tags;
}

echo "Streaming article extraction...\n\n";

// Stream extraction and receive final result as array
$stream = (new StructuredOutput)
    ->withResponseClass(Article::class)
    ->intoArray()  // Final result will be array
    ->with(
        messages: "Extract: 'Introduction to PHP 8.4' by Jane Doe, 1500 words, tags: php, tutorial, programming",
    )
    ->stream();

// During streaming, partials are objects (for validation)
$updateCount = 0;
foreach ($stream->partials() as $partial) {
    $updateCount++;
    // Partial updates are still objects during streaming
    assert(is_object($partial) || is_array($partial));
    echo "Update #{$updateCount}: " . (is_object($partial) ? get_class($partial) : 'array') . "\n";
}

// Final result is an array (not object)
$finalArticle = $stream->finalValue();

dump($finalArticle);

assert(is_array($finalArticle));
assert($finalArticle['title'] === 'Introduction to PHP 8.4');
assert($finalArticle['author'] === 'Jane Doe');
assert($finalArticle['wordCount'] === 1500);
assert(is_array($finalArticle['tags']));
assert(in_array('php', $finalArticle['tags']));

echo "\nFinal result (array):\n";
echo "Title: {$finalArticle['title']}\n";
echo "Author: {$finalArticle['author']}\n";
echo "Words: {$finalArticle['wordCount']}\n";
echo "Tags: " . implode(', ', $finalArticle['tags']) . "\n";
?>
```

## Expected Output

```
Streaming article extraction...

Update #1: Article
Update #2: Article
Update #3: Article

array(4) {
  ["title"]=>
  string(26) "Introduction to PHP 8.4"
  ["author"]=>
  string(8) "Jane Doe"
  ["wordCount"]=>
  int(1500)
  ["tags"]=>
  array(3) {
    [0]=>
    string(3) "php"
    [1]=>
    string(8) "tutorial"
    [2]=>
    string(11) "programming"
  }
}

Final result (array):
Title: Introduction to PHP 8.4
Author: Jane Doe
Words: 1500
Tags: php, tutorial, programming
```

## How It Works

1. **During streaming**: Partial updates are deserialized as objects for real-time
   validation and deduplication
2. **After streaming completes**: The final result is re-extracted and returned
   as an array (respecting `intoArray()`)
3. **Best of both worlds**: Object validation during streaming, array convenience
   for the final result
