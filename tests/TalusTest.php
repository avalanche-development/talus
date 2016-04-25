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

    public function testConstructSetsNullContainer()
    {
        $this->markTestIncomplete('NullContainer not yet implemented (if even a good idea)');
    }

    public function testConstructSetsContainer()
    {
        $container = $this->getMock('Interop\Container\ContainerInterface');
        $talus = new Talus([
            'container' => $container,
            'swagger' => $this->emptySwagger,
        ]);

        $this->assertAttributeSame($container, 'container', $talus);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage container must be instance of ContainerInterface
     */
    public function testConstructValidatesContainer()
    {
        $container = new stdclass();
        $talus = new Talus([
            'container' => $container,
            'swagger' => $this->emptySwagger,
        ]);
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

    public function testAddMiddleware()
    {
        $middleware = function ($req, $res) {};
        $talus = new Talus([
            'swagger' => $this->emptySwagger,
        ]);
        $talus->addMiddleware($middleware);

        $this->assertAttributeSame([$middleware], 'middlewareStack', $talus);
    }

    /**
     * @expectedException DomainException
     * @expectedExceptionMessage middleware must handle request and response
     */
    public function testAddMiddlewareBadClosure()
    {
        $this->markTestIncomplete('Needs to add validation for middleware closure');
    }

    public function testAddMiddlewareStacking()
    {
        $middlewareStack = [
            function ($req, $res) {},
            function ($req, $res) {},
        ];
        $talus = new Talus([
            'swagger' => $this->emptySwagger,
        ]);
        array_walk($middlewareStack, [$talus, 'addMiddleware']);

        $this->assertAttributeSame($middlewareStack, 'middlewareStack', $talus);
    }

    public function testGetRequest()
    {
        $reflectedTalus = new ReflectionClass('Jacobemerick\Talus\Talus');
        $reflectedRequest = $reflectedTalus->getMethod('getRequest');
        $reflectedRequest->setAccessible(true);

        $talus = new Talus([
            'swagger' => $this->emptySwagger,
        ]);
        $request = $reflectedRequest->invoke($talus);

        $this->assertInstanceOf('Psr\Http\Message\RequestInterface', $request);
    }

    public function testGetResponse()
    {
        $reflectedTalus = new ReflectionClass('Jacobemerick\Talus\Talus');
        $reflectedResponse = $reflectedTalus->getMethod('getResponse');
        $reflectedResponse->setAccessible(true);

        $talus = new Talus([
            'swagger' => $this->emptySwagger,
        ]);
        $response = $reflectedResponse->invoke($talus);

        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $response);
    }

    public function testMapHttpMethod()
    {
        $reflectedTalus = new ReflectionClass('Jacobemerick\Talus\Talus');
        $reflectedMapHttpMethod = $reflectedTalus->getMethod('mapHttpMethod');
        $reflectedMapHttpMethod->setAccessible(true);

        $mockRequest = $this->getMock('Psr\Http\Message\RequestInterface');
        $mockRequest->method('getMethod')->willReturn('POST');

        $talus = new Talus([
            'swagger' => $this->emptySwagger,
        ]);
        $httpMethodName = $reflectedMapHttpMethod->invokeArgs($talus, [$mockRequest]);

        $this->assertEquals('getPost', $httpMethodName);
    }

    public function tearDown()
    {
        $this->emptySwagger = fopen('empty-swagger.json', 'w');
        fclose($this->emptySwagger);
        unlink('empty-swagger.json');
    }
}
