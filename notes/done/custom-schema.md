## Custom schema generation - not based on class reflection & PHPDoc

### Problem and ideas

Model classes could implement HasSchemaProvider interface, which would allow for custom schema generation - rendering logic would skip reflection and use the provided schema instead.

SchemaProvider could be a trait, which would allow for easy implementation.

Example SchemaProvider:
class SchemaProvider {
public function schema(): Schema {
return new Schema([
'type' => 'object',
'properties' => [
'id' => ['type' => 'integer', 'description' => 'Description'],
'name' => ['type' => 'string', 'description' => 'Description'],
],
'required' => ['id', 'name'],
]);
}
}

### Solution

If model implements CanProvideSchema interface it can fully customize schema generation.

It usually requires to also implement custom deserialization logic via CanDeserializeJson interface, so you can control how LLM response JSON is turned into data (and fed into model fields).

You may also need to implement CanTransformResponse to control what you ultimately send back to the caller (e.g. you can return completely different data than the input model).

This is used for the implementation of Scalar class, which is a universal adapter for scalar values.
