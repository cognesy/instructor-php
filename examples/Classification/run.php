<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__.'../../src/');

///--- code
use Cognesy\Instructor\Instructor;

// Enumeration for single-label text classification.
enum Label : string {
    case SPAM = "spam";
    case NOT_SPAM = "not_spam";
}

// Class for a single class label prediction.
class SinglePrediction {
    public Label $classLabel;
}

// Perform single-label classification on the input text.
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

// Test single-label classification
$prediction = classify("Hello there I'm a Nigerian prince and I want to give you money");
assert($prediction->classLabel == Label::SPAM);
dump($prediction);
