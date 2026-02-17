---
title: 'Context caching (structured output)'
docname: 'context_cache_structured'
---

## Overview

Instructor offers a simplified way to work with LLM providers' APIs supporting caching,
so you can focus on your business logic while still being able to take advantage of lower
latency and costs.

> **Note 1:** Instructor supports context caching for Anthropic API and OpenAI API.

> **Note 2:** Context caching is automatic for all OpenAI API calls. Read more
> in the [OpenAI API documentation](https://platform.openai.com/docs/guides/prompt-caching).


## Example

When you need to process multiple requests with the same context, you can use context
caching to improve performance and reduce costs.

In our example we will be analyzing the README.md file of this Github project and
generating its structured description for multiple audiences.

Let's start by defining the data model for the project details and the properties
that we want to extract or generate based on README file.

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Schema\Attributes\Description;
use Cognesy\Utils\Str;

class Project {
    public string $name;
    public string $targetAudience;
    /** @var string[] */
    #[Description('Technology platform and libraries used in the project')]
    public array $technologies;
    /** @var string[] */
    #[Description('Target audience domain specific features and capabilities of the project')]
    public array $features;
    /** @var string[] */
    #[Description('Target audience domain specific applications and potential use cases of the project')]
    public array $applications;
    #[Description('Explain the purpose of the project and the target audience domain specific problems it solves')]
    public string $description;
    #[Description('Target audience domain specific example code in Markdown demonstrating an application of the library')]
    public string $code;
}
?>
```

We read the content of the README.md file and cache the context, so it can be reused for
multiple requests.

```php
<?php
$content = file_get_contents(__DIR__ . '/../../../README.md');

$cached = (new StructuredOutput)->using('anthropic')->withCachedContext(
    system: 'Your goal is to respond questions about the project described in the README.md file'
        . "\n\n# README.md\n\n" . $content,
    prompt: 'Respond with strict JSON object using schema:\n<|json_schema|>',
);//->withHttpDebugPreset('on');
?>
```
At this point we can use Instructor structured output processing to extract the project
details from the README.md file into the `Project` data model.

Let's start by asking the user to describe the project for a specific audience: P&C insurance CIOs.

```php
<?php
// get StructuredOutputResponse object to get access to usage and other metadata
$response1 = $cached->with(
    messages: 'Describe the project in a way compelling to my audience: P&C insurance CIOs.',
    responseModel: Project::class,
    options: ['max_tokens' => 4096],
    mode: OutputMode::MdJson,
)->create();

// get processed value - instance of Project class
$project1 = $response1->get();
dump($project1);
assert($project1 instanceof Project);
assert(Str::contains($project1->name, 'Instructor'));

// get usage information from response() method which returns raw InferenceResponse object
$usage1 = $response1->response()->usage();
echo "Usage: {$usage1->inputTokens} prompt tokens, {$usage1->cacheWriteTokens} cache write tokens\n";
?>
```
Now we can use the same context to ask the user to describe the project for a different
audience: boutique CMS consulting company owner.

Anthropic API will use the context cached in the previous request to provide the response,
which results in faster processing and lower costs.

```php
<?php
// get StructuredOutputResponse object to get access to usage and other metadata
$response2 = $cached->with(
    messages: "Describe the project in a way compelling to my audience: boutique CMS consulting company owner.",
    responseModel: Project::class,
    options: ['max_tokens' => 4096],
    mode: OutputMode::Json,
)->create();

// get the processed value - instance of Project class
$project2 = $response2->get();
dump($project2);
assert($project2 instanceof Project);
assert(Str::contains($project2->name, 'Instructor'));

// get usage information from response() method which returns raw InferenceResponse object
$usage2 = $response2->response()->usage();
echo "Usage: {$usage2->inputTokens} prompt tokens, {$usage2->cacheReadTokens} cache read tokens\n";
assert($usage2->cacheReadTokens > 0, 'Expected cache read tokens');
?>
```
