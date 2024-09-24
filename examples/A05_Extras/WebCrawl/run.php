---
title: 'Web Crawler'
docname: 'web_crawler'
---

## Overview

This example demonstrates how to extract structured data from a website with multiple
webs page and get the results as PHP object.

## Example

In this example we will be comparing LLM libraries.

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Web\Webpage;
use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Schema\Attributes\Instructions;

class Library {
    public string $name = '';
    public string $location = '';
    public string $description = '';
    #[Instructions('Remove any tracking parameters from the URL')]
    public string $websiteUrl = '';
    #[Instructions('List of technologies library relies on')]
    /** @var string[] */
    public array $techStack = [];
    #[Instructions('Provide a simple code example of how to use the library')]
    public string $codeExample = '';
}

$instructor = (new Instructor)->withClient('openai');

$companyGen = Webpage::withScraper('none')
    ->get('https://themanifest.com/pl/software-development/laravel/companies?page=1')
    ->cleanup()
    ->select('.directory-providers__list')
    ->selectMany(selector: '.provider-card', callback: fn($item) => $item->asMarkdown(), limit: 3);

$companies = [];
foreach($companyGen as $company) {
    $company = $instructor->respond(
        messages: $company,
        responseModel: Company::class,
        mode: Mode::Json
    );
    $companies[] = $company;
    dump($company);
}

?>
```
