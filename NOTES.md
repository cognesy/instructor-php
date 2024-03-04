# NOTES

## Support scalar types as response_model

Solution 1:
Have universal scalar value adapter with HasSchemaProvider interface
HasSchemaProvider = schema() : Schema, which, if present, will be used to generate schema
Instead of the default schema generation mechanism
This will allow for custom schema generation

## Custom schema generation - not based on class reflection & PHPDoc

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

## Validation

What about validation in such case? we can already have ```validate()``` method in the schema,
Is it enough?

## Deserialization

We also need custom deserializer or easier way of customizing existing one.
Specific need is #[Description] attribute, which should be used to generate description.

## Streaming arrays / iterables

Callback approach - provide callback to Instructor, which will be called for each
token received (?). It does not make sense for structured outputs, only if the result
is iterable / array.

## Partial updates

If callback is on, we should be able to provide partial updates to the object + send
notifications about the changes.

## Observability

Need and solution to be analyzed

## Other LLMs

Either via custom BASE_URIs - via existing OpenAI client or custom LLM classes.
LLM class is the one that needs to handle all model / API specific stuff (e.g. streaming,
modes, etc.).

## Caching schema

It may not be worth it purely for performance reasons, but it may be useful for debugging or schema optimization (DSPy like).

Schema could be saved in version controlled, versioned JSON files and loaded from there. In development mode it would be read from JSON file, unless class file is newer than schema file.