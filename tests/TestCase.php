<?php

namespace Tests;

abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        // Переменные терминала не должны перенаправить RefreshDatabase в рабочую БД.
        $connection = $_ENV['DB_CONNECTION'] ?? 'sqlite';
        if ($connection === 'mysql') {
            $app['config']->set('database.default', 'mysql');
            $app['config']->set('database.connections.mysql.database', 'hspm_test');
            $app['config']->set('database.connections.mysql.host', '127.0.0.1');
            $app['config']->set('database.connections.mysql.url', null);
            $app['config']->set('database.connections.mysql.port', 3306);
            $app['config']->set('database.connections.mysql.username', 'root');
        } else {
            $app['config']->set('database.default', 'sqlite');
            $app['config']->set('database.connections.sqlite.database', ':memory:');
        }
        $app['db']->purge();

        return $app;
    }
}
