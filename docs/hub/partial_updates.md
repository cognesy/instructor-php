# Partial updates

Instructor can process LLM's streamed responses to provide partial updates that you
can use to update the model with new data as the response is being generated. You can
use it to improve user experience by updating the UI with partial data before the full
response is received.

```php
<?php
sleep(3);
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

$text = <<<TEXT
    Jason is 25 years old, he is an engineer and tech lead. He lives in
    San Francisco. He likes to play soccer and climb mountains.
TEXT;

$user = (new Instructor)->request(
    messages: $text,
    responseModel: UserDetail::class,
)->onPartialUpdate(partialUpdate(...))->get();

function partialUpdate($partial) {
    echo chr(27).chr(91).'H'.chr(27).chr(91).'J';
    dump($partial);
    usleep(100000);
}

assert($user->roles[0]->title == 'engineer');
assert($user->roles[1]->title == 'tech lead');
assert($user->location == 'San Francisco');
assert($user->hobbies[0] == 'soccer');
assert($user->hobbies[1] == 'climb mountains');
assert($user->age == 25);
assert($user->name == 'Jason');
?>
```
