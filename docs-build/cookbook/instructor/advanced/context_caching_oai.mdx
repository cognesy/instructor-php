<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Schema\Attributes\Description;
use Cognesy\Utils\Str;

class Project {
    public string $name;
    public string $targetAudience;
    /** @var string[] */
    #[Description('Technology platform and libraries used in the project')]
    public array $technologies;
    /** @var string[] */
    #[Description('Target audience domain specific features and capabilities of the project')]
    public array $features;
    /** @var string[] */
    #[Description('Target audience domain specific applications and potential use cases of the project')]
    public array $applications;
    #[Description('Explain the purpose of the project and the target audience domain specific problems it solves')]
    public string $description;
    #[Description('Target audience domain specific example code in Markdown demonstrating an application of the library')]
    public string $code;
}
?>
```
```php
<?php
$content = file_get_contents(__DIR__ . '/../../../README.md');

$cached = (new StructuredOutput)->using('openai')->withCachedContext(
    system: 'Your goal is to respond questions about the project described in the README.md file'
        . "\n\n# README.md\n\n" . $content,
    prompt: 'Respond with strict JSON object using schema:\n<|json_schema|>',
);//->withDebugPreset('on');
?>
```
```php
<?php
// get StructuredOutputResponse object to get access to usage and other metadata
$response1 = $cached->with(
    messages: 'Describe the project in a way compelling to my audience: P&C insurance CIOs.',
    responseModel: Project::class,
    options: ['max_tokens' => 4096],
    mode: OutputMode::MdJson,
)->create();

// get processed value - instance of Project class
$project1 = $response1->get();
dump($project1);
assert($project1 instanceof Project);
assert(Str::contains($project1->name, 'Instructor'));

// get usage information from response() method which returns raw InferenceResponse object
$usage1 = $response1->response()->usage();
echo "Usage: {$usage1->inputTokens} prompt tokens, {$usage1->cacheWriteTokens} cache write tokens\n";
?>
```
```php
<?php
// get StructuredOutputResponse object to get access to usage and other metadata
$response2 = $cached->with(
    messages: "Describe the project in a way compelling to my audience: boutique CMS consulting company owner.",
    responseModel: Project::class,
    options: ['max_tokens' => 4096],
    mode: OutputMode::Json,
)->create();

// get the processed value - instance of Project class
$project2 = $response2->get();
dump($project2);
assert($project2 instanceof Project);
assert(Str::contains($project2->name, 'Instructor'));

// get usage information from response() method which returns raw InferenceResponse object
$usage2 = $response2->response()->usage();
echo "Usage: {$usage2->inputTokens} prompt tokens, {$usage2->cacheReadTokens} cache read tokens\n";
if ($usage2->cacheReadTokens === 0) {
    echo "Note: cacheReadTokens is 0. Prompt caching applies only to eligible models and prompt sizes.\n";
}
?>
