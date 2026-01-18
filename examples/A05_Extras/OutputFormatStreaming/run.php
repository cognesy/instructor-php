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

// Track when chunks arrive to prove streaming
$startTime = microtime(true);
$chunkTimes = [];

// Stream extraction and receive final result as array
$stream = (new StructuredOutput)
    ->withResponseClass(Article::class)
    ->intoArray()
    ->withMessages("Extract: 'Introduction to PHP 8.4' by Jane Doe, 1500 words, tags: php, tutorial, programming")
    ->stream();

// During streaming, partials are objects (for validation)
foreach ($stream->responses() as $response) {
    dump($response);
}

// Final result is an array (not object)
$finalArticle = $stream->finalValue();
dump($finalArticle);

assert(is_array($finalArticle));
assert(is_string($finalArticle['title']) && $finalArticle['title'] !== '');
assert(is_string($finalArticle['author']) && $finalArticle['author'] !== '');
assert(is_int($finalArticle['wordCount']) && $finalArticle['wordCount'] > 0);
assert(is_array($finalArticle['tags']));
assert(in_array('php', $finalArticle['tags']));

echo "\nFinal result (array):\n";
echo "Title: {$finalArticle['title']}\n";
echo "Author: {$finalArticle['author']}\n";
echo "Words: {$finalArticle['wordCount']}\n";
echo "Tags: " . implode(', ', $finalArticle['tags']) . "\n";
?>
