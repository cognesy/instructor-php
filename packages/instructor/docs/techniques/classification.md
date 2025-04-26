## Text Classification using LLM

This tutorial showcases how to implement text classification tasks—specifically, single-label and multi-label classifications—using LLM (via OpenAI API), PHP's **`enums`** and classes.

!!! tips "Motivation"

    Text classification is a common problem in many NLP applications, such as spam detection or support ticket categorization. The goal is to provide a systematic way to handle these cases using language models in combination with PHP data structures.




## Single-Label Classification

### Defining the Structures

For single-label classification, we first define an **`enum`** for possible labels and a PHP class for the output.

```php
<?php
// Enumeration for single-label text classification. 
enum Label : string {
    case SPAM = "spam";
    case NOT_SPAM = "not_spam";
}

// Class for a single class label prediction. 
class SinglePrediction {
    public Label $classLabel;
}
```


### Classifying Text

The function **`classify`** will perform the single-label classification.

```php
<?php
use Cognesy\Instructor\Instructor;

/**
 * Perform single-label classification on the input text. 
 */
function classify(string $data) : SinglePrediction {
    return (new Instructor())->respond(
        messages: [[
            "role" => "user",
            "content" => "Classify the following text: $data",
        ]],
        responseModel: SinglePrediction::class,
        model: "gpt-3.5-turbo-0613",
    );
}
```


### Testing and Evaluation

Let's run an example to see if it correctly identifies a spam message.

```php
<?php

// Test single-label classification
$prediction = classify("Hello there I'm a Nigerian prince and I want to give you money");
assert($prediction->classLabel == Label::SPAM);
```



## Multi-Label Classification

### Defining the Structures

For multi-label classification, we introduce a new enum class and a different PHP class to handle multiple labels.

```php
<?php
/** Potential ticket labels */
enum Label : string {
    case TECH_ISSUE = "tech_issue";
    case BILLING = "billing";
    case SALES = "sales";
    case SPAM = "spam";
    case OTHER = "other";
}

/** Represents analysed ticket data */
class Ticket {
    /** @var Label[] */
    public array $ticketLabels = [];
}
```


### Classifying Text

The function **`multi_classify`** executes multi-label classification using LLM.

```php
<?php
use Cognesy\Instructor\Instructor;

// Perform single-label classification on the input text.
function multi_classify(string $data) : Ticket {
    return (new Instructor())->respond(
        messages: [[
            "role" => "user",
            "content" => "Classify following support ticket: {$data}",
        ]],
        responseModel: Ticket::class,
        model: "gpt-3.5-turbo-0613",
    );
}
```

### Testing and Evaluation

Finally, we test the multi-label classification function using a sample support ticket.

```php
<?php
// Test single-label classification
$ticket = "My account is locked and I can't access my billing info.";
$prediction = multi_classify($ticket);

assert(in_array(Label::TECH_ISSUE, $prediction->classLabels));
assert(in_array(Label::BILLING, $prediction->classLabels));
```
