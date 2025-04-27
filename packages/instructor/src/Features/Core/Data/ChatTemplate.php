<?php
namespace Cognesy\Instructor\Features\Core\Data;

use Cognesy\Polyglot\LLM\Enums\Mode;
use Cognesy\Template\Script\Script;
use Cognesy\Utils\Settings;

class ChatTemplate
{
    use Traits\ChatTemplate\HandlesCachedContext;
    use Traits\ChatTemplate\HandlesRetries;
    use Traits\ChatTemplate\HandlesScript;
    use Traits\ChatTemplate\HandlesSections;
    use Traits\ChatTemplate\HandlesUtils;

    private string $defaultRetryPrompt;
    private array $defaultPrompts = [];

    private ?StructuredOutputRequest $request;
    private Script $script;

    public function __construct(StructuredOutputRequest $request = null) {
        $this->request = $request;
        $this->defaultRetryPrompt = Settings::get('llm', 'defaultRetryPrompt');
        $this->defaultPrompts[Mode::MdJson->value] = Settings::get('llm', 'defaultMdJsonPrompt');
        $this->defaultPrompts[Mode::Json->value] = Settings::get('llm', 'defaultJsonPrompt');
        $this->defaultPrompts[Mode::Tools->value] = Settings::get('llm', 'defaultToolsPrompt');
    }

    public static function fromRequest(StructuredOutputRequest $request) : static {
        return new self($request);
    }

    public function toMessages() : array {
        $this->script = $this
            ->makeScript($this->request)
            ->mergeScript($this->makeCachedScript($this->request->cachedContext()));

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
                parameters: ['json_schema' => $this->makeJsonSchema() ?? []],
            );

        return $output;
    }
}