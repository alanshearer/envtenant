<?php

namespace AlanShearer\EnvTenant;

use Illuminate\Console\Events\ArtisanStarting;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleExceptionEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\EventDispatcher;
use AlanShearer\EnvTenant\Events\TenantActivatedEvent;
use AlanShearer\EnvTenant\Events\TenantResolvedEvent;
use AlanShearer\EnvTenant\Events\TenantNotResolvedEvent;
use AlanShearer\EnvTenant\Events\TenantNotResolvedException;
use AlanShearer\EnvTenant\Contracts\TenantContract;
use \Dotenv;

class TenantResolver {

    protected $app = null;
    protected $tenant = null;
    protected $request = null;
    protected $activeTenant = null;
    protected $consoleDispatcher = false;
    protected $defaultConnection = null;
    protected $tenantConnection = null;

    public function __construct(Application $app, TenantContract $tenant) {
        $this->app = $app;
        $this->tenant = $tenant;
        $this->defaultConnection = $this->app['db']->getDefaultConnection();
        $this->tenantConnection = 'envtenant';
        config()->set('database.connections.' . $this->tenantConnection, config('database.connections.' . $this->defaultConnection));
    }

    public function setActiveTenant(TenantContract $activeTenant) {
        $this->activeTenant = $activeTenant;
        $this->setDefaultConnection($activeTenant);

        event(new TenantActivatedEvent($activeTenant));
    }

    public function getActiveTenant() {
        return $this->activeTenant;
    }

    public function getAllTenants() {
        return $this->tenant->get();
    }

    public function mapAllTenants($callback) {
        $tenants = $this->getAllTenants();

        foreach ($tenants as $tenant) {
            $this->setActiveTenant($tenant);

            $callback($tenant);
        }
    }

    public function reconnectDefaultConnection() {
        $this->setDefaultConnection($this->tenantConnection);
    }

    public function reconnectTenantConnection() {
        $this->setDefaultConnection($this->getActiveTenant());
    }

    public function resolveTenant() {
        $this->registerTenantConsoleArgument();
        $this->registerConsoleStartEvent();
        $this->registerConsoleTerminateEvent();
        $this->resolveRequest();
    }

    public function isResolved() {
        return !is_null($this->getActiveTenant());
    }

    protected function hasSubdomain($domain) {
        $exploded = explode('.', $domain);
        if (count($exploded) > 1) {
            return true;
        }
        return false;
    }

    protected function resolveRequest() {
        if ($this->app->runningInConsole()) {
            $domain = (new ArgvInput())->getParameterOption('--tenant', null);

            try {
                $model = $this->tenant;
                $tenant = $model
                        ->where('subdomain', '=', $domain)
                        ->orWhere('alias_domain', '=', $domain)
                        ->orWhere('id', '=', $domain)
                        ->first();
            } catch (\Exception $e) {
                $tenant = null;
                echo $e->getMessage();
            }
        } else {
            $this->request = $this->app->make(Request::class);
            $domain = $this->request->getHost();
            if ($this->hasSubdomain($domain)) {
                $subdomain = explode('.', $domain)[0];
                $id = $this->request->segment(1);

                $model = $this->tenant;
                $tenant = $model
                        ->where(function($query) use ($subdomain, $domain) {
                            $query->where('subdomain', '=', $subdomain);
                            $query->orWhere('alias_domain', '=', $domain);
                        })
                        ->orWhere(function($query) use ($id) {
                            $query->whereNull('subdomain');
                            $query->whereNull('alias_domain');
                            $query->where('id', $id);
                        })
                        ->first();
            } else {
                $tenant = null;
            }
        }
        if (!$this->hasSubdomain($domain)) {
            return;
        }

        if (
                empty($tenant->connection) ||
                (!empty($tenant->connection) && $tenant->connection === 'pending')
        )
            $tenant = null;

        if ($tenant instanceof TenantContract) {
            $this->setActiveTenant($tenant);

            event(new TenantResolvedEvent($tenant));

            return;
        }

        event(new TenantNotResolvedEvent($domain));

        if (!$this->app->runningInConsole()) {
            throw new TenantNotResolvedException($domain);
        }

        return;
    }

