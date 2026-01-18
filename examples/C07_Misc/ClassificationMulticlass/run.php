<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

/** Potential ticket labels */
enum Label : string {
    case TECH_ISSUE = "tech_issue";
    case BILLING = "billing";
    case SALES = "sales";
    case SPAM = "spam";
    case OTHER = "other";
}

/** Represents analysed ticket data */
class TicketLabels {
    /** @var Label[] */
    public array $labels = [];
}
?>
```
```php
<?php
// Perform single-label classification on the input text.
function multi_classify(string $data) : TicketLabels {
    return (new StructuredOutput)
        ->withMessages("Label following support ticket: {$data}")
        ->withResponseModel(TicketLabels::class)
        ->get();
}
?>
```
```php
<?php
// Test single-label classification
$ticket = "My account is locked and I can't access my billing info.";
$prediction = multi_classify($ticket);

dump($prediction);

assert(in_array(Label::TECH_ISSUE, $prediction->labels));
assert(in_array(Label::BILLING, $prediction->labels));
?>
