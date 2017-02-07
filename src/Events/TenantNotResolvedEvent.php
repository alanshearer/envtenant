<?php
namespace ThinkSayDo\EnvTenant\Events;

class TenantNotResolvedEvent extends TenantEvent
{
    public $tenant = null;

    function __construct($tenant)
    {
        $this->tenant = $tenant;
    }
}