<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\Events;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * Event Service Provider for Instructor.
 *
 * Registers event listeners that bridge Instructor's PSR-14 events
 * to Laravel's event system, enabling logging, monitoring, and
 * integration with Laravel's event infrastructure.
 */
class InstructorEventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // Map Instructor events to Laravel event listeners
        // Users can extend this in their own EventServiceProvider
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
