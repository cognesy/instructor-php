---
title: Classification
description: 'Use enums and response models to turn text into categories with LLM-powered classification.'
---

Text classification is one of the most common tasks in natural language processing. Whether you are
detecting spam, categorizing support tickets, or routing content, Instructor makes it straightforward
by combining PHP enums with response models.

## Single-Label Classification

Define a backed enum for the possible labels and a response model that holds the prediction.

```php
use Cognesy\Instructor\StructuredOutput;

enum Label: string {
    case SPAM = 'spam';
    case NOT_SPAM = 'not_spam';
}

final class SinglePrediction {
    public Label $classLabel;
}

$prediction = (new StructuredOutput)
    ->with(
        messages: 'Classify the following text: Hello there, I\'m a Nigerian prince and I want to give you money.',
        responseModel: SinglePrediction::class,
    )
    ->get();

assert($prediction->classLabel === Label::SPAM);
// @doctest id="800d"
```

The model sees the enum values in the generated JSON Schema and picks the most appropriate one.
Keep the enum small and descriptive -- fewer choices typically produce more accurate results.

## Multi-Label Classification

When a single input can belong to several categories at once, use a typed array of enums.

```php
use Cognesy\Instructor\StructuredOutput;

enum TicketCategory: string {
    case TECH_ISSUE = 'tech_issue';
    case BILLING = 'billing';
    case SALES = 'sales';
    case SPAM = 'spam';
    case OTHER = 'other';
}

final class Ticket {
    /** @var TicketCategory[] */
    public array $labels = [];
}

$ticket = (new StructuredOutput)
    ->with(
        messages: 'Classify this support ticket: My account is locked and I can\'t access my billing info.',
        responseModel: Ticket::class,
    )
    ->get();

assert(in_array(TicketCategory::TECH_ISSUE, $ticket->labels));
assert(in_array(TicketCategory::BILLING, $ticket->labels));
// @doctest id="bb88"
```

## Tips for Better Classification

### Always include a fallback option

Adding an `OTHER` or `UNKNOWN` case gives the model an escape hatch when the input does not fit
neatly into your predefined categories. Without it, the model is forced to pick an incorrect label.

### Use descriptive enum values

The string values of your enum cases are included in the JSON Schema that the model receives.
Descriptive values like `tech_issue` communicate intent better than opaque codes like `T1`.

### Add PHPDoc descriptions

You can annotate enum cases or the response model properties with PHPDoc comments to give the
model additional guidance.

```php
final class SentimentResult {
    /** The overall sentiment of the input text. Choose the single best match. */
    public Sentiment $sentiment;
    /** Brief explanation of why this sentiment was chosen. */
    public string $reasoning;
}
// @doctest id="d2ff"
```

### Keep the schema narrow

Classification quality improves when the model has fewer valid shapes to choose from. If your
response model only needs a label, do not add extra fields. If you need confidence scores or
explanations, add them as separate properties so the model can fill them independently.

### Validate with custom rules

For multi-label classification, you may want to enforce constraints like "at least one label" or
"no more than three labels". Use the `ValidationMixin` trait or implement `CanValidateSelf` to
add custom validation logic that Instructor will enforce automatically.

```php
use Cognesy\Instructor\Validation\Traits\ValidationMixin;
use Cognesy\Instructor\Validation\ValidationResult;

final class Ticket {
    use ValidationMixin;

    /** @var TicketCategory[] Must contain at least one label. */
    public array $labels = [];

    public function validate(): ValidationResult {
        if (empty($this->labels)) {
            return ValidationResult::fieldError(
                field: 'labels',
                value: $this->labels,
                message: 'At least one label is required.',
            );
        }
        return ValidationResult::valid();
    }
}
// @doctest id="b020"
```
