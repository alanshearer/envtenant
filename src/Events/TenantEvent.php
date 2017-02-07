<?php
namespace ThinkSayDo\EnvTenant\Events;

use Illuminate\Queue\SerializesModels;
use ThinkSayDo\EnvTenant\Tenant;

class TenantEvent
{
    use SerializesModels;

    public $tenant;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

}