---
title: 'Translating UI text fields'
docname: 'translate_ui_fields'
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

use Cognesy\Instructor\Extras\Scalar\Scalar;
use Cognesy\Instructor\Features\Validation\Contracts\CanValidateObject;
use Cognesy\Instructor\Features\Validation\ValidationResult;
use Cognesy\Instructor\StructuredOutput;

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

$validator = new class implements CanValidateObject {
    public function validate(object $dataObject): ValidationResult {
        $isInGerman = (new StructuredOutput)->create(
            input: $dataObject,
            responseModel: Scalar::boolean(),
            prompt: 'Are all content fields translated to German?',
        )->getBoolean();
        return match($isInGerman) {
            true => ValidationResult::valid(),
            default => ValidationResult::invalid(['All input text fields have to be translated to German. Keep HTML tags unchanged.']),
        };
    }
};

$transformedModel = (new StructuredOutput)
    ->wiretap(fn($e)=>$e->print())
    ->addValidator($validator)
    ->create(
        input: $sourceModel,
        responseModel: get_class($sourceModel),
        prompt: 'Translate all text fields to German. Keep HTML tags unchanged.',
        maxRetries: 2,
        options: ['temperature' => 0],
    )->get();

dump($transformedModel);

assert((
    str_contains($transformedModel->headline, 'Überschrift')
    || str_contains($transformedModel->headline, 'Schlagzeile')
) === true);
assert(str_contains($transformedModel->text, 'Inhalt') === true);
assert(str_contains($transformedModel->text, '<p>') === true);
assert(str_contains(str_replace('\/', '/', $transformedModel->url), 'https://translation.com/') === true);
?>
```
