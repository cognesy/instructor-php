<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

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
```php
<?php
function segment(string $data) : Search {
    return (new StructuredOutput)
        //->withDebugPreset('on')
        ->withMessages("Consider the data below: '\n$data' and segment it into multiple search queries")
        ->withResponseClass(Search::class)
        ->get();
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
