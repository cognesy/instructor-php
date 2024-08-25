<?php
namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\Data\Messages\Script;
use Cognesy\Instructor\Enums\Mode;

class ChatTemplate
{
    use Traits\ChatTemplate\HandlesCachedContext;
    use Traits\ChatTemplate\HandlesRetries;
    use Traits\ChatTemplate\HandlesScript;
    use Traits\ChatTemplate\HandlesSections;
    use Traits\ChatTemplate\HandlesUtils;

    private string $defaultRetryPrompt = "JSON generated incorrectly, fix following errors:\n";
    private array $defaultPrompts = [
        Mode::MdJson->value => "Respond correctly with strict JSON object containing extracted data within a ```json {} ``` codeblock. Object must validate against this JSONSchema:\n<|json_schema|>\n",
        Mode::Json->value => "Respond correctly with strict JSON object. Response must follow JSONSchema:\n<|json_schema|>\n",
        Mode::Tools->value => "Extract correct and accurate data from the input using provided tools.\n",
        //Mode::Tools->value => "Extract correct and accurate data from the input using provided tools. Response must be JSON object following provided tool schema.\n<|json_schema|>\n",
    ];

    private ?Request $request;
    private Script $script;

    public function __construct(Request $request = null) {
        $this->request = $request;
    }

    public static function fromRequest(Request $request) : static {
        return new self($request);
    }

    public function toMessages() : array {
        $this->script = $this->makeScript($this->request)->mergeScript(
            $this->makeCachedScript($this->request->cachedContext())
        );

        // Add retry messages if needed
        $this->addRetryMessages();

        // Add meta sections
        $output = $this
            ->withCacheMetaSections($this->withSections($this->script))
            ->select([
                // potentially cached - predefined sections used to construct the script
                'system',
                'pre-cached',
                    'pre-cached-prompt', 'cached-prompt', 'post-cached-prompt',
                    'pre-cached-examples', 'cached-examples', 'post-cached-examples',
                    'pre-cached-input', 'cached-input', 'post-cached-input',
                    'cached-messages',
                'post-cached',
                // never cached
                'pre-prompt', 'prompt', 'post-prompt',
                'pre-examples', 'examples', 'post-examples',
                'pre-input', 'input', 'post-input',
                'messages',
                'pre-retries', 'retries', 'post-retries'
            ])
            ->toArray(
                context: ['json_schema' => $this->makeJsonSchema() ?? []],
            );

        return $output;
    }
}