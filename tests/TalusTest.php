<?php

namespace AvalancheDevelopment\Talus;

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

        $this->assertInstanceOf('AvalancheDevelopment\Talus\Talus', $talus);
    }

    public function testTalusImplementsLoggerInterface()
    {
        $talus = new Talus([
            'swagger' => $this->emptySwagger,
        ]);

        $this->assertInstanceOf('Psr\Log\LoggerAwareInterface', $talus);
    }

    public function testConstructSetsContainer()
    {
        $container = $this->createMock('Interop\Container\ContainerInterface');
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
        $logger = $this->createMock('Psr\Log\LoggerInterface');
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
        $reflectedTalus = new ReflectionClass('AvalancheDevelopment\Talus\Talus');
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
        $reflectedTalus = new ReflectionClass('AvalancheDevelopment\Talus\Talus');
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
        $reflectedTalus = new ReflectionClass('AvalancheDevelopment\Talus\Talus');
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
        $reflectedTalus = new ReflectionClass('AvalancheDevelopment\Talus\Talus');
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

    public function testSetErrorHandler()
    {
        $errorHandler = function ($req, $res, $e) {};
        $talus = new Talus([
            'swagger' => $this->emptySwagger,
        ]);
        $talus->setErrorHandler($errorHandler);

        $this->assertAttributeSame($errorHandler, 'errorHandler', $talus);
    }

    public function testRun()
    {
        $this->markTestIncomplete('Talus::run() is not yet covered');
    }

    public function testOutputResponseSendsStatus()
    {
        $statusCode = 403;
        $reasonPhrase = 'Forbidden';

        ob_start();
        print_r([
            sprintf('HTTP/1.1 %d %s', $statusCode, $reasonPhrase),
            true,
            $statusCode,
        ]);
        $expectedHeaders = ob_get_clean();

        $reflectedTalus = new ReflectionClass('AvalancheDevelopment\Talus\Talus');
        $reflectedOutput = $reflectedTalus->getMethod('outputResponse');
        $reflectedOutput->setAccessible(true);

        $mockResponse = $this->createMock('Psr\Http\Message\ResponseInterface');
        $mockResponse->method('getStatusCode')->willReturn($statusCode);
        $mockResponse->method('getReasonPhrase')->willReturn($reasonPhrase);

        $this->expectOutputString($expectedHeaders);

        $talus = new Talus([
            'swagger' => $this->emptySwagger,
        ]);
        $reflectedOutput->invokeArgs($talus, [$mockResponse]);
    }

    public function testOutputResponseSendsHeaders()
    {
        $statusCode = 200;
        $reasonPhrase = 'OK';
        $headers = [
            'Content-Type' => ['application/json'],
            'Content-Language' => ['en/us'],
        ];

        ob_start();
        print_r([
            sprintf('HTTP/1.1 %d %s', $statusCode, $reasonPhrase),
            true,
            $statusCode,
        ]);
        foreach ($headers as $headerKey => $headerValue) {
            print_r([
                sprintf("%s: %s", $headerKey, implode(', ', $headerValue)),
                true,
            ]);
        }
        $expectedHeaders = ob_get_clean();

        $reflectedTalus = new ReflectionClass('AvalancheDevelopment\Talus\Talus');
        $reflectedOutput = $reflectedTalus->getMethod('outputResponse');
        $reflectedOutput->setAccessible(true);

        $mockResponse = $this->createMock('Psr\Http\Message\ResponseInterface');
        $mockResponse->method('getStatusCode')->willReturn($statusCode);
        $mockResponse->method('getReasonPhrase')->willReturn($reasonPhrase);
        $mockResponse->method('getHeaders')->willReturn($headers);

        $this->expectOutputString($expectedHeaders);

        $talus = new Talus([
            'swagger' => $this->emptySwagger,
        ]);
        $reflectedOutput->invokeArgs($talus, [$mockResponse]);
    }

    public function testOutputResponseSendsMultipleHeaders()
    {
        $statusCode = 200;
        $reasonPhrase = 'OK';
        $headers = [
            'Content-Type' => ['application/json', 'application/json+xml'],
        ];

        ob_start();
        print_r([
            sprintf('HTTP/1.1 %d %s', $statusCode, $reasonPhrase),
            true,
            $statusCode,
        ]);
        foreach ($headers as $headerKey => $headerValue) {
            print_r([
                sprintf("%s: %s", $headerKey, implode(', ', $headerValue)),
                true,
            ]);
        }
        $expectedHeaders = ob_get_clean();

        $reflectedTalus = new ReflectionClass('AvalancheDevelopment\Talus\Talus');
        $reflectedOutput = $reflectedTalus->getMethod('outputResponse');
        $reflectedOutput->setAccessible(true);

        $mockResponse = $this->createMock('Psr\Http\Message\ResponseInterface');
        $mockResponse->method('getStatusCode')->willReturn($statusCode);
        $mockResponse->method('getReasonPhrase')->willReturn($reasonPhrase);
        $mockResponse->method('getHeaders')->willReturn($headers);

        $this->expectOutputString($expectedHeaders);

        $talus = new Talus([
            'swagger' => $this->emptySwagger,
        ]);
        $reflectedOutput->invokeArgs($talus, [$mockResponse]);
    }

    public function testOutputResponseSendsBody()
    {
        $statusCode = 200;
        $reasonPhrase = 'OK';
        $body = 'Hello world!';

        ob_start();
        print_r([
            sprintf('HTTP/1.1 %d %s', $statusCode, $reasonPhrase),
            true,
            $statusCode,
        ]);
        echo $body;
        $expectedOutput = ob_get_clean();

        $reflectedTalus = new ReflectionClass('AvalancheDevelopment\Talus\Talus');
        $reflectedOutput = $reflectedTalus->getMethod('outputResponse');
        $reflectedOutput->setAccessible(true);

        $mockResponse = $this->createMock('Psr\Http\Message\ResponseInterface');
        $mockResponse->method('getStatusCode')->willReturn($statusCode);
        $mockResponse->method('getReasonPhrase')->willReturn($reasonPhrase);
        $mockResponse->method('getBody')->willReturn($body);

        $this->expectOutputString($expectedOutput);

        $talus = new Talus([
            'swagger' => $this->emptySwagger,
        ]);
        $reflectedOutput->invokeArgs($talus, [$mockResponse]);
    }

    public function testInvoke()
    {
        $this->markTestIncomplete('Talus::__invoke() is not yet covered');
    }

    public function testMatchPath()
    {
        $this->markTestIncomplete('Talus::matchPath() is not yet covered');
    }

    public function testGetRequest()
    {
        $reflectedTalus = new ReflectionClass('AvalancheDevelopment\Talus\Talus');
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
        $reflectedTalus = new ReflectionClass('AvalancheDevelopment\Talus\Talus');
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
        $reflectedTalus = new ReflectionClass('AvalancheDevelopment\Talus\Talus');
        $reflectedMapHttpMethod = $reflectedTalus->getMethod('mapHttpMethod');
        $reflectedMapHttpMethod->setAccessible(true);

        $mockRequest = $this->createMock('Psr\Http\Message\RequestInterface');
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
