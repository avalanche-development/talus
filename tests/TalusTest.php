<?php

namespace AvalancheDevelopment\Talus;

use Exception;
use PHPUnit_Framework_TestCase;
use ReflectionClass;
use stdclass;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class TalusTest extends PHPUnit_Framework_TestCase
{

    public function testIsInstanceOfTalus()
    {
        $talus = new Talus([
            'swagger' => ['swagger'],
        ]);

        $this->assertInstanceOf('AvalancheDevelopment\Talus\Talus', $talus);
    }

    public function testTalusImplementsLoggerInterface()
    {
        $talus = new Talus([
            'swagger' => ['swagger'],
        ]);

        $this->assertInstanceOf('Psr\Log\LoggerAwareInterface', $talus);
    }

    public function testConstructSetsContainer()
    {
        $container = $this->createMock('Interop\Container\ContainerInterface');
        $talus = new Talus([
            'container' => $container,
            'swagger' => ['swagger'],
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
            'swagger' => ['swagger'],
        ]);
    }

    public function testConstructSetsNullLogger()
    {
        $talus = new Talus([
            'swagger' => ['swagger'],
        ]);

        $this->assertAttributeInstanceOf('Psr\Log\NullLogger', 'logger', $talus);
    }

    public function testConstructSetsLogger()
    {
        $logger = $this->createMock('Psr\Log\LoggerInterface');
        $talus = new Talus([
            'logger' => $logger,
            'swagger' => ['swagger'],
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
            'swagger' => ['swagger'],
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
        $swagger = ['swagger'];

        $talus = new Talus([
            'swagger' => $swagger,
        ]);

        $this->assertAttributeEquals($swagger, 'swagger', $talus);
    }

    public function testSetErrorHandler()
    {
        $errorHandler = function ($req, $res, $e) {};
        $talus = new Talus([
            'swagger' => ['swagger'],
        ]);
        $talus->setErrorHandler($errorHandler);

        $this->assertAttributeSame($errorHandler, 'errorHandler', $talus);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage error handler must be callable
     */
    public function testSetErrorHandlerBailsOnBadHandler()
    {
        $errorHandler = false;

        $talus = $this->getMockBuilder(Talus::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();
        $talus->setErrorHandler($errorHandler);
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
            'swagger' => ['swagger'],
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
            'swagger' => ['swagger'],
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
            'swagger' => ['swagger'],
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
            'swagger' => ['swagger'],
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
            'swagger' => ['swagger'],
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
            'swagger' => ['swagger'],
        ]);
        $response = $reflectedResponse->invoke($talus);

        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $response);
    }

    public function testHandleErrorDefault()
    {
        $exception = new Exception('test error');

        $stream = $this->createMock(StreamInterface::class);
        $stream->expects($this->once())
            ->method('write')
            ->with('Error: test error');

        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getBody')
            ->willReturn($stream);

        $reflectedTalus = new ReflectionClass(Talus::class);
        $reflectedHandleError = $reflectedTalus->getMethod('handleError');
        $reflectedHandleError->setAccessible(true);

        $talus = $this->getMockBuilder(Talus::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();

        $result = $reflectedHandleError->invokeArgs($talus, [ $request, $response, $exception ]);
        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $result);
    }

    public function testHandleErrorCustom()
    {
        $exception = new Exception('test error');
        $errorHandler = function ($req, $res, $e) {
            $res->getBody()->write("SOME ERROR: {$e->getMessage()}");
            return $res;
        };

        $stream = $this->createMock(StreamInterface::class);
        $stream->expects($this->once())
            ->method('write')
            ->with('SOME ERROR: test error');

        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getBody')
            ->willReturn($stream);

        $reflectedTalus = new ReflectionClass(Talus::class);
        $reflectedErrorHandler = $reflectedTalus->getProperty('errorHandler');
        $reflectedErrorHandler->setAccessible(true);
        $reflectedHandleError = $reflectedTalus->getMethod('handleError');
        $reflectedHandleError->setAccessible(true);

        $talus = $this->getMockBuilder(Talus::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();

        $reflectedErrorHandler->setValue($talus, $errorHandler);
        $result = $reflectedHandleError->invokeArgs($talus, [ $request, $response, $exception ]);
        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $result);
    }
}
