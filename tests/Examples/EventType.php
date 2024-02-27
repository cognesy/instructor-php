<?php

namespace Tests\Examples;

/** Represents type of project event */
enum EventType: string {
    case Risk = 'risk';
    case Issue = 'issue';
    case Action = 'action';
    case Progress = 'progress';
    case Other = 'other';
}
