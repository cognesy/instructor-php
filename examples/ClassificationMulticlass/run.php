<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__.'../../src/');

///--- code
use Cognesy\Instructor\Instructor;

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

// Perform single-label classification on the input text.
function multi_classify(string $data) : TicketLabels {
    return (new Instructor())->respond(
        messages: [[
            "role" => "user",
            "content" => "Label following support ticket: {$data}",
        ]],
        responseModel: TicketLabels::class,
    );
}

// Test single-label classification
$ticket = "My account is locked and I can't access my billing info.";
$prediction = multi_classify($ticket);

assert(in_array(Label::TECH_ISSUE, $prediction->labels));
assert(in_array(Label::BILLING, $prediction->labels));
dump($prediction);
