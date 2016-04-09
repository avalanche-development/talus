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
        $this->emptySwagger = fopen('empty-swagger.json', 'w+');
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
     * @expectedExceptionMessage logger must be instance of LoggerInterface
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
     * @expectedExceptionMessage missing swagger information
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

    public function testGetSwaggerSpecReadable()
    {
        $reflectedTalus = new ReflectionClass('Jacobemerick\Talus\Talus');
        $reflectedSwaggerSpec = $reflectedTalus->getMethod('getSwaggerSpec');
        $reflectedSwaggerSpec->setAccessible(true);

        $talus = new Talus([
            'swagger' => $this->emptySwagger,
        ]);

        $expectedSpec = (object) [
            'key' => 'value',
        ];
        $encodedSpec = json_encode($expectedSpec);
        $swagger = fopen('empty-swagger-readable.json', 'w+');
        fwrite($swagger, $encodedSpec);
        rewind($swagger);
        $spec = $reflectedSwaggerSpec->invokeArgs($talus, [$swagger]);

        $this->assertEquals($expectedSpec, $spec);

        fclose($swagger);
        unlink('empty-swagger-readable.json');
    }

    /**
     * @expectedException DomainException
     * @expectedExceptionMessage swagger stream is not readable
     */
    public function testGetSwaggerSpecNotReadable()
    {
        $reflectedTalus = new ReflectionClass('Jacobemerick\Talus\Talus');
        $reflectedSwaggerSpec = $reflectedTalus->getMethod('getSwaggerSpec');
        $reflectedSwaggerSpec->setAccessible(true);

        $talus = new Talus([
            'swagger' => $this->emptySwagger,
        ]);

        $expectedSpec = (object) [
            'key' => 'value',
        ];
        $encodedSpec = json_encode($expectedSpec);
        $swagger = fopen('empty-swagger-not-readable.json', 'w');
        fwrite($swagger, $encodedSpec);
        rewind($swagger);

        try {
            $reflectedSwaggerSpec->invokeArgs($talus, [$swagger]);
        } catch (Exception $e) {
            throw $e;
        } finally {
            fclose($swagger);
            unlink('empty-swagger-not-readable.json');
        }
    }

    /**
     * @expectedException DomainException
     * @expectedExceptionMessage swagger stream is not parseable
     */
    public function testGetSwaggerSpecInvalidJson()
    {
        $reflectedTalus = new ReflectionClass('Jacobemerick\Talus\Talus');
        $reflectedSwaggerSpec = $reflectedTalus->getMethod('getSwaggerSpec');
        $reflectedSwaggerSpec->setAccessible(true);

        $talus = new Talus([
            'swagger' => $this->emptySwagger,
        ]);
        fclose($this->emptySwagger);

        $content = 'words';
        $swagger = fopen('empty-swagger-invalid-json.json', 'w+');
        fwrite($swagger, $content);
        rewind($swagger);

        try {
            $reflectedSwaggerSpec->invokeArgs($talus, [$swagger]);
        } catch (Exception $e) {
            throw $e;
        } finally {
            fclose($swagger);
            unlink('empty-swagger-invalid-json.json');
        }
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
        $this->emptySwagger = fopen('empty-swagger.json', 'w');
        fclose($this->emptySwagger);
        unlink('empty-swagger.json');
    }
}
