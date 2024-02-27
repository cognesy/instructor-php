<?php
namespace Tests\Examples;

enum JobType : string
{
    case FullTime = 'full-time';
    case PartTime = 'part-time';
    case SelfEmployed = 'self-employed';
    case Unemployed = 'unemployed';
    case Other = 'other';
}
