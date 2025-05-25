<?php

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Examples\Complex\ProjectEvent;
use Cognesy\Instructor\Tests\Examples\Complex\ProjectEvents;
use Cognesy\Instructor\Tests\Examples\Complex\Stakeholder;
use Cognesy\Instructor\Tests\Examples\Extraction\Address;
use Cognesy\Instructor\Tests\Examples\Extraction\JobType;
use Cognesy\Instructor\Tests\Examples\Extraction\Person;
use Cognesy\Instructor\Tests\Examples\Extraction\PersonWithAddress;
use Cognesy\Instructor\Tests\Examples\Extraction\PersonWithAddresses;
use Cognesy\Instructor\Tests\Examples\Extraction\PersonWithJob;
use Cognesy\Instructor\Tests\MockLLM;

it('supports simple properties', function () {
    $mockLLM = MockLLM::get([
        '{"name":"Jason","age":28}'
    ]);

    $text = "His name is Jason, he is 28 years old.";
    $person = (new StructuredOutput)->withHttpClient($mockLLM)->with(
        messages: [['role' => 'user', 'content' => $text]],
        responseModel: Person::class,
    )->get();
    // dump($person);
    expect($person)->toBeInstanceOf(Person::class);
    expect($person->name)->toBe('Jason');
    expect($person->age)->toBe(28);
});


it('supports enum properties', function () {
    $mockLLM = MockLLM::get([
        '{"name":"Jason","age":28,"jobType":"self-employed"}'
    ]);

    $text = "His name is Jason, he is 28 years old. He is self-employed.";
    $person = (new StructuredOutput)->withHttpClient($mockLLM)->with(
        messages: [['role' => 'user', 'content' => $text]],
        responseModel: PersonWithJob::class,
    )->get();
    // dump($person);
    expect($person)->toBeInstanceOf(PersonWithJob::class);
    expect($person->name)->toBe('Jason');
    expect($person->age)->toBe(28);
    expect($person->jobType)->toBeInstanceOf(JobType::class);
});


it('supports object type property', function () {
    $mockLLM = MockLLM::get([
        '{"name":"Jason","age":28,"address":{"country":"USA","city":"San Francisco"}}'
    ]);

    $text = "His name is Jason, he is 28 years old. He lives in San Francisco.";
    $person = (new StructuredOutput)->withHttpClient($mockLLM)->with(
        messages: [['role' => 'user', 'content' => $text]],
        responseModel: PersonWithAddress::class,
    )->get();
    // dump($person);
    expect($person)->toBeInstanceOf(PersonWithAddress::class);
    expect($person->name)->toBe('Jason');
    expect($person->age)->toBe(28);
    expect($person->address)->toBeInstanceOf(Address::class);
});


it('supports arrays of objects property', function () {
    $mockLLM = MockLLM::get([
        '{"name":"Jason","age":28,"addresses":[{"country":"USA","city":"San Francisco"},{"country":"USA","city":"New York"}]}',
    ]);

    $text = "His name is Jason, he is 28 years old. He lives in USA - he works from his home office in San Francisco, he also has an apartment in New York.";
    $person = (new StructuredOutput)->withHttpClient($mockLLM)->with(
        messages: [['role' => 'user', 'content' => $text]],
        responseModel: PersonWithAddresses::class,
    )->get();
    //dump($person);
    expect($person)->toBeInstanceOf(PersonWithAddresses::class);
    expect($person->name)->toBe('Jason');
    expect($person->age)->toBe(28);
    expect($person->addresses)->toBeArray();
    expect($person->addresses[0])->toBeInstanceOf(Address::class);
});

dataset('project_report', [
    <<<'EOT'
        [2021-09-01]
        Acme Insurance project to implement SalesTech CRM solution is currently in RED status due to delayed delivery of document production system, led by 3rd party vendor - Alfatech. Customer (Acme) is discussing the resolution with the vendor. Due to dependencies it will result in delay of the ecommerce track by 2 sprints. System integrator (SysCorp) are working to absorb some of the delay by deploying extra resources to speed up development when the doc production is done. Another issue is that the customer is not able to provide the test data for the ecommerce track. SysCorp notified it will impact stabilization schedule unless resolved by the end of the month. Steerco has been informed last week about the potential impact of the issues, but insists on maintaining release schedule due to marketing campaign already ongoing. Customer executives are asking us - SalesTech team - to confirm SysCorp's assessment of the situation. We're struggling with that due to communication issues - SysCorp team has not shown up on 2 recent calls. Lack of insight has been escalated to SysCorp's leadership team yesterday, but we've got no response yet. The previously reported Integration Proxy connectivity issue which was blocking policy track has been resolved on 2021-08-30 - the track is now GREEN. Production deployment plan has been finalized on Aug 15th and awaiting customer approval.
    EOT
]);

it('can extract complex, multi-nested structure', function ($text) {
    $mockLLM = MockLLM::get([
        '{"events":[{"title":"Project Status RED","description":"Acme Insurance project to implement SalesTech CRM solution is currently in RED status due to delayed delivery of document production system, led by 3rd party vendor - Alfatech.","type":"risk","status":"open","stakeholders":[{"name":"Alfatech","role":"vendor"},{"name":"Acme","role":"customer"}],"date":"2021-09-01"},{"title":"Ecommerce Track Delay","description":"Due to dependencies, the ecommerce track will be delayed by 2 sprints because of the delayed delivery of the document production system.","type":"issue","status":"open","stakeholders":[{"name":"Acme","role":"customer"},{"name":"SysCorp","role":"system integrator"}]},{"title":"Test Data Availability Issue","description":"customer is not able to provide the test data for the ecommerce track, which will impact the stabilization schedule unless resolved by the end of the month.","type":"issue","status":"open","stakeholders":[{"name":"Acme","role":"customer"},{"name":"SysCorp","role":"system integrator"}]},{"title":"Steerco Maintains Schedule","description":"Steerco insists on maintaining the release schedule due to marketing campaign already ongoing, regardless of the project issues.","type":"issue","status":"open","stakeholders":[{"name":"Acme","role":"customer"}]},{"title":"Communication Issues","description":"SalesTech team struggling with communication issues as SysCorp team has not shown up on 2 recent calls, leading to lack of insight. This has been escalated to SysCorp\'s leadership team.","type":"issue","status":"open","stakeholders":[{"name":"SysCorp","role":"system integrator"},{"name":"Acme","role":"customer"}]},{"title":"Integration Proxy Issue Resolved","description":"The previously reported Integration Proxy connectivity issue, which was blocking the policy track, has been resolved.","type":"progress","status":"closed","stakeholders":[{"name":"SysCorp","role":"system integrator"}],"date":"2021-08-30"},{"title":"Finalized Production Deployment Plan","description":"Production deployment plan has been finalized on Aug 15th and is awaiting customer approval.","type":"progress","status":"open","stakeholders":[{"name":"Acme","role":"customer"}],"date":"2021-08-15"}]}'
    ]);

    $structuredOutput = (new StructuredOutput)->withHttpClient($mockLLM); //$mockLLM
    /** @var ProjectEvents $events */
    $events = $structuredOutput->with(
        messages: [['role' => 'user', 'content' => $text]],
        responseModel: ProjectEvents::class,
        maxRetries: 2,
    )->get();

    expect($events)->toBeInstanceOf(ProjectEvents::class);
    expect($events->events)->toBeArray();
    expect($events->events[0])->toBeInstanceOf(ProjectEvent::class);
    expect($events->events[0]->stakeholders[0])->toBeInstanceOf(Stakeholder::class);
})->with('project_report');
