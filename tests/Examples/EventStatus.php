<?php

namespace Tests\Examples;

/** Represents status of project event */
enum EventStatus: string {
    case Open = 'open';
    case Closed = 'closed';
    case Unknown = 'unknown';
}

