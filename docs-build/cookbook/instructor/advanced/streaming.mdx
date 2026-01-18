<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\Cli\Console;

class UserRole
{
    /** Monotonically increasing identifier */
    public int $id;
    public string $title = '';
}

class UserDetail
{
    public int $age = 0;
    public string $name = '';
    public string $location = '';
    /** @var UserRole[] */
    public array $roles = [];
    /** @var string[] */
    public array $hobbies = [];
}

// This function will be called every time a new token is received
function partialUpdate($partial) {
    // Clear the screen and move the cursor to the top
    Console::clearScreen();

    // Display the partial object
    dump($partial);

    // Wait a bit before clearing the screen to make partial changes slower.
    // Don't use this in your application :)
    // usleep(250000);
}
?>
```
```php
<?php
$text = <<<TEXT
    Jason is 25 years old, he is an engineer and tech lead. He lives in
    San Francisco. He likes to play soccer and climb mountains.
    TEXT;

$stream = (new StructuredOutput)
    //->wiretap(fn(Event $e) => $e->print())
    ->withMessages($text)
    ->withResponseClass(UserDetail::class)
    ->withStreaming()
    ->withOutputMode(OutputMode::Json)
    ->stream();

foreach ($stream->partials() as $partial) {
    partialUpdate($partial);
}

$user = $stream->lastUpdate();

assert($user->name === 'Jason');
assert($user->age === 25);
?>
