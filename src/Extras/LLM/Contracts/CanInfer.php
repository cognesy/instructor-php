<?php
namespace Cognesy\Instructor\Extras\LLM\Contracts;

use Cognesy\Instructor\Enums\Mode;
use Psr\Http\Message\ResponseInterface;

interface CanInfer
{
    /**
     * @param string|array $messages
     * @param array $options
     * @return ResponseInterface
     */
    public function infer(
        array $messages = [],
        string $model = '',
        array $tools = [],
        string|array $toolChoice = '',
        array $responseFormat = [],
        array $options = [],
        Mode $mode = Mode::Text,
    ) : ResponseInterface;
}
