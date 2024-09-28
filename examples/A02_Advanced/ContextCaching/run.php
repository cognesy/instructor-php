---
title: 'Context caching (Anthropic)'
docname: 'context_cache'
---

## Overview

Instructor offers a simplified way to work with LLM providers' APIs supporting caching
(currently only Anthropic API), so you can focus on your business logic while still being
able to take advantage of lower latency and costs.

> **Note:** Context caching is only available for Anthropic API.

## Example

When you need to process multiple requests with the same context, you can use context
caching to improve performance and reduce costs.

In our example we will be analyzing the README.md file of this Github project and
generating its structured description for multiple audiences.

Let's start by defining the data model for the project details and the properties
that we want to extract or generate based on README file.

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Schema\Attributes\Description;
use Cognesy\Instructor\Utils\Env;

class Project {
    public string $name;
    /** @var string[] */
    #[Description('Technology platform and libraries used in the project')]
    public array $technologies;
    /** @var string[] */
    #[Description('Features and capabilities of the project')]
    public array $features;
    /** @var string[] */
    #[Description('Applications and potential use cases of the project')]
    public array $applications;
    #[Description('Explain the purpose of the project and the problems it solves')]
    public string $description;
    #[Description('Example code in Markdown demonstrating the application of the library')]
    public string $code;
}
?>
```

We read the content of the README.md file and cache the context, so it can be reused for
multiple requests.

```php
<?php
$content = file_get_contents(__DIR__ . '/../../../README.md');

$cached = (new Instructor)->withConnection('anthropic')->cacheContext(
    system: 'Your goal is to respond questions about the project described in the README.md file',
    prompt: 'Respond to the user with a description of the project with JSON using schema:\n<|json_schema|>',
    input: "# README.md\n\n" . $content,
);
?>
```
At this point we can use Instructor structured output processing to extract the project
details from the README.md file into the `Project` data model.

Let's start by asking the user to describe the project for a specific audience: P&C insurance CIOs.

```php
<?php
$project = $cached->respond(
    messages: 'Describe the project - my audience is P&C insurance CIOs',
    mode: Mode::Json,
    responseModel: Project::class,
    options: ['max_tokens' => 4096],
);
dump($project);
?>
```
Now we can use the same context to ask the user to describe the project for a different
audience: boutique CMS consulting company owner.

Anthropic API will use the context cached in the previous request to provide the response,
which results in faster processing and lower costs.

```php
<?php
$project = $cached->respond(
    messages: 'Describe the project - my audience is boutique CMS consulting company owner',
    mode: Mode::Json,
    responseModel: Project::class,
    options: ['max_tokens' => 4096],
);
dump($project);
?>
```
