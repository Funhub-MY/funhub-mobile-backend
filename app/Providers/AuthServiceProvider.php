<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use App\Models\Audit;
use App\Policies\AuditPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use OwenIt\Auditing\Models\Audit as ModelsAudit;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        ModelsAudit::class => AuditPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Gate::define('audit', function ($user) {
            return $user->hasRole('super_admin');
        });

        Gate::define('restoreAudit', function ($user) {
            return $user->hasRole('super_admin');
        });
    }
}
