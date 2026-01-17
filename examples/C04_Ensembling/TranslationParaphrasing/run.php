---
title: 'Use Translation for Paraphrasing'
docname: 'translation_paraphrasing'
---

## Overview

Back-translation can produce diverse paraphrases: translate to another language and back to English, encouraging varied phrasing.

## Example

```php
\<\?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

class TranslatedPrompt { public string $translation; }

class Paraphraser {
    public function translate(string $prompt, string $from, string $to) : TranslatedPrompt {
        $system = "You are an expert translation assistant. Translate from {$from} to {$to}. Paraphrase and use synonyms where possible.";
        return (new StructuredOutput)->with(
            messages: [ ['role'=>'system','content'=>$system], ['role'=>'user','content'=>"Prompt: {$prompt}"] ],
            responseModel: TranslatedPrompt::class,
        )->get();
    }

    public function backTranslate(string $prompt, string $lang) : string {
        $step1 = $this->translate($prompt, 'english', $lang)->translation;
        $step2 = $this->translate($step1, $lang, 'english')->translation;
        return $step2;
    }

    public function generate(string $prompt, array $languages, int $permutations = 5) : array {
        $out = [];
        for ($i = 0; $i < $permutations; $i++) {
            $lang = $languages[$i % max(1, count($languages))] ?? 'spanish';
            $out[] = $this->backTranslate($prompt, $lang);
        }
        return $out;
    }
}

$prompt = 'Explain how photosynthesis works for a 10-year-old.';
$languages = ['spanish','french','german'];
$variants = (new Paraphraser)->generate($prompt, $languages, permutations: 3);
dump($variants);
?>
```

### References

1) Prompt paraphrasing approaches
