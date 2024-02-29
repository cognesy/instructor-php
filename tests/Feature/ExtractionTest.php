<?php
namespace Tests;

use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\LLMs\OpenAI\LLM;
use Mockery;
use Tests\Examples\Address;
use Tests\Examples\Event;
use Tests\Examples\Events;
use Tests\Examples\JobType;
use Tests\Examples\Person;
use Tests\Examples\PersonWithAddress;
use Tests\Examples\PersonWithAddresses;
use Tests\Examples\PersonWithJob;
use Tests\Examples\Stakeholder;


it('supports simple properties', function () {
    $mockLLM = Mockery::mock(LLM::class);
    $mockLLM->shouldReceive('callFunction')->andReturnUsing(
        fn() => '{"name":"Jason","age":28}',
    );

    $text = "His name is Jason, he is 28 years old.";
    $person = (new Instructor(llm: $mockLLM))->respond(
        messages: [['role' => 'user', 'content' => $text]],
        responseModel: Person::class,
    );
    // dump($person);
    expect($person)->toBeInstanceOf(Person::class);
    expect($person->name)->toBe('Jason');
    expect($person->age)->toBe(28);
});


it('supports enum properties', function () {
    $mockLLM = Mockery::mock(LLM::class);
    $mockLLM->shouldReceive('callFunction')->andReturnUsing(
        fn() => '{"name":"Jason","age":28,"jobType":"self-employed"}',
    );

    $text = "His name is Jason, he is 28 years old. He is self-employed.";
    $person = (new Instructor(llm: $mockLLM))->respond(
        messages: [['role' => 'user', 'content' => $text]],
        responseModel: PersonWithJob::class,
    );
    // dump($person);
    expect($person)->toBeInstanceOf(PersonWithJob::class);
    expect($person->name)->toBe('Jason');
    expect($person->age)->toBe(28);
    expect($person->jobType)->toBeInstanceOf(JobType::class);
});


it('supports object type property', function () {
    $mockLLM = Mockery::mock(LLM::class);
    $mockLLM->shouldReceive('callFunction')->andReturnUsing(
        fn() => '{"name":"Jason","age":28,"address":{"country":"USA","city":"San Francisco"}}',
    );

    $text = "His name is Jason, he is 28 years old. He lives in San Francisco.";
    $person = (new Instructor(llm: $mockLLM))->respond(
        messages: [['role' => 'user', 'content' => $text]],
        responseModel: PersonWithAddress::class,
    );
    // dump($person);
    expect($person)->toBeInstanceOf(PersonWithAddress::class);
    expect($person->name)->toBe('Jason');
    expect($person->age)->toBe(28);
    expect($person->address)->toBeInstanceOf(Address::class);
});


it('supports arrays of objects property', function () {
    $mockLLM = Mockery::mock(LLM::class);
    $mockLLM->shouldReceive('callFunction')->andReturnUsing(
        fn() => '{"name":"Jason","age":28,"addresses":[{"country":"USA","city":"San Francisco"},{"country":"USA","city":"New York"}]}',
    );

    $text = "His name is Jason, he is 28 years old. He lives in San Francisco. He also has an apartment in New York.";
    $person = (new Instructor(llm: $mockLLM))->respond(
        messages: [['role' => 'user', 'content' => $text]],
        responseModel: PersonWithAddresses::class,
    );
    // dump($person);
    expect($person)->toBeInstanceOf(PersonWithAddresses::class);
    expect($person->name)->toBe('Jason');
    expect($person->age)->toBe(28);
    expect($person->addresses)->toBeArray();
    expect($person->addresses[0])->toBeInstanceOf(Address::class);
});


it('can extract complex, multi-nested structure', function ($text) {
    $mockLLM = Mockery::mock(LLM::class);
    $mockLLM->shouldReceive('callFunction')->andReturnUsing(
        fn() => '{"events":[{"title":"Project Status RED","description":"Acme Insurance project to implement SalesTech CRM solution is currently in RED status due to delayed delivery of document production system, led by 3rd party vendor - Alfatech.","type":"risk","status":"open","stakeholders":[{"name":"Alfatech","role":"vendor"},{"name":"Acme","role":"customer"}],"date":"2021-09-01"},{"title":"Ecommerce Track Delay","description":"Due to dependencies, the ecommerce track will be delayed by 2 sprints because of the delayed delivery of the document production system.","type":"issue","status":"open","stakeholders":[{"name":"Acme","role":"customer"},{"name":"SysCorp","role":"system integrator"}]},{"title":"Test Data Availability Issue","description":"customer is not able to provide the test data for the ecommerce track, which will impact the stabilization schedule unless resolved by the end of the month.","type":"issue","status":"open","stakeholders":[{"name":"Acme","role":"customer"},{"name":"SysCorp","role":"system integrator"}]},{"title":"Steerco Maintains Schedule","description":"Steerco insists on maintaining the release schedule due to marketing campaign already ongoing, regardless of the project issues.","type":"issue","status":"open","stakeholders":[{"name":"Acme","role":"customer"}]},{"title":"Communication Issues","description":"SalesTech team struggling with communication issues as SysCorp team has not shown up on 2 recent calls, leading to lack of insight. This has been escalated to SysCorp\'s leadership team.","type":"issue","status":"open","stakeholders":[{"name":"SysCorp","role":"system integrator"},{"name":"Acme","role":"customer"}]},{"title":"Integration Proxy Issue Resolved","description":"The previously reported Integration Proxy connectivity issue, which was blocking the policy track, has been resolved.","type":"progress","status":"closed","stakeholders":[{"name":"SysCorp","role":"system integrator"}],"date":"2021-08-30"},{"title":"Finalized Production Deployment Plan","description":"Production deployment plan has been finalized on Aug 15th and is awaiting customer approval.","type":"progress","status":"open","stakeholders":[{"name":"Acme","role":"customer"}],"date":"2021-08-15"}]}',
    );

    $instructor = new Instructor(llm: $mockLLM);
    /** @var Events $events */
    $events = $instructor->respond(
        [['role' => 'user', 'content' => $text]],
        Events::class,
    );
    // dump($events);
    expect($events)->toBeInstanceOf(Events::class);
    expect($events->events)->toBeArray();
    expect($events->events[0])->toBeInstanceOf(Event::class);
    expect($events->events[0]->stakeholders[0])->toBeInstanceOf(Stakeholder::class);
})->with('project_report');
