<?php

namespace Tests\Examples\Maybe;

enum IssueType : string {
    case Bug = 'bug';
    case Feature = 'feature';
    case Other = 'other';
}
