# Extracting Scalar Values

Sometimes we just want to get quick results without defining a class for the response model, especially if we're trying to get a straight, simple answer in a form of string, integer, boolean or float. Instructor provides a simplified API for such cases.

```php
use Cognesy\Instructor;

$value = (new Instructor)->respond(
    messages: "His name is Jason, he is 28 years old.",
    responseModel: Scalar::integer('age'),
);

var_dump($value);
// int(28)
```

In this example, we're extracting a single integer value from the text. You can also use `Scalar::string()`, `Scalar::boolean()` and `Scalar::float()` to extract other types of values.

Additionally, you can use Scalar adapter to extract one of the provided options.

```php
use Cognesy\Instructor;

$value = (new Instructor)->respond(
    messages: "His name is Jason, he currently plays Doom Eternal.",
    responseModel: Scalar::select(
        name: 'activityType',
        options: ['work', 'entertainment', 'sport', 'other']
    ),
);

var_dump($value);
// string(4) "entertainment"
```

NOTE: Currently Scalar::select() always returns strings and its ```options``` parameter only accepts string values.