    protected function setDefaultConnection($activeTenant) {
        $hasConnection = !empty($activeTenant->connection);
        $connection = $hasConnection ? $activeTenant->connection : $this->tenantConnection;
        $prefix = ($hasConnection && !empty($activeTenant->subdomain)) ? $activeTenant->subdomain . '_' : '';
        //$folder = $activeTenant->subdomain
        if (empty($activeTenant->subdomain)) {
            $path = base_path();
        } else {
            $path = base_path('/storage/tenants/' . $activeTenant->subdomain . '/');
        }
        $dotenv = new Dotenv\Dotenv($path);
        $dotenv->load();

        if ($hasConnection && empty($activeTenant->subdomain)) {
            $prefix = 'tenant' . $activeTenant->id . '_';
        }

        config()->set('database.default', $connection);
        config()->set('database.connections.' . $connection . '.prefix', '');

        if ($hasConnection) {
            config()->set('tenant', $activeTenant->toArray());
            $this->app['db']->purge($this->defaultConnection);
        }

        $this->app['db']->setDefaultConnection($connection);
    }

    protected function getConsoleDispatcher() {
        if (!$this->consoleDispatcher) {
            $this->consoleDispatcher = app(EventDispatcher::class);
        }

        return $this->consoleDispatcher;
    }

    protected function registerTenantConsoleArgument() {
        $this->app['events']->listen(ArtisanStarting::class, function($event) {
            $definition = $event->artisan->getDefinition();

            $definition->addOption(
                    new InputOption('--tenant', null, InputOption::VALUE_OPTIONAL, 'The tenant subdomain or alias domain the command should be run for. Use * or all for every tenant.')
            );

            $event->artisan->setDefinition($definition);
            $event->artisan->setDispatcher($this->getConsoleDispatcher());
        });
    }

    protected function registerConsoleStartEvent() {
        $this->getConsoleDispatcher()->addListener(ConsoleEvents::COMMAND, function(ConsoleCommandEvent $event) {
            $tenant = $event->getInput()->getParameterOption('--tenant', null);

            if (!is_null($tenant)) {
                if ($tenant == '*' || $tenant == 'all') {
                    $event->disableCommand();
                } else {
                    if ($this->isResolved()) {
                        $event->getOutput()->writeln('<info>Running command for ' . $this->getActiveTenant()->name . '</info>');
                    } else {
                        $event->getOutput()->writeln('<error>Failed to resolve tenant</error>');
                        $event->disableCommand();
                    }
                }
            }
        });
    }

    protected function registerConsoleTerminateEvent() {
        $this->getConsoleDispatcher()->addListener(ConsoleEvents::TERMINATE, function(ConsoleTerminateEvent $event) {
            $tenant = $event->getInput()->getParameterOption('--tenant', null);

            if (!is_null($tenant)) {
                if ($tenant == '*' || $tenant == 'all') {
                    $command = $event->getCommand();
                    $input = $event->getInput();
                    $output = $event->getOutput();
                    $exitCode = $event->getExitCode();

                    $tenants = $this->getAllTenants();

                    foreach ($tenants as $tenant) {
                        $this->setActiveTenant($tenant);

                        $event->getOutput()->writeln('<info>Running command for ' . $this->getActiveTenant()->name . '</info>');

                        try {
                            $exitCode = $command->run($input, $output);
                        } catch (\Exception $e) {
                            $event = new ConsoleExceptionEvent($command, $input, $output, $e, $e->getCode());

                            $this->getConsoleDispatcher()->dispatch(ConsoleEvents::EXCEPTION, $event);

                            $e = $event->getException();

                            throw $e;
                        }
                    }

                    $event->setExitCode($exitCode);
                }
            }
        });
    }

}
