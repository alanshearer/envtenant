<?php
namespace ThinkSayDo\EnvTenant\Events;

use Illuminate\Foundation\Application;

class TenantNotResolvedListener
{
    protected $app = null;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function handle(TenantNotResolvedEvent $event)
    {
        if ( ! $this->app->runningInConsole())
        {
            throw new TenantNotResolvedException($event->tenant);
        }
    }
}