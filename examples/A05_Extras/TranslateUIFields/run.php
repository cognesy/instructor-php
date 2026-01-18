<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Validation\Validators\SymfonyValidator;

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

$transformedModel = (new StructuredOutput)
    ->withInput($sourceModel)
    ->withResponseClass(get_class($sourceModel))
    ->withPrompt('Translate the headline and text fields to German. Keep HTML tags unchanged. Keep the url field unchanged.')
    ->withModel('gpt-4o-mini')
    ->withMaxRetries(2)
    ->withOptions(['temperature' => 0])
    ->withValidators(SymfonyValidator::class)
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
