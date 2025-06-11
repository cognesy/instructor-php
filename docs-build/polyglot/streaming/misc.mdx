---
title: Advanced Stream Processing
description: How to use advanced stream processing features in Polyglot
---

## Using Callbacks

You can use the `onPartialResponse` method to register a callback that is called for each partial response:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$inference = new Inference();
$response = $inference->with(
    messages: 'Write a short story about a space explorer.',
    options: ['stream' => true]
);

// Set up a callback for processing partial responses
$stream = $response->stream()->onPartialResponse(function($partialResponse) {
    echo $partialResponse->contentDelta;
    flush();
});

// Process all responses
foreach ($stream->responses() as $_) {
    // The callback is called for each partial response
    // We don't need to do anything here
}
```


## Transforming Stream Content

You can process and transform the content as it streams:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$inference = new Inference();
$response = $inference->with(
    messages: 'Generate a list of 10 book titles.',
    options: ['stream' => true]
);

$stream = $response->stream()->responses();
$titleCount = 0;
$currentTitle = '';

foreach ($stream as $partialResponse) {
    $content = $partialResponse->contentDelta;

    // Check for new titles (assuming numbered list format)
    if (preg_match('/(\d+)\.\s+(.+?)(?=\n\d+\.|\Z)/s', $content, $matches)) {
        $titleCount++;
        $title = trim($matches[2]);
        echo "Title #{$matches[1]}: $title\n";
    } elseif (!empty(trim($content))) {
        echo $content;
    }
}
```


## Processing JSON Streams

For streaming JSON responses, you need to accumulate content until you have valid JSON:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

$inference = new Inference();
$response = $inference->with(
    messages: 'List 5 countries and their capitals in JSON format.',
    mode: OutputMode::Json,  // Request JSON response
    options: ['stream' => true]
);

$stream = $response->stream()->responses();
$jsonBuffer = '';

foreach ($stream as $partialResponse) {
    $jsonBuffer .= $partialResponse->contentDelta;

    // Try to parse the accumulated JSON
    $tempJson = $jsonBuffer;

    // Attempt to complete any incomplete JSON
    if (substr(trim($tempJson), -1) !== '}') {
        $tempJson .= '}';
    }

    // Replace any trailing commas which would make the JSON invalid
    $tempJson = preg_replace('/,\s*}$/', '}', $tempJson);

    try {
        $data = json_decode($tempJson, true, 512, JSON_THROW_ON_ERROR);
        echo "Valid JSON received so far: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
    } catch (\JsonException $e) {
        // Not a complete valid JSON yet
        echo "Accumulated content: $jsonBuffer\n";
    }
}

// Process the final, complete JSON
try {
    $finalData = json_decode($jsonBuffer, true, 512, JSON_THROW_ON_ERROR);
    echo "Final JSON: " . json_encode($finalData, JSON_PRETTY_PRINT) . "\n";
} catch (\JsonException $e) {
    echo "Error parsing final JSON: " . $e->getMessage() . "\n";
}
```



## Cancellation

In some cases, you may want to stop the generation early:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$inference = new Inference();
$response = $inference->with(
    messages: 'Write a long story about space exploration.',
    options: ['stream' => true]
);

$stream = $response->stream()->responses();
$wordCount = 0;
$maxWords = 100;  // Limit to 100 words

foreach ($stream as $partialResponse) {
    echo $partialResponse->contentDelta;
    flush();

    // Count words in the accumulated content
    $words = str_word_count($partialResponse->content());

    // Stop after reaching the word limit
    if ($words >= $maxWords) {
        echo "\n\n[Generation stopped after $maxWords words]\n";
        break;  // Exit the loop early
    }
}
```

Note that when you break out of the loop, the request to the provider continues in the background, but your application stops processing the response.





## Performance Considerations

When working with streaming responses, keep these performance considerations in mind:

1. **Memory Usage**: Be careful with how you accumulate content, especially for very long responses
2. **Buffer Flushing**: In web applications, make sure output buffers are properly flushed
3. **Connection Stability**: Streaming connections can be more sensitive to network issues
4. **Timeouts**: Adjust timeout settings for long-running streams

Here's an example of memory-efficient processing for very long responses:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$inference = new Inference();
$response = $inference->with(
    messages: 'Generate a very long story.',
    options: [
        'stream' => true,
        'max_tokens' => 10000  // Request a long response
    ]
);

$stream = $response->stream()->responses();
$outputFile = fopen('generated_story.txt', 'w');

foreach ($stream as $partialResponse) {
    // Write chunks directly to file instead of keeping them in memory
    fwrite($outputFile, $partialResponse->contentDelta);

    // Optional: Show a progress indicator
    echo ".";
    flush();
}

fclose($outputFile);
echo "\nGeneration complete. Story saved to generated_story.txt\n";
```
