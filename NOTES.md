# NOTES


## Better control over deserialization

We need custom deserializer or easier way of customizing existing one.
Specific need is #[Description] attribute, which should be used to generate description.

Another reason is that we need to handle custom types, such as Money, Date, etc. Some of them may not be supported by Symfony Serializer out of the box.

Need to document how to write and plug in custom field / object deserializer into Instructor.

Custom deserialization strategy is also needed for partial updates, maybe for streaming too.



## Parallel function calling

GPT-4-turbo can handle parallel function calling, which allows to return multiple models in a single API call. We do not yet support it, but Python Instructor does.

The benefit is that you can reduce the number of function calls and get extra "intelligence", for example asking LLM to return a series of "operations" it considers relevant to the input.

Need to test it further to understand how it is different from constructing a more complex model that is composed out of other models (or sequences of other models).

One obvious benefit could be that they are returned separately, can be processed separately and, potentially, acted upon in parallel.

It is doable with composite models via custom deserialization, but would be nice not to be forced to do it manually.



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

### JSON_MODE vs function calling

Add JSON_MODE to the LLM class, so it can handle both modes.

### MISTRAL_MODE

Review Jason's Python code to understand how to handle function calling for Mistral.

### YAML

For models not supporting function calling YAML might be an easier way to get structured outputs.



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



## Async / parallel processing

Identify capabilities of the engine that could be parallelized, so we can speed up processing of the results, esp. for large data sets.





# DONE

## Support scalar types as response_model

Solution 1:
Have universal scalar value adapter with HasSchemaProvider interface
HasSchemaProvider = schema() : Schema, which, if present, will be used to generate schema
Instead of the default schema generation mechanism
This will allow for custom schema generation

### Solution 

Ultimately the implemented solution has much nicer DX:

```php
$isAdult = (new Instructor)->respond(
    messages: "Jason is 35 years old",
    responseModel: Scalar::bool('isAdult')
);
```

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

### Solution

If model implements CanProvideSchema interface it can fully customize schema generation.

It usually requires to also implement custom deserialization logic via CanDeserializeJson interface, so you can control how LLM response JSON is turned into data (and fed into model fields).

You may also need to implement CanTransformResponse to control what you ultimately send back to the caller (e.g. you can return completely different data than the input model).

This is used for the implementation of Scalar class, which is a universal adapter for scalar values.



## Validation

What about validation in such case? we can already have ```validate()``` method in the schema,
Is it enough?

### Solution

Validation can be also customized by implementing CanSelfValidate interface. It allows you to fully control how the data is validated. At the moment it skips built in Symfony Validator logic, so you have to deal with Symfony validation constraints manually.
