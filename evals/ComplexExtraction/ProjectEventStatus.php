<?php declare(strict_types=1);

namespace Evals\ComplexExtraction;

/** Represents status of project event */
enum ProjectEventStatus: string {
    case Open = 'open';
    case Closed = 'closed';
    case Unknown = 'unknown';
}
