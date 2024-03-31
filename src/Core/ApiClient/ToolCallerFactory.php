<?php

namespace Cognesy\Instructor\Core\ApiClient;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\ApiClient\Contracts\CanCallChatCompletion;
use Cognesy\Instructor\ApiClient\Contracts\CanCallJsonCompletion;
use Cognesy\Instructor\ApiClient\Contracts\CanCallTools;
use Cognesy\Instructor\Contracts\CanCallApiClient;
use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Enums\Mode;
use Exception;

class ToolCallerFactory
{
    public function __construct(
        private CanCallApi $client,
        private array $modeHandlers,
        private ?Mode $forceMode = null,
    ) {}

    public function fromRequest(Request $request) : CanCallApiClient {
        $mode = $request->mode->value;
        // if mode is forced, use it
        if ($this->forceMode) {
            $mode = $this->forceMode->value;
        }
        // check if client supports mode
        if (!$this->supportsMode($this->client, $mode)) {
            throw new Exception("Mode `$mode` not supported by ".get_class($this->client));
        }
        // check if handler for mode exists
        if (!isset($this->modeHandlers[$mode])) {
            throw new Exception("Mode handler not found for mode: `{$mode}`");
        }
        // instantiate handler via provided callback
        $callback = $this->modeHandlers[$mode];
        return $callback();
    }

    private function supportsMode(CanCallApi $client, string $mode) : bool {
        return match($mode) {
            Mode::Json->value => $client instanceof CanCallJsonCompletion,
            Mode::Tools->value => $client instanceof CanCallTools,
            Mode::MdJson->value => $client instanceof CanCallChatCompletion,
            default => false,
        };
    }
}