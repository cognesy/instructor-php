## Extracting Scalar Values

Sometimes we just want to get quick results without defining a class for the response model, especially if we're trying to get a straight, simple answer in a form of string, integer, boolean or float. Instructor provides a simplified API for such cases.

```php
<?php
use Cognesy\Instructor\StructuredOutput;

$value = (new StructuredOutput)
    ->with(
        messages: "His name is Jason, he is 28 years old.",
        responseModel: Scalar::integer('age'),
    )
    ->get();

var_dump($value);
// int(28)
```

In this example, we're extracting a single integer value from the text. You can also use `Scalar::string()`, `Scalar::boolean()` and `Scalar::float()` to extract other types of values.

Additionally, you can use Scalar adapter to extract enums via `Scalar::enum()`.


## Examples

### String result

```php
<?php
$value = (new StructuredOutput)
    ->with(
        messages: "His name is Jason, he is 28 years old.",
        responseModel: Scalar::string(name: 'firstName'),
    )
    ->get();
// expect($value)->toBeString();
// expect($value)->toBe("Jason");
```

### Integer result

```php
<?php
$value = (new StructuredOutput)
    ->with(
        messages: "His name is Jason, he is 28 years old.",
        responseModel: Scalar::integer('age'),
    )
    ->get();
// expect($value)->toBeInt();
// expect($value)->toBe(28);
```

### Boolean result

```php
<?php
$value = (new StructuredOutput)
    ->with(
        messages: "His name is Jason, he is 28 years old.",
        responseModel: Scalar::boolean(name: 'isAdult'),
    )
    ->get();
// expect($value)->toBeBool();
// expect($value)->toBe(true);
```

### Float result

```php
<?php
$value = (new StructuredOutput)
    ->with(
        messages: "His name is Jason, he is 28 years old and his 100m sprint record is 11.6 seconds.",
        responseModel: Scalar::float(name: 'recordTime'),
    )
    ->get();
// expect($value)->toBeFloat();
// expect($value)->toBe(11.6);
```

### Enum result / select one of the options

```php
<?php
$text = "His name is Jason, he is 28 years old and he lives in Germany.";
$value = (new StructuredOutput)
    ->with(
        messages: [
            ['role' => 'system', 'content' => $text],
            ['role' => 'user', 'content' => 'What is Jason\'s citizenship?'],
        ],
        responseModel: Scalar::enum(CitizenshipGroup::class, name: 'citizenshipGroup'),
    )->get();
// expect($value)->toBeString();
// expect($value)->toBe('other');
```
