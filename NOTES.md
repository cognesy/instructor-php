# NOTES


## Deserialization

We need custom deserializer or easier way of customizing existing one.
Specific need is #[Description] attribute, which should be used to generate description.

Another reason is that we need to handle custom types, such as Money, Date, etc. Some of them may not be supported by Symfony Serializer out of the box.

Need to document how to write and plug in custom field / object deserializer into Instructor.

Custom deserialization strategy is also needed for partial updates, maybe for streaming too.



## Streaming arrays / iterables

Callback approach - provide callback to Instructor, which will be called for each
token received (?). It does not make sense for structured outputs, only if the result
is iterable / array.



## Partial updates

If callback is on, we should be able to provide partial updates to the object + send
notifications about the changes.

To achieve this I need a way to generate a skeleton JSON, send it back to the client and then send changes or new versions of the whole object back to the client.

Question: How to make partial updates and streaming / iterables compatible?



### Denormalization of model structure

It may make sense to denormalize the model - instead of nested structure, split it into a series of individual objects with references. Then generate them in a sequence individually (while providing object context). To be tested if this would result in better or worse inference quality, which is ultimately the most important thing.

Splitting into objects would also allow for partial updates.

Further - splitting objects to properties and generating them individually would make streaming partial updates easier.

To be tested: maybe it could work for less capable models with no function calling.

##### Model now

Conceptually, the model is a tree of objects, which is generated in a single pass.

```
Issues[] {
    Issue {
        title: string
        description: string
        type: IssueType { 
            value: [technical, commercial, collaboration, other]
        }
        related_quotes: Quote[] {
            Quote {
                text: string
                source: string
                date: ?date
            }
        }
    }
}
```

##### Flattened model

The alternative is treating the model as a series of items - each item is a property of an object, following prescribed structure.

```
issues.issue[0].title
issues.issue[0].description
issues.issue[0].type
issues.issue[0].related_quotes
issues.issue[0].related_quotes.quote[0].text
issues.issue[0].related_quotes.quote[0].source
issues.issue[0].related_quotes.quote[0].date
issues.issue[0].related_quotes.quote[1].text
issues.issue[0].related_quotes.quote[1].source
issues.issue[0].related_quotes.quote[1].date
...
issues.issue[1].title
issues.issue[1].description
issues.issue[1].type
issues.issue[1].related_quotes
issues.issue[1].related_quotes.quote[2].text
issues.issue[1].related_quotes.quote[2].source
issues.issue[1].related_quotes.quote[2].date
issues.issue[1].related_quotes.quote[3].text
issues.issue[1].related_quotes.quote[3].source
issues.issue[1].related_quotes.quote[3].date
...
```



## Other LLMs

1) Via custom BASE_URI - via existing OpenAI client
2) Custom LLM classes.
LLM class is the one that needs to handle all model / API specific stuff (e.g. function calling - vide: Claude's FC XML "API", streaming, modes, etc.).



## Observability

Requirements and solution - to be analyzed



## Metadata

Need a better way to handle model metadata. Currently, we rely on 2 building blocks:

 - PHPDocs
 - Type information
 - Attributes (limited - validation)

Redesigned engine does not offer an easy way to handle custom Attributes.

Not sure if Attributes are the ultimate answer, as they are static and cannot be easily manipulated at runtime.

Pydantic approach is to take over the whole model definition via Field() calls, but PHP does not allow us to do something similar, at least in a clean way.

```php
class User {
    public string $name;
    public string $email = new Field(description: 'Email address'); // This is not possible in PHP
}
```


## Examples

We need a way to inject examples in a more structured way than as a text in PHPDocs.

 - It mixes instructions with examples.
 - It's not easy to extract examples from PHPDocs and manage them separately (e.g. using larger external source of examples)
 - PHPDocs cannot be easily manipulated - it's not easy to inject / replace examples in PHPDocs.

### Questions

Do examples need to be provided at a class level or at a property level?



## Optimization of instructions

Quality is highly dependent on the . We need a better way to generate instructions and examples (e.g. similar to DSPy).



## Caching schema

It may not be worth it purely for performance reasons, but it would be useful for debugging or schema optimization (DSPy like).

Schema could be saved in version controlled, versioned JSON files and loaded from there. In development mode it would be read from JSON file, unless class file is newer than schema file.



## Handling useful, common data types

Currently, there is no special treatment for common data types, such as:

 - Date
 - Time
 - DateTime
 - Period
 - Duration
 - Money
 - Currency

There are no tests around those types of data, nor support for parsing that Pydantic has.



## Public vs private/protected fields

Document and write tests around the behavior of public vs private/protected fields.



# DONE

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

