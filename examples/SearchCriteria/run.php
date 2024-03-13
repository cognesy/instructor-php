<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

///--- code
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

function segment(string $data) : Search {
    return (new Instructor)->respond(
        messages: [[
            "role" => "user",
            "content" => "Consider the data below: '\n$data' and segment it into multiple search queries",
        ]],
        responseModel: Search::class,
    );
}

foreach (segment("Search for a picture of a cat and a video of a dog")->queries as $query) {
    $query->execute();
    // dump($query);
}
// Results:
// Searching with query `picture of a cat` using `image`
// Searching with query `video of a dog` using `video`
