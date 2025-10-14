<?php declare(strict_types=1);

namespace Cognesy\Instructor\Partials\Transducers;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Partials\Reducers\DeserializeAndDeduplicateReducer;
use Cognesy\Instructor\Streaming\PartialGen\AssemblePartialObject;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;

/**
 * Deserialize JSON to object and deduplicate using existing value objects.
 *
 * Input:  PartialContext (with json)
 * Output: PartialContext (with object, shouldEmit flag)
 * State:  PartialObject (tracks hash for deduplication)
 */
final readonly class DeserializeAndDeduplicate implements Transducer
{
    public function __construct(
        private AssemblePartialObject $assembler,
        private ResponseModel $responseModel,
    ) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new DeserializeAndDeduplicateReducer(
            inner: $reducer,
            assembler: $this->assembler,
            responseModel: $this->responseModel,
        );
    }
}
