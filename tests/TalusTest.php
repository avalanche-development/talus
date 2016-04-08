<?php

namespace Jacobemerick\Talus;

use PHPUnit_Framework_TestCase;
use ReflectionClass;
use stdclass;
use Swagger\Document as SwaggerDocument;

class TalusTest extends PHPUnit_Framework_TestCase
{

    protected $emptySwagger;

    public function setUp()
    {
        $this->emptySwagger = fopen('php://memory', 'w+');
        fwrite($this->emptySwagger, '{}');
        rewind($this->emptySwagger);
    }

    public function testIsInstanceOfTalus()
    {
        $talus = new Talus([
            'swagger' => $this->emptySwagger,
        ]);

        $this->assertInstanceOf('Jacobemerick\Talus\Talus', $talus);
    }

    public function testTalusImplementsLoggerInterface()
    {
        $talus = new Talus([
            'swagger' => $this->emptySwagger,
        ]);

        $this->assertInstanceOf('Psr\Log\LoggerAwareInterface', $talus);
    }

    public function testConstructSetsNullLogger()
    {
        $talus = new Talus([
            'swagger' => $this->emptySwagger,
        ]);

        $this->assertAttributeInstanceOf('Psr\Log\NullLogger', 'logger', $talus);
    }

    public function testConstructSetsLogger()
    {
        $logger = $this->getMock('Psr\Log\LoggerInterface');
        $talus = new Talus([
            'logger' => $logger,
            'swagger' => $this->emptySwagger,
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
            'swagger' => $this->emptySwagger,
        ]);
    }

    /**
     * @expectedException DomainException
     */
    public function testConstructRequiresSwagger()
    {
        $talus = new Talus([]);
    }

    public function testConstructSetsSwagger()
    {
        $reflectedTalus = new ReflectionClass('Jacobemerick\Talus\Talus');
        $reflectedSwaggerSpec = $reflectedTalus->getMethod('getSwaggerSpec');
        $reflectedSwaggerSpec->setAccessible(true);

        $talus = new Talus([
            'swagger' => $this->emptySwagger,
        ]);
        rewind($this->emptySwagger);
        $spec = $reflectedSwaggerSpec->invokeArgs($talus, [$this->emptySwagger]);
        $swagger = new SwaggerDocument($spec);

        $this->assertAttributeEquals($swagger, 'swagger', $talus);
    }

    public function testSetLogger()
    {
        $logger = $this->getMock('Psr\Log\LoggerInterface');
        $talus = new Talus([
            'swagger' => $this->emptySwagger,
        ]);
        $talus->setLogger($logger);

        $this->assertAttributeSame($logger, 'logger', $talus);
    }

    public function tearDown()
    {
        $this->emptySwagger = fopen('php://memory', 'w');
        fclose($this->emptySwagger);
    }
}
