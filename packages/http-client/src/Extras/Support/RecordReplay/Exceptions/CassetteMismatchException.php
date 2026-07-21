<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Support\RecordReplay\Exceptions;

final class CassetteMismatchException extends CassetteReplayException
{
    public function __construct()
    {
        parent::__construct('HTTP request does not match the next cassette interaction.');
    }
}
