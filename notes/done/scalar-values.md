# Support scalar types as response_model


## Problem and ideas

Have universal scalar value adapter with HasSchemaProvider interface
HasSchemaProvider = schema() : Schema, which, if present, will be used to generate schema
Instead of the default schema generation mechanism
This will allow for custom schema generation


## Solution

Ultimately the implemented solution has much nicer DX:

```php
$isAdult = (new Instructor)->respond(
    messages: "Jason is 35 years old",
    responseModel: Scalar::bool('isAdult')
);
```
