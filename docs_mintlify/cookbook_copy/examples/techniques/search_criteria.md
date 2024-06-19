# Expanding Search Queries

In this example, we will demonstrate how to leverage the enums and typed arrays
to segment a complex search prompt into multiple, better structured queries that
can be executed separately against specialized APIs or search engines.

!!! tips "Motivation"

Extracting a list of tasks from text is a common use case for leveraging language
models. This pattern can be applied to various applications, such as virtual
assistants like Siri or Alexa, where understanding user intent and breaking down
requests into actionable tasks is crucial. In this example, we will demonstrate
how to use Instructor to segment search queries, so you can execute them separately
against specialized APIs or search engines.

## Structure of the Data

The `SearchQuery` class is a PHP class that defines the structure of an individual
search query. It has three fields: `title`, `query`, and `type`. The `title` field
is the title of the request, the `query` field is the query to search for relevant
content, and the `type` field is the type of search. The `execute` method is used
to execute the search query.

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Instructor;

enum SearchType : string {
    case TEXT = "text";
    case IMAGE = "image";
    case VIDEO = "video";
}

class Search
{
    /** @var SearchQuery[] */
    public array $queries = [];
}

class SearchQuery
{
    public string $title;
    /**  Rewrite query for a search engine */
    public string $query;
    /** Type of search - image, video or text */
    public SearchType $type;

    public function execute() {
        // ... write actual search code here
        print("Searching for `{$this->title}` with query `{$this->query}` using `{$this->type->value}`\n");
    }
}
?>
```

## Segmenting the Search Prompt

The `segment` function takes a string `data` and segments it into multiple search queries.
It uses the `Instructor::respond` method to send a prompt and extract the data into
the target object. The `responseModel` parameter specifies `Search::class` as the model
to use for extraction.

```php
<?php
function segment(string $data) : Search {
    return (new Instructor)->respond(
        messages: [[
            "role" => "user",
            "content" => "Consider the data below: '\n$data' and segment it into multiple search queries",
        ]],
        responseModel: Search::class,
    );
}

$search = segment("Find a picture of a cat and a video of a dog");
foreach ($search->queries as $query) {
    $query->execute();
}
// Results:
// Searching with query `picture of a cat` using `image`
// Searching with query `video of a dog` using `video`

assert(count($search->queries) === 2);
?>
```
