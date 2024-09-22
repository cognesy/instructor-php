<?php

namespace Cognesy\Instructor\Extras\LLM\Contracts;

use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\Enums\Mode;
use Generator;

interface CanInfer
{
    /**
     * @param string|array $messages
     * @param array $options
     * @return ApiResponse
     */
    public function infer(
        array $messages = [],
        string $model = '',
        array $tools = [],
        string|array $toolChoice = '',
        array $responseFormat = [],
        array $options = [],
        Mode $mode = Mode::Json,
    ) : ApiResponse;

    /**
     * @param string|array $messages
     * @param array $options
     * @return Generator<PartialApiResponse>
     */
//    public function stream(
//        array $messages = [],
//        string $model = '',
//        array $tools = [],
//        string $toolChoice = '',
//        array $responseFormat = [],
//        array $options = [],
//        Mode $mode = Mode::Json,
//    ) : Generator;
}
