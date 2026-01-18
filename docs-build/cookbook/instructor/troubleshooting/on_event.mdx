<?php
require 'examples/boot.php';

use Cognesy\Events\Event;
use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Http\Events\HttpResponseReceived;
use Cognesy\Instructor\StructuredOutput;

class User
{
    public string $name;
    public int $age;
}

// let's mock a logger class - in real life you would use a proper logger, e.g. Monolog
$logger = new class {
    public function log(Event $event) {
        // we're using a predefined asLog() method to get the event data,
        // but you can access the event properties directly and customize the output
        echo $event->asLog()."\n";
    }
};

$user = (new StructuredOutput)
    ->onEvent(HttpRequestSent::class, fn($event) => $logger->log($event))
    ->onEvent(HttpResponseReceived::class, fn($event) => $logger->log($event))
    ->with(
        messages: "Jason is 28 years old",
        responseModel: User::class,
    )
    ->get();

dump($user);
?>
