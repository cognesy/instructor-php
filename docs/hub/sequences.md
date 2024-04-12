# Extracting sequences of objects

Sequences are a special type of response model that can be used to represent
a list of objects.

It is usually more convenient not create a dedicated class with a single array
property just to handle a list of objects of a given class.

Additional, unique feature of sequences is that they can be streamed per each
completed item in a sequence, rather than on any property update.

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__.'../../src/');

use Cognesy\Instructor\Extras\Sequences\Sequence;
use Cognesy\Instructor\Instructor;

class Person
{
    public string $name;
    public int $age;
}

$text = <<<TEXT
    Jason is 25 years old. Jane is 18 yo. John is 30 years old. Anna is 2 years younger than him.
    TEXT;

$list = (new Instructor)
    ->request(
        messages: [['role' => 'user', 'content' => $text]],
        responseModel: Sequence::of(Person::class),
        options: ['stream' => true]
    )
    ->onSequenceUpdate(fn($sequence) => dump($sequence->last()))
    ->get();

dump(count($list));
assert(count($list) === 4);
?>
```
