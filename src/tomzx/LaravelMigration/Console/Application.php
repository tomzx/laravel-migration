<?php

namespace tomzx\LaravelMigration\Console;

use Illuminate\Config\FileLoader;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Database\MigrationServiceProvider;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Mockery as m;
use tomzx\LaravelMigration\BaseApplication;

class Application extends \Illuminate\Console\Application
{
    const NAME = 'Laravel Migration';

    /**
     * @var string
     */
    const VERSION = '0.1.0';

    /**
     * @var string
     */
    protected $laravelVersion;

    public function __construct()
    {
        $container = $this->initialize();
        if ($this->laravelVersion === 5) {
            parent::__construct($container, $container['events'], self::VERSION);
            $this->setCatchExceptions(true);
            $this->setName(self::NAME);
        } else {
            parent::__construct(self::NAME, self::VERSION);
            $this->setLaravel($container);
            $this->boot();
        }

        $this->loadMigrations($container);
    }

    /**
     * @return \tomzx\LaravelMigration\BaseApplication
     */
    private function initialize()
    {
        if (interface_exists('Illuminate\Contracts\Foundation\Application')) {
            $container = new \tomzx\LaravelMigration\v5\Application();
            $this->laravelVersion = 5;
            $container['path.database'] = getcwd();
        } else {
            $container = new \tomzx\LaravelMigration\v4\Application();
            $this->laravelVersion = 4;
            $container['path'] = getcwd();
            $container['path.base'] = getcwd();
            $container['path.database'] = getcwd() . '/database';
        }

        $container->singleton('events', function () {
            return new Dispatcher();
        });
        $container->singleton('files', function () {
            return new Filesystem();
        });
        $container->singleton('config', function () use ($container) {
            if ($this->laravelVersion === 5) {
                $config = require_once 'database.php';
                return new Repository(['database' => $config]);
            } else {
                $fileLoader = new FileLoader($container['files'], getcwd());
                return new Repository($fileLoader, 'production');
            }
        });
        $container->singleton('composer', function () {
            $composer = m::mock('\Illuminate\Foundation\Composer');

            $composer->shouldReceive('dumpAutoloads');

            return $composer;
        });

        if ($this->laravelVersion === 4) {
            $container->singleton('command.dump-autoload', function () {
                $command = m::mock('\Illuminate\Console\Command');

                $command->shouldReceive('setLaravel')
                    ->shouldReceive('setApplication')
                    ->shouldReceive('isEnabled')->andReturn(true)
                    ->shouldReceive('getDefinition')->andReturn([])
                    ->shouldReceive('getName')->andReturn('dump-autoload')
                    ->shouldReceive('getAliases')->andReturn([])
                    ->shouldReceive('run');

                return $command;
            });

            $events = $container['events'];

            $events->listen('artisan.start', function($artisan) {
                $artisan->resolveCommands(['command.dump-autoload']);
            });
        }

        $databaseServiceProvider = new DatabaseServiceProvider($container);
        $databaseServiceProvider->register();

        $migrationServiceProvider = new MigrationServiceProvider($container);
        $migrationServiceProvider->register();

        return $container;
    }

    /**
     * @param \tomzx\LaravelMigration\BaseApplication $container
     */
    private function loadMigrations(BaseApplication $container)
    {
        $files = glob($container->databasePath() . '/migrations/*.php');
        foreach ($files as $file) {
            require_once $file;
        }
    }
}
