---
title: 'Web page to PHP objects'
docname: 'web_to_objects'
---

## Overview

This example demonstrates how to extract structured data from a web page and get
it as PHP object.

## Example

In this example we will be extracting list of Laravel companies from The Manifest
website. The result will be a list of `Company` objects.

We use Webpage extractor to get the content of the page and specify 'none' scraper,
which means that we will be using built-in `file_get_contents` function to get the
content of the page.

In production environment you might want to use one of the supported scrapers:
 - `browsershot`
 - `scrapingbee`
 - `scrapfly`
 - `jinareader`

Commercial scrapers require API key, which can be set in the configuration file
(`/config/web.php`).

```php
<?php
require 'examples/boot.php';

use Cognesy\Auxiliary\Web\Webpage;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Schema\Attributes\Instructions;

class Company {
    public string $name = '';
    public string $location = '';
    public string $description = '';
    public int $minProjectBudget = 0;
    public string $companySize = '';
    #[Instructions('Remove any tracking parameters from the URL')]
    public string $websiteUrl = '';
    /** @var string[] */
    public array $clients = [];
}

$companyGen = Webpage::withScraper('none')
    ->get('https://themanifest.com/pl/software-development/laravel/companies?page=1')
    ->cleanup()
    ->select('.directory-providers__list')
    ->selectMany(
        selector: '.provider-card',
        callback: fn($item) => $item->asMarkdown(),
        limit: 3
    );

$companies = [];
echo "Extracting company data from:\n\n";
foreach($companyGen as $companyDiv) {
    echo " > " . substr($companyDiv, 0, 32) . "...\n\n";
    $company = (new StructuredOutput)
        ->using('openai')
        ->with(
            messages: $companyDiv,
            responseModel: Company::class,
            mode: OutputMode::Json
        )->get();
    $companies[] = $company;
    dump($company);
}

assert(count($companies) === 3);
?>
```
