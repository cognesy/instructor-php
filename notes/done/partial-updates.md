## Partial updates

> Priority: should have

If callback is on, we should be able to provide partial updates to the object + send
notifications about the changes.

To achieve this I need a way to generate a skeleton JSON, send it back to the client and then send changes or new versions of the whole object back to the client.

Question: How to make partial updates and streaming / iterables compatible?

### Using events

Library currently dispatches events on every chunk received from LLM in streaming mode and on every partial update of the response model.

Questions:
1. How does the client receive partially updated data model? What's the API? Do we want separate endpoint for regular `response()` method vs partial / streamed one?
2. How do we distinguish between partial updates and collection streaming (getting a stream of instances of the same model)?
3. Can the streamed collections models be partially updated?
4. Is there a need for a separate event on property completed, not just updated?


### IDEA: Denormalization of model structure

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
