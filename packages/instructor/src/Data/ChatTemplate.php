<?php
namespace Cognesy\Instructor\Data;

class ChatTemplate
{
    use Traits\ChatTemplate\HandlesCachedContext;
    use Traits\ChatTemplate\HandlesRetries;
    use Traits\ChatTemplate\HandlesScript;
    use Traits\ChatTemplate\HandlesSections;
    use Traits\ChatTemplate\HandlesUtils;

    private StructuredOutputConfig $config;

    public function __construct(?StructuredOutputConfig $config = null) {
        $this->config = $config ?: StructuredOutputConfig::load();
    }

    public function toMessages(StructuredOutputRequest $request) : array {
        $script = $this
            ->makeScript($request)
            ->mergeScript($this->makeCachedScript($request->cachedContext()));

        // Add retry messages if needed
        $script = $this->addRetryMessages($request, $script);

        // Add meta sections
        $output = $this
            ->withCacheMetaSections($request->cachedContext(), $this->withSections($script))
            ->select($this->config->chatStructure())
            ->toArray(
                parameters: ['json_schema' => $this->makeJsonSchema($request->responseModel())],
            );

        return $output;
    }
}