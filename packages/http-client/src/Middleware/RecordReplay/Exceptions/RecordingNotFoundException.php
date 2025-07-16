<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware\RecordReplay\Exceptions;

/**
 * Exception thrown when a recording is not found and fallback is disabled
 */
class RecordingNotFoundException extends \RuntimeException
{
}