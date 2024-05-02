<?php

namespace Cognesy\Instructor\Services;

use Cognesy\Instructor\Core\StreamFactory;
use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Data\Response;
use Cognesy\Instructor\Stream;

// TODO: this is part of refactoring in progress - currently not used

class InstructorService
{
    public function __construct(
        private ConfigurationService $configurationService,
        private SchemaService $schemaService,
        private LanguageModelService $languageModelService,
        private StreamFactory $streamFactory,
    ) {
    }

    public function respond(Request $request) : Response {
        $responseModel = null;
        return $this->languageModelService->respond($request, $responseModel, $request->client());
    }

    public function stream(Request $request) : Stream {
        $responseModel = null;
        $stream = $this->languageModelService->stream($request, $responseModel, $request->client());
        return $this->streamFactory->create($stream);
    }
}
