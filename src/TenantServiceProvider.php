<?php
namespace ThinkSayDo\EnvTenant;

use Illuminate\Support\ServiceProvider;

class TenantServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bindIf('TenantContract', function()
        {
            return new Tenant();
        }, true);

        $this->app->singleton('tenant', function($app)
        {
            $tenant = app('TenantContract');
            return new TenantResolver($app, $tenant);
        });
    }

    public function boot()
    {
        $resolver = app('tenant');
        $resolver->resolveTenant();
    }
}