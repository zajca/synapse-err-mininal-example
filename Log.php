<?php

declare(strict_types=1);

namespace Example;

class Log extends \Psr\Log\AbstractLogger
{
    /**
     * @inheritDoc
     */
    public function log($level, $message, array $context = [])
    {
        echo sprintf(
                '%s %s',
                (new \DateTime('now'))->format('H:i:s'),
                $message
            ) . PHP_EOL;
    }
}
