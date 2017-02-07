## Laravel EnvTenant 2.2.*

Version 2.2.9 Changes:

- Laravel 5.3 support.

Version 2.2.6 Changes:

- Set connection column to 'pending' to block tenant access until admin approval.

Version 2.2.3 Changes:

- Added ability to get all Tenants
- Added ability to activate and run a callback on all Tenant models
- Added ability to get Tenant by ID instead of subdomain or domain (example.com/{tenantId})

Version 2.2.2 Changes:

- Removed generic listener
- Removed public setDefaultConnection method
- Changed resolver registration to be app('tenant')
- Added TenantContract to enable custom models
- Updated documentation

The Laravel 5.2 EnvTenant package enables you to easily add multi-tenant capabilities to your application.
This package is designed using a minimalist approach providing just the essentials - no views, routes,
or configs. Just drop it in, run the migration, and start adding tenants. Your applications will
have access to current tenant information through the dynamically set config('tenant') values.
Optionally, you can let applications reconnect to the default master database so a tenant
could manage all tenant other accounts for example. And, perhaps the best part, Artisan
is completely multi-tenant aware! Just add the --tenant option to any command to
run that command on one or all tenants. Works on migrations, queueing, etc.!

EnvTenant also offers a TenantContract, triggers Laravel events, and throws a TenantNotResolvedException,
so you can easily add in custom functionality and tweak it for your needs.

Laravel EnvTenant was originally forked from the Laravel Tenantable project by @leemason
Lee is to be credited with doing a lot of the hard work to figure out how to globally
add the --tenant option to Artisan and for inspiration for the idea. Where this
project differs is in it's approach to managing database connection settings.
Tenantable stores settings in the database and offers unlimited domains.
EnvTenant relies on your ENV and Database config and stores just the
conneciton name in the table and only allows one subdomain and
domain per tenant, which is most often plenty for most apps.
EnvTenant also throws TenantNotResolvedException when
tenants are not found, which you can catch.


## Simple Installation & Usage

Composer install:

```
composer require thinksaydo/envtenant:2.2.*
```

Then run composer dump-autoload.

Tenants database table install:

```php 
artisan migrate --path /vendor/thinksaydo/envtenant/migrations
```

Service provider install:

```php
ThinkSayDo\EnvTenant\TenantServiceProvider::class,
```

Tenant creation (just uses a standard Eloquent model):

```php
$tenant = new \ThinkSayDo\EnvTenant\Tenant();
$tenant->name = 'ACME Inc.';
$tenant->email = 'person@acmeinc.com';
$tenant->subdomain = 'acme';
$tenant->alias_domain = 'acmeinc.com';
$tenant->connection = 'db1';
$tenant->meta = ['phone' => '123-123-1234'];
$tenant->save();
```

And you're done! Minimalist, simple. Whenever your app is visited via http://acme.domain.com or http://acmeinc.com
the default database connection will be set to "db1", the table prefix will switch to "acme_", and config('tenant')
will be set with tenant details allowing you to access values from your views or application.


## Advanced EnvTenant Usage

### Artisan

```php
// migrate master database tables
php artisan migrate

// migrate specific tenant database tables
php artisan migrate --tenant=acme

// migrate all tenant database tables
php artisan migrate --tenant=*
```

The --tenant option works on all Artisan commands.


### Tenant

The ```\ThinkSayDo\EnvTenant\Tenant``` class is a simple Eloquent model providing basic tenant settings.

```php
$tenant = new \ThinkSayDo\EnvTenant\Tenant();

// The unique name field identifies the tenant profile
$tenant->name = 'ACME Inc.';

// The non-unique email field lets you email tenants
$tenant->email = 'person@acmeinc.com';

// The unique subdomain field represents the subdomain portion of a domain and the database table prefix
// Set subdomain and alias_domain field to NULL to access tenant by ID instead
$tenant->subdomain = 'acme';

// The unique alias_domain field represents an alternate full domain that can be used to access the tenant
// Set subdomain and alias_domain field to NULL to access tenant by ID instead
$tenant->alias_domain = 'acmeinc.com';

// The non-unique connection field stores the Laravel database connection name
$tenant->connection = 'db1';

// The meta field is cast to an array and allows you to store any extra values you might need to know
$tenant->meta = ['phone' => '123-123-1234'];

$tenant->save();
```


### TenantResolver

The ```\ThinkSayDo\EnvTenant\TenantResolver``` class is responsible for resolving and managing the active tenant
during Web and Artisan access. You can access the resolver class using ```app('tenant')```.

```php
// get the resolver instance
$resolver = app('tenant');

// check if valid tenant
$resolver->isResolved();

// get the active tenant (returns Tenant model or null)
$tenant = $resolver->getActiveTenant();

// get all tenants (returns collection of Tenant models or null)
$tenants = $resolver->getAllTenants();

// activate and run all tenants through a callback function
$resolver->mapAllTenants(function ($tenant) {});

// reconnect default connection enabling access to "tenants" table
$resolver->reconnectDefaultConnection();

// reconnect tenant connection disabling access to "tenants" table
$resolver->reconnectTenantConnection();
```

If you want to use a custom model, register a custom service provider that binds a singleton to the TenantContract
and resolves to an instance of your custom tenant model. EnvTenant will automatically defer to your custom model
as long as you load your service provider before loading the EnvTenant\TenantServiceProvider.

Create this example service provider in your app/Providers folder as CustomTenantServiceProvider.php:

```php
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
```

Then register ```App\Providers\CustomTenantServiceProvider::class``` in your config/app.php file.

### Events

Throughout the lifecycle events are fired allowing you to listen and customize behavior.

Tenant activated:
```php
ThinkSayDo\EnvTenant\Events\TenantActivatedEvent
```

Tenant resolved:
```php
ThinkSayDo\EnvTenant\Events\TenantResolvedEvent
```

Tenant not resolved:
```php
ThinkSayDo\EnvTenant\Events\TenantNotResolvedEvent
```

Tenant not resolved via the Web, an exception is thrown:
```php
ThinkSayDo\EnvTenant\Events\TenantNotResolvedException
```


# Securing sessions

Sessions in Laravel can be locked down to a domain, preventing users from jumping across domains and potentially
retaining their authentication. Here's some quick example code that might be useful. I'm sure there are more
sophisticated ways to manage this. In the future, I may also add some cross domain security to EnvTenant.

In your session config file, change the domain value to be something like this:

```php
'domain' => ( ! empty($_SERVER['HTTP_HOST'])) ? $_SERVER['HTTP_HOST'] : null,
```

### Enjoy! Report issues or ideas.
