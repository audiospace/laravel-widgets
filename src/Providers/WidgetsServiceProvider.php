<?php

declare(strict_types=1);

namespace Rinvex\Widgets\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Rinvex\Widgets\Factories\WidgetFactory;
use Illuminate\View\Compilers\BladeCompiler;
use Rinvex\Widgets\Console\Commands\WidgetMakeCommand;

class WidgetsServiceProvider extends ServiceProvider
{
    /**
     * The commands to be registered.
     *
     * @var array
     */
    protected $commands = [
        WidgetMakeCommand::class => 'command.rinvex.widgets.make',
    ];

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // Merge config
        $this->mergeConfigFrom(realpath(__DIR__.'/../../config/config.php'), 'rinvex.widgets');

        $this->registerWidgetFactory();
        $this->registerWidgetCollection();

        // Register console commands
        ! $this->app->runningInConsole() || $this->registerCommands();
    }

    /**
     * Register the widget collection.
     *
     * @return void
     */
    public function registerWidgetCollection()
    {
        // Register widget collection
        $this->app->singleton('rinvex.widgets.list', function ($app) {
            return collect();
        });
    }

    /**
     * Register the widget factory.
     *
     * @return void
     */
    public function registerWidgetFactory()
    {
        $this->app->singleton('rinvex.widgets', function ($app) {
            return new WidgetFactory();
        });

        $this->app->alias('rinvex.widgets', WidgetFactory::class);

        $this->app->singleton('rinvex.widgets.group', function () {
            return collect();
        });
    }

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot(Router $router)
    {
        // Load resources
        $this->loadRoutes($router);
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'rinvex/widgets');

        // Publish Resources
        ! $this->app->runningInConsole() || $this->publishResources();

        $this->app->afterResolving('blade.compiler', function (BladeCompiler $bladeCompiler) {
            // @widget('widgetName')
            $bladeCompiler->directive('widget', function ($expression) {
                return "<?php echo app('rinvex.widgets')->make({$expression}); ?>";
            });

            // @widgetGroup('widgetName')
            $bladeCompiler->directive('widgetGroup', function ($expression) {
                return "<?php echo app('rinvex.widgets.group')->group({$expression})->render(); ?>";
            });
        });
    }

    /**
     * Load the routes.
     *
     * @param \Illuminate\Routing\Router $router
     *
     * @return void
     */
    protected function loadRoutes(Router $router)
    {
        // Load routes
        if (! $this->app->routesAreCached() && config('rinvex.widgets.register_routes')) {
            $router->get('widget', function () {
                $factory = app('rinvex.widgets');
                $widgetName = urldecode(request()->input('name'));
                $widgetParams = $factory->decryptWidgetParams(request()->input('params', ''));

                return call_user_func_array([$factory, $widgetName], $widgetParams);
            })->name('rinvex.widgets.async')->middleware('web');

            $this->app->booted(function () use ($router) {
                $router->getRoutes()->refreshNameLookups();
                $router->getRoutes()->refreshActionLookups();
            });
        }
    }

    /**
     * Publish resources.
     *
     * @return void
     */
    protected function publishResources()
    {
        $this->publishes([realpath(__DIR__.'/../../config/config.php') => config_path('rinvex.widgets.php')], 'rinvex-widgets-config');
        $this->publishes([realpath(__DIR__.'/../../resources/views') => resource_path('views/vendor/rinvex/widgets')], 'rinvex-widgets-views');
    }

    /**
     * Register console commands.
     *
     * @return void
     */
    protected function registerCommands()
    {
        // Register artisan commands
        foreach ($this->commands as $key => $value) {
            $this->app->singleton($value, function ($app) use ($key) {
                return new $key();
            });
        }

        $this->commands(array_values($this->commands));
    }
}
