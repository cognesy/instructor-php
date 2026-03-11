---
title: Search
description: 'Turn natural-language requests into structured search queries.'
---

A common use case for structured output is converting free-form text into one or more search
queries that your application can execute against an API, database, or search engine. Instructor
handles the parsing so you can focus on the domain logic.

## Defining the Query Structure

Start by modeling what a single search query looks like. Use an enum when the query type comes
from a fixed set, and add a PHPDoc comment to guide the model on how to rewrite the user's
request into an effective query.

```php
enum SearchType: string {
    case TEXT = 'text';
    case IMAGE = 'image';
    case VIDEO = 'video';
}

final class SearchQuery {
    public string $title;
    /** Rewrite the user's intent as a concise search-engine query. */
    public string $query;
    /** The type of content to search for. */
    public SearchType $type;

    public function execute(): void {
        // dispatch to the appropriate search backend
    }
}
// @doctest id="6600"
```

## Segmenting Into Multiple Queries

Wrap the query class in a container that holds an array. This lets the model split a complex,
multi-part request into separate, actionable queries.

```php
final class Search {
    /** @var SearchQuery[] */
    public array $queries = [];
}
// @doctest id="dd28"
```

## Putting It Together

Pass the user's request as a message and let Instructor decompose it.

```php
use Cognesy\Instructor\StructuredOutput;

function segment(string $input): Search {
    return (new StructuredOutput)
        ->with(
            messages: "Consider the data below:\n'{$input}'\nand segment it into multiple search queries.",
            responseModel: Search::class,
        )
        ->get();
}

$results = segment('Find a picture of a cat and a video of a dog');

foreach ($results->queries as $query) {
    $query->execute();
}
// @doctest id="7374"
```

## Design Tips

- **Match the response model to your consumer.** The shape of `SearchQuery` should mirror how your
  search backend expects input. If your API needs filters, add typed filter properties rather than
  encoding everything in a single query string.
- **Use `Sequence` for streamed results.** When you want to process queries as they arrive rather
  than waiting for the full response, use Instructor's `Sequence` wrapper with streaming enabled.
- **Keep the schema focused.** A small, well-typed class produces better results than a large,
  generic one. If text and image searches require different parameters, consider separate classes
  and a discriminated union pattern.
