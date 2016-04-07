<?php

/**
 * What what
 */

namespace Jacobemerick\Talus;

use InvalidArgumentException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Talus implements LoggerAwareInterface
{

    /** @var LoggerInterface */
    protected $logger;

    /**
     * @param array $config
     */
    public function __construct(array $config)
    {
        if (!empty($config['logger'])) {
            if (!($config['logger'] instanceof LoggerInterface)) {
                throw new InvalidArgumentException('logger must be instance of LoggerInterface');
            }
            $this->logger = $config['logger'];
        } else {
            $this->logger = new NullLogger();
        }
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function addMiddleware()
    {
        // add some middleware somehow
    }

    public function run()
    {
        // do stuff
    }
}
