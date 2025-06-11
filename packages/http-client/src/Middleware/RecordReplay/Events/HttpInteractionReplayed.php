<?php

namespace Cognesy\Http\Middleware\RecordReplay\Events;

use Cognesy\Events\Event;

/**
 * Event fired when a recorded HTTP interaction is replayed
 */
final class HttpInteractionReplayed extends Event {}
