# Handle processing errors

`Instructor->onError(callable $callback)` method allows you to receive callback
on any uncaught error, so you can customize handling it, for example logging the
error or using some fallback mechanism in an attempt to recover.

In case Instructor encounters any error that it cannot handle, your callable (if
defined) will be called with an instance of `ErrorRaised` event, which contains
information about the error and request that caused it (among some other properties).

In most cases, after you process the error (e.g. store it in a log via some logger)
the best way to proceed is to rethrow the error.

If you do not rethrow the error and just return some value, Instructor will return
it as a result of response processing. This way you can provide a fallback response,
e.g. with an object with default values.

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Clients\Mistral\MistralClient;
use Cognesy\Instructor\Events\Instructor\ErrorRaised;
use Cognesy\Instructor\Instructor;

class User
{
    public string $name;
    public int $age;
}

// let's mock a logger class - in real life you would use a proper logger, e.g. Monolog
$logger = new class {
    public function error(ErrorRaised $event) {
        // instead of logging we will print the data to the console
        echo $event->asLog()."\n";
        // normally, you'd want to rethrow the error here
    }
};

// we will intentionally create an error by providing a wrong client credentials
$client = new MistralClient('wrong-id', 'wrong-uri');

$user = (new Instructor)
    ->withClient($client)
    ->request(
        messages: "Jason is 28 years old",
        responseModel: User::class,
    )
    ->onError(fn(ErrorRaised $event) => $logger->error($event))
    ->get();

dump($user);
?>
```
