<?php
namespace ThinkSayDo\EnvTenant\Contracts;

interface TenantContract
{
    public function getNameAttribute();

    public function setNameAttribute($value);

    public function getEmailAttribute();

    public function setEmailAttribute($value);

    public function getSubdomainAttribute();

    public function setSubdomainAttribute($value);

    public function getAliasDomainAttribute($value);

    public function setAliasDomainAttribute($value);

    public function getConnectionAttribute();

    public function setConnectionAttribute($value);

    public function getMetaAttribute();

    public function setMetaAttribute($value);
}