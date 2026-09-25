<?php

declare(strict_types=1);

namespace Cognesy\Retrieval;

use Cognesy\Retrieval\Core\RetrievalExecutionSession;
use Cognesy\Retrieval\Data\RetrievalRequest;
use Cognesy\Retrieval\Data\RetrievalResponse;
use Cognesy\Retrieval\Data\SearchHits;

final readonly class PendingRetrieval
{
    public function __construct(
        private RetrievalRequest $request,
        private RetrievalExecutionSession $session,
    ) {}

    public function get(): SearchHits
    {
        return $this->response()->hits();
    }

    public function response(): RetrievalResponse
    {
        return $this->session->response();
    }

    public function request(): RetrievalRequest
    {
        return $this->request;
    }

    public function executionId(): string
    {
        return $this->session->executionId();
    }
}
