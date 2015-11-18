<?php

namespace tomzx\LaravelMigration\Console;

use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Database\MigrationServiceProvider;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Mockery as m;
use tomzx\LaravelMigration\BaseApplication;

class Application extends \Illuminate\Console\Application
{
    /**
     * @var string
     */
    const VERSION = '0.1.0';

    public function __construct()
    {
        $container = $this->initialize();
        parent::__construct($container, $container['events'], self::VERSION);
        $this->setName('Laravel Migration');
        $this->loadMigrations($container);
    }

    /**
     * @return \tomzx\LaravelMigration\BaseApplication
     */
    private function initialize()
    {
        if (interface_exists('Illuminate\Contracts\Foundation\Application')) {
            $container = new \tomzx\LaravelMigration\v5\Application();
        } else {
            $container = new \tomzx\LaravelMigration\v4\Application();
        }

        $container->singleton('events', function () {
            return new Dispatcher();
        });
        $container->singleton('config', function () {
            $config = require_once 'config.php';
            return new Repository(['database' => $config]);
        });
        $container->singleton('files', function () {
            return new Filesystem();
        });
        $container->singleton('composer', function () {
            $composer = m::mock('\Illuminate\Foundation\Composer');

            $composer->shouldReceive('dumpAutoloads');

            return $composer;
        });

        $dbService = new DatabaseServiceProvider($container);
        $dbService->register();


        $service = new MigrationServiceProvider($container);
        $service->register();

        return $container;
    }

    /**
     * @param \tomzx\LaravelMigration\BaseApplication $container
     */
    private function loadMigrations(BaseApplication $container)
    {
        $files = glob($container->databasePath().'/migrations/*.php');
        foreach ($files as $file) {
            require_once $file;
        }
    }
}
