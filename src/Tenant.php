<?php

namespace AlanShearer\EnvTenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use AlanShearer\EnvTenant\Contracts\TenantContract;
use File;

class Tenant extends Model implements TenantContract {

    protected $table = 'tenants';
    protected $connection = 'envtenant';
    protected $fillable = [
        'name',
        'email',
        'subdomain',
        'alias_domain',
        'connection',
        'meta'
    ];
    protected $casts = [
        'name' => 'string',
        'email' => 'string',
        'subdomain' => 'string',
        'alias_domain' => 'string',
        'connection' => 'string',
        'meta' => 'array'
    ];

    public function __construct(array $attributes = []) {
        $this->setConnection(config('database.default'));

        parent::__construct($attributes);
    }

    public function getNameAttribute() {
        return $this->attributes['name'];
    }

    public function setNameAttribute($value) {
        $this->attributes['name'] = trim($value);
    }

    public function getEmailAttribute() {
        return $this->attributes['email'];
    }

    public function setEmailAttribute($value) {
        $this->attributes['email'] = trim($value);
    }

    public function getSubdomainAttribute() {
        return $this->attributes['subdomain'];
    }

    public function setSubdomainAttribute($value) {
        $this->attributes['subdomain'] = mb_strtolower($this->_alphaOnly($value));
    }

    public function getAliasDomainAttribute($value) {
        return $this->attributes['alias_domain'];
    }

    public function setAliasDomainAttribute($value) {
        $this->attributes['alias_domain'] = mb_strtolower($this->_alphaOnly($value));
    }

    public function getConnectionAttribute() {
        return $this->attributes['connection'];
    }

    public function setConnectionAttribute($value) {
        $this->attributes['connection'] = strtolower(trim($value));
    }

    public function getMetaAttribute() {
        return json_decode($this->attributes['meta'], true);
    }

    public function setMetaAttribute($value) {
        $this->attributes['meta'] = json_encode($value);
    }

    protected function _alphaOnly($value) {
        return preg_replace('/[^[:alnum:]\-\.]/u', '', $value);
    }

    /*
     * override Model::save method
     */

    public function save(array $options = array(), $createEnvFile = true) {
        $saved = parent::save($options);
        if ($saved && $createEnvFile) {
            $this->createEnvFile();
            $this->createDB();
        }
        return $saved;
    }

    /*
     * function createDB:
     * create a database with name equals to subdomain property
     */

    public function createDB() {
        return $this->getConnection()->statement('CREATE DATABASE ' . $this->subdomain);
    }

    /*
     * function createEnvFile
     * create a {subdomain}/.env file into the specified path, or eventually in /storage/tenants/
     */

    public function createEnvFile($path = '') {
        if (empty($path)) {
            $path = storage_path('/tenants/' . $this->subdomain . '/');
        } else {
            $path = $path . $this->subdomain . '/';
        }
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
        $environmentPath = $path . '.env';
        copy(base_path('.env'), $environmentPath);

        if (file_exists($environmentPath)) {
            file_put_contents($environmentPath, preg_replace('#(DB_DATABASE=.*)#', 'DB_DATABASE=' . $this->subdomain, file_get_contents($environmentPath)));
        }
        return true;
    }

}
