<?php

namespace Cognesy\Http\Middleware\RecordReplay\Events;

use Cognesy\Events\Event;

/**
 * Event fired when a recording is not found for a request
 */
final class HttpInteractionNotFound extends Event {}
