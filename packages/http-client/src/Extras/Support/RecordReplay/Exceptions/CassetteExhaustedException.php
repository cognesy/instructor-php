<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Support\RecordReplay\Exceptions;

final class CassetteExhaustedException extends CassetteReplayException
{
    public function __construct()
    {
        parent::__construct('HTTP cassette has no remaining interactions.');
    }
}
