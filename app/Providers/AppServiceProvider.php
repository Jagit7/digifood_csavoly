<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use App\Services\GreetingService;
use Illuminate\Database\Eloquent\Relations\Relation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\GreetingService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'child' => \App\Models\Child::class,
            'institution_employee' => \App\Models\InstitutionEmployee::class,
        ]);

        Blade::directive('greeting', function ($expression) {
            $expr = $expression ?: 'auth()->user()->name';

            return "<?php echo app(App\Services\GreetingService::class)->greet({$expr}); ?>";
        });
    }
}
