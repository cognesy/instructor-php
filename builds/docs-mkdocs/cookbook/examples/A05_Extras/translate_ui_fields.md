---
title: 'Translating UI text fields'
docname: 'translate_ui_fields'
id: '8cdb'
tags:
  - 'extras'
  - 'translation'
  - 'ui-localization'
---
## Overview

You can use Instructor to translate text fields in your UI. We can instruct the model to
translate only the text fields from one language to another, but leave the other fields,
like emails or URLs, unchanged.

This example demonstrates how to translate text fields from English to German using
structure-to-structure processing with LLM.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;

class TextElementModel
{
    public function __construct(
        public string $headline = '',
        public string $text = '',
        public string $url = 'https://translation.com/'
    ) {}
}

$sourceModel = new TextElementModel(
    headline: 'This is my headline',
    text: '<p>This is some WYSIWYG HTML content.</p>'
);

$transformedModel = new StructuredOutput(
        StructuredOutputRuntime::fromProvider(LLMProvider::using('openai'))
            ->withMaxRetries(2)
            ->withValidator(new \Cognesy\Instructor\Validation\Validators\SymfonyValidator())
    )
    ->withInput($sourceModel)
    ->withResponseClass(get_class($sourceModel))
    ->withPrompt('Translate the headline and text fields to German. Keep HTML tags unchanged. Keep the url field unchanged.')
    ->withModel('gpt-4o-mini')
    ->withOptions(['temperature' => 0])
    ->get();

print_r($transformedModel);

$hasGermanHeadline = str_contains($transformedModel->headline, 'Überschrift')
    || str_contains($transformedModel->headline, 'Schlagzeile');
if (!$hasGermanHeadline) {
    echo "ERROR: Headline not translated to German\n";
    exit(1);
}
if (!str_contains($transformedModel->text, '<p>')) {
    echo "ERROR: HTML tags not preserved in text\n";
    exit(1);
}
$url = str_replace('\/', '/', $transformedModel->url);
if (!str_contains($url, 'https://translation.com/')) {
    echo "ERROR: URL was modified during translation\n";
    exit(1);
}
?>
```
