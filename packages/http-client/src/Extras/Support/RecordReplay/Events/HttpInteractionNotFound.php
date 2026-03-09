<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Support\RecordReplay\Events;

use Cognesy\Events\Event;

/**
 * Event fired when a recording is not found for a request
 */
final class HttpInteractionNotFound extends Event {}
