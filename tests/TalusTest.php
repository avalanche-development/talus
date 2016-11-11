<?php

namespace AvalancheDevelopment\Talus;

use Exception;
use PHPUnit_Framework_TestCase;
use ReflectionClass;
use stdclass;

use AvalancheDevelopment\CrashPad\ErrorHandler;
use Interop\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class TalusTest extends PHPUnit_Framework_TestCase
{

    public function testIsInstanceOfTalus()
    {
        $container = $this->createMock(ContainerInterface::class);

        $talus = new Talus([], $container);

        $this->assertInstanceOf('AvalancheDevelopment\Talus\Talus', $talus);
    }

    public function testTalusImplementsLoggerInterface()
    {
        $container = $this->createMock(ContainerInterface::class);

        $talus = new Talus([], $container);

        $this->assertInstanceOf('Psr\Log\LoggerAwareInterface', $talus);
    }

    public function testConstructSetsContainer()
    {
        $container = $this->createMock(ContainerInterface::class);

        $talus = new Talus([], $container);

        $this->assertAttributeSame($container, 'container', $talus);
    }

    public function testConstructSetsNullLogger()
    {
        $container = $this->createMock(ContainerInterface::class);

        $talus = new Talus([], $container);

        $this->assertAttributeInstanceOf('Psr\Log\NullLogger', 'logger', $talus);
    }

    public function testConstructSetsErrorHandler()
    {
        $container = $this->createMock(ContainerInterface::class);

        $talus = new Talus([], $container);

        $this->assertAttributeInstanceOf(ErrorHandler::class, 'errorHandler', $talus);
    }

    public function testConstructSetsSwagger()
    {
        $container = $this->createMock(ContainerInterface::class);
        $swagger = ['swagger'];

        $talus = new Talus($swagger, $container);

        $this->assertAttributeEquals($swagger, 'swagger', $talus);
    }

    public function testSetErrorHandler()
    {
        $errorHandler = function () {};

        $talus = $this->getMockBuilder(Talus::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();
        $talus->setErrorHandler($errorHandler);

        $this->assertAttributeSame($errorHandler, 'errorHandler', $talus);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Error handler must be callable
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

        $talus = $this->getMockBuilder(Talus::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();
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

        $talus = $this->getMockBuilder(Talus::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();
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

        $talus = $this->getMockBuilder(Talus::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();
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

        $talus = $this->getMockBuilder(Talus::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();
        $reflectedOutput->invokeArgs($talus, [$mockResponse]);
    }

    public function testInvoke()
    {
        $this->markTestIncomplete('Talus::__invoke() is not yet covered');
    }

    public function testGetRequest()
    {
        $reflectedTalus = new ReflectionClass('AvalancheDevelopment\Talus\Talus');
        $reflectedRequest = $reflectedTalus->getMethod('getRequest');
        $reflectedRequest->setAccessible(true);

        $talus = $this->getMockBuilder(Talus::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();
        $request = $reflectedRequest->invoke($talus);

        $this->assertInstanceOf('Psr\Http\Message\RequestInterface', $request);
    }

    public function testGetResponse()
    {
        $reflectedTalus = new ReflectionClass('AvalancheDevelopment\Talus\Talus');
        $reflectedResponse = $reflectedTalus->getMethod('getResponse');
        $reflectedResponse->setAccessible(true);

        $talus = $this->getMockBuilder(Talus::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();
        $response = $reflectedResponse->invoke($talus);

        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $response);
    }
}
