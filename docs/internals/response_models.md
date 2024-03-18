# Response Models

Instructor is able to process several types of input provided as response model, giving you more flexibility on how you interact with the library.

The signature of `respond()` method of Instructor states the `responseModel` can be either string, object or array.

## Handling string $responseModel value

If `string` value is provided, it is used as a name of the class of the response model.

Instructor checks if the class exists and analyzes the class & properties type information & doc comments to generate a schema needed to specify LLM response constraints.

The best way to provide the name of the response model class is to use `NameOfTheClass::class`, making it easy for IDE to check the type, handle refactorings, etc.


## Handling object $responseModel value

If `object` value is provided, it is considered an instance of the response model. Instructor checks the class of the instance, then analyzes it and its property type data to specify LLM response constraints.


## Handling array $responseModel value

If `array` value is provided, it is considered a raw JSON Schema, therefore allowing Instructor to use it directly in LLM requests (after wrapping in appropriate context - e.g. function call).

Instructor requires information on the class of each nested object in your JSON Schema, so it can correctly deserialize the data into appropriate type.

This information is available to Instructor when you are passing $responseModel as a class name or an instance, but it is missing from raw JSON Schema. Lack of the information on target class makes it impossible for Instructor to deserialize the data into appropriate, expected type.

Current design uses JSON Schema `$comment` field on property to overcome this information gap. Instructor expects developer to use `$comment` field to provide fully qualified name of the target class to be used to deserialize property data of object or enum type.


