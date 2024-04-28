# Extracting scalar values

Sometimes we just want to get quick results without defining a class for
the response model, especially if we're trying to get a straight, simple
answer in a form of string, integer, boolean or float. Instructor provides
a simplified API for such cases.

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Extras\Scalars\Scalar;
use Cognesy\Instructor\Instructor;

enum CitizenshipGroup : string {
    case US = "us";
    case Canada = "uk";
    case Other = "other";
}

$text = "His name is Jason, he is 28 years old and he lives in Germany.";
$value = (new Instructor)->respond(
    messages: [
        ['role' => 'system', 'content' => $text],
        ['role' => 'user', 'content' => 'What is Jason\'s citizenship?'],
    ],
    responseModel: Scalar::enum(CitizenshipGroup::class, name: 'citizenshipGroup'),
);

dump($value);

assert($value instanceof CitizenshipGroup);
expect($value == CitizenshipGroup::Other);
?>
```

