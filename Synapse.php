<?php

declare(strict_types=1);

namespace Example;

use Psr\Log\LoggerInterface;

final class Synapse
{
    /**
     * @var resource
     */
    private $connection;

    private LoggerInterface $log;

    /**
     * Synapse constructor.
     *
     * @param resource $connection
     */
    public function __construct(LoggerInterface $log)
    {
        $this->log = $log;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    public function connectAsMyUser(): void
    {
        sleep(5);
        $this->connect(
            'MY_USER', 'strong4PassWord1', [
                'database' => getenv('SYNAPSE_DATABASE'),
            ]
        );
    }

    public function connect(string $user, string $password, array $options = []): void
    {
        $this->disconnect();
        $connectionInfo = [
            'UID' => $user,
            'pwd' => $password,
            'Database' => $options['database'],
            'LoginTimeout' => 30,
            'Encrypt' => 1,
            'TrustServerCertificate' => 0,
        ];
        $maxBackoffAttempts = 5;
        $attemptNumber = 0;

        do {
            $this->disconnect();
            if ($attemptNumber > 0) {
                sleep(2 ** $attemptNumber);
            }
            $strInfo = [];
            foreach ($connectionInfo as $key => $item) {
                $strInfo[] = $key . '=>' . $item;
            }
            $this->log->info(
                sprintf('Connect SRV:"%s", INFO: "%s"', getenv('SYNAPSE_SERVER'), implode(';', $strInfo))
            );
            $this->connection = \sqlsrv_connect(getenv('SYNAPSE_SERVER'), $connectionInfo);

            if ($this->connection === false) {
                $attemptNumber++;
                $msg = [];
                foreach (sqlsrv_errors() as $error) {
                    $msg[] = $error['message'];
                }
                $this->log->error(
                    sprintf('Initializing Synapse connection failed: %s', implode(', ', $msg))
                );
                if ($attemptNumber > $maxBackoffAttempts) {
                    return;
                }
            }
        } while ($this->connection === false);
    }

    private function disconnect(): void
    {
        if (is_resource($this->connection)) {
            sqlsrv_close($this->connection);
            $this->connection = null;
        }
    }

    public function connectDefault(): void
    {
        $this->connect(
            getenv('SYNAPSE_UID'),
            getenv('SYNAPSE_PWD'),
            [
                'database' => getenv('SYNAPSE_DATABASE'),
            ]
        );
    }

    public function connectToMaster(): void
    {
        $this->connect(
            getenv('SYNAPSE_UID'),
            getenv('SYNAPSE_PWD'),
            [
                'database' => 'master',
            ]
        );
    }

    public function runQuery(string $sql): void
    {
        $result = sqlsrv_query($this->connection, $sql);
        $this->handleError($result);
    }

    /**
     * @param $result
     * @throws \Exception
     */
    private function handleError($result): void
    {
        if ($result === false) {
            $msg = [];
            foreach (sqlsrv_errors() as $error) {
                $msg[] = $error['message'];
            }
            throw new \Exception(implode(', ', $msg));
        }
    }
}
