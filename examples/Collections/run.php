# Collections


```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__.'../../src/');

use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Utils\Collection;

class Fact
{
    public string $key;
    public string $value;
}

$text = <<<TEXT
    Jason is 25 years old. He is a programmer. He has a car. He lives
    in a small house in Alamo. He likes to play guitar.
TEXT;

$list = (new Instructor)->respond(
    messages: [['role' => 'user', 'content' => $text]],
    responseModel: Collection::of(Fact::class)
);

dump($list);
?>
```
