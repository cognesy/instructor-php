<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Schema\Factories\SchemaFactory;

class City {
    public string $name;
    public int $population;
    public int $founded;
}

$schema = (new SchemaFactory)->schema(City::class);

$city = (new StructuredOutput)->with(
    messages: "What is capital of France",
    responseModel: $schema,
)->get();

dump($city);

assert(gettype($city) === 'object');
assert(get_class($city) === 'City');
assert($city->name === 'Paris');
assert(is_int($city->population));
assert(is_int($city->founded));

?>
