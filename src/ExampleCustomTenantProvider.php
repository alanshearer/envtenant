<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Tenant;

class CustomTenantServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->app->singleton('TenantContract', function()
        {
            return new Tenant();
        });
    }

    public function register()
    {
        //
    }
}
