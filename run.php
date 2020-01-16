<?php

declare(strict_types=1);

date_default_timezone_set('Europe/Prague');
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';

$log = new \Example\Log();
$connection = new Example\Synapse($log);

function runQueries(Example\Synapse $connection): \Generator
{
    $connection->connectToMaster();
    yield 'CREATE LOGIN MY_USER WITH PASSWORD = \'strong4PassWord1\'';
    $connection->connectDefault();
    yield 'CREATE USER MY_USER FOR LOGIN MY_USER';
    yield 'CREATE ROLE MY_ROLE';
    yield 'EXEC sp_addrolemember \'MY_ROLE\', \'MY_USER\'';
    $connection->connectAsMyUser();
    yield  <<<EOT
CREATE TABLE "test" (
    "id" varchar(8000) NOT NULL,
    "name" varchar(8000) NOT NULL,
    "_timestamp" datetimeoffset,
    PRIMARY KEY NONCLUSTERED("id") NOT ENFORCED
)
EOT;
    $connection->connectDefault();
    yield 'EXEC sp_droprolemember \'MY_ROLE\', \'MY_USER\'';
    yield 'DROP USER "MY_USER"';
    $connection->connectToMaster();
    yield 'DROP LOGIN "MY_USER"';
    $connection->connectDefault();
    yield 'DROP ROLE "MY_ROLE"';
}

foreach (runQueries($connection) as $query) {
    $log->info(sprintf('RUN: %s', $query));
    try {
        $connection->runQuery($query);
        $log->info('Success');
    } catch (\Throwable $e) {
        $log->error('Error: ' . $e->getMessage());
    }
}
