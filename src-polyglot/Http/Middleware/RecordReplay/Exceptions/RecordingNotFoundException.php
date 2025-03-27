<?php

namespace Cognesy\Polyglot\Http\Middleware\RecordReplay\Exceptions;

/**
 * Exception thrown when a recording is not found and fallback is disabled
 */
class RecordingNotFoundException extends \RuntimeException
{
}