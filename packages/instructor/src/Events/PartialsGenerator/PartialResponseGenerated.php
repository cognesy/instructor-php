<?php declare(strict_types=1);

namespace Cognesy\Instructor\Events\PartialsGenerator;

use Cognesy\Events\Event;
use Cognesy\Utils\Json\Json;

final class PartialResponseGenerated extends Event
{
    public function __construct(
        public mixed $partialResponse
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return Json::encode($this->partialResponse);
    }
}