<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__.'../../src/');

use Cognesy\Instructor\Events\LLM\StreamedFunctionCallUpdated;
use Cognesy\Instructor\Instructor;
use Tests\Examples\Extraction\Person;

$instructor = (new Instructor)
    ->wiretap(fn($e)=>dump($e->toConsole()))
    ->onEvent(StreamedFunctionCallUpdated::class, fn($e)=>dump($e->functionCall))
    ->onError(fn($e)=>dump($e->error));

$instructor->respond(
    messages: "His name is Jason, he is 28 years old.",
    responseModel: Person::class,
    options: ['stream' => true],
);
