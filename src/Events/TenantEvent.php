<?php
namespace AlanShearer\EnvTenant\Events;

use Illuminate\Queue\SerializesModels;
use AlanShearer\EnvTenant\Tenant;

class TenantEvent
{
    use SerializesModels;

    public $tenant;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

}