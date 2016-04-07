<?php

namespace Jacobemerick\Talus;

use PHPUnit_Framework_TestCase;
use stdclass;

class TalusTest extends PHPUnit_Framework_TestCase
{

    public function testIsInstanceOfTalus()
    {
        $talus = new Talus([]);

        $this->assertInstanceOf('Jacobemerick\Talus\Talus', $talus);
    }

    public function testTalusImplementsLoggerInterface()
    {
        $talus = new Talus([]);

        $this->assertInstanceOf('Psr\Log\LoggerAwareInterface', $talus);
    }

    public function testConstructSetsNullLogger()
    {
        $talus = new Talus([]);

        $this->assertAttributeInstanceOf('Psr\Log\NullLogger', 'logger', $talus);
    }

    public function testConstructSetsLogger()
    {
        $logger = $this->getMock('Psr\Log\LoggerInterface');
        $talus = new Talus([
            'logger' => $logger,
        ]);

        $this->assertAttributeSame($logger, 'logger', $talus);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructValidatesLogger()
    {
        $logger = new stdclass();
        $talus = new Talus([
            'logger' => $logger,
        ]);
    }

    public function testSetLogger()
    {
        $logger = $this->getMock('Psr\Log\LoggerInterface');
        $talus = new Talus([]);
        $talus->setLogger($logger);

        $this->assertAttributeSame($logger, 'logger', $talus);
    }
}
