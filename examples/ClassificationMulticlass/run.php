<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__.'../../src/');

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
class Ticket {
    /** @var Label[] */
    public array $ticketLabels = [];
}

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

// Test single-label classification
$ticket = "My account is locked and I can't access my billing info.";
$prediction = multi_classify($ticket);

assert(in_array(Label::TECH_ISSUE, $prediction->classLabels));
assert(in_array(Label::BILLING, $prediction->classLabels));
dump($prediction);
