# Streaming partial updates during inference

Instructor can process LLM's streamed responses to provide partial updates that you
can use to update the model with new data as the response is being generated. You can
use it to improve user experience by updating the UI with partial data before the full
response is received.

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Instructor;

class UserRole
{
    /** Monotonically increasing identifier */
    public int $id;
    public string $title = '';
}

class UserDetail
{
    public int $age;
    public string $name;
    public string $location;
    /** @var UserRole[] */
    public array $roles;
    /** @var string[] */
    public array $hobbies;
}

// This function will be called every time a new token is received
function partialUpdate($partial) {
    // Clear the screen and move the cursor to the top
    echo chr(27).chr(91).'H'.chr(27).chr(91).'J';

    // Print explanation
    echo "Waiting 250ms on every update received to make changes easier to observe...\n";

    // Display the partial object
    dump($partial);

    // Wait a bit before clearing the screen to make partial changes slower.
    // Don't use this in your application :)
    usleep(250000);
}
?>
```
Now we can use this data model to extract arbitrary properties from a text message.
As the tokens are streamed from LLM API, the `partialUpdate` function will be called
with partially updated object of type `UserDetail` that you can use, usually to update
the UI.

```php
<?php
$text = <<<TEXT
    Jason is 25 years old, he is an engineer and tech lead. He lives in
    San Francisco. He likes to play soccer and climb mountains.
    TEXT;

$user = (new Instructor)->request(
    messages: $text,
    responseModel: UserDetail::class,
    options: ['stream' => true]
)->onPartialUpdate(partialUpdate(...))->get();

echo "All tokens received, fully completed object available in `\$user` variable.\n";
echo '$user = '."\n";
dump($user);

assert(!empty($user->roles));
assert(!empty($user->hobbies));
assert($user->location == 'San Francisco');
assert($user->age == 25);
assert($user->name == 'Jason');
?>
```
