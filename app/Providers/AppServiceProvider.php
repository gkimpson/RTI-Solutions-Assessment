<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Tag;
use App\Models\Task;
use App\Observers\TaskObserver;
use App\Policies\TagPolicy;
use App\Policies\TaskPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     */
    protected array $policies = [
        Task::class => TaskPolicy::class,
        Tag::class => TagPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
        $this->registerObservers();
    }

    /**
     * Register the application's policies.
     */
    public function registerPolicies(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }

    /**
     * Register the application's model observers.
     */
    public function registerObservers(): void
    {
        Task::observe(TaskObserver::class);
    }
}
