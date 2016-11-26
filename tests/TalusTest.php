<?php

namespace AvalancheDevelopment\Talus;

use Exception;
use PHPUnit_Framework_TestCase;
use ReflectionClass;
use stdclass;

use AvalancheDevelopment\CrashPad\ErrorHandler;
use AvalancheDevelopment\SwaggerRouterMiddleware\Router;
use AvalancheDevelopment\SwaggerHeaderMiddleware\Header;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class TalusTest extends PHPUnit_Framework_TestCase
{

    public function testIsInstanceOfTalus()
    {
        $talus = new Talus([]);

        $this->assertInstanceOf(Talus::class, $talus);
    }

    public function testTalusImplementsLoggerInterface()
    {
        $talus = new Talus([]);

        $this->assertInstanceOf(LoggerAwareInterface::class, $talus);
    }

    public function testConstructSetsNullLogger()
    {
        $talus = new Talus([]);

        $this->assertAttributeInstanceOf(NullLogger::class, 'logger', $talus);
    }

    public function testConstructSetsErrorHandler()
    {
        $talus = new Talus([]);

        $this->assertAttributeInstanceOf(ErrorHandler::class, 'errorHandler', $talus);
    }

    public function testConstructSetsSwagger()
    {
        $swagger = ['swagger'];

        $talus = new Talus($swagger);

        $this->assertAttributeEquals($swagger, 'swagger', $talus);
    }

    public function testAddController()
    {
        $operationId = 'getThings';
        $controller = function () {};

        $reflectedTalus = new ReflectionClass(Talus::class);
        $reflectedControllerList = $reflectedTalus->getProperty('controllerList');
        $reflectedControllerList->setAccessible(true);

        $talus = $this->getMockBuilder(Talus::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();
        $talus->addController($operationId, $controller);

        $controllerList = $reflectedControllerList->getValue($talus);

        $this->assertArrayHasKey('getThings', $controllerList);
        $this->assertSame($controller, $controllerList['getThings']);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Controller must be callable
     */
    public function testAddControllerBailsOnBadController()
    {
        $controller = false;

        $talus = $this->getMockBuilder(Talus::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();
        $talus->addController('', $controller);
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

    public function testRunLogsIncomingRun()
    {
        $reflectedTalus = new ReflectionClass(Talus::class);
        $reflectedLogger = $reflectedTalus->getProperty('logger');
        $reflectedLogger->setAccessible(true);

        $mockRequest = $this->createMock(RequestInterface::class);
        $mockResponse = $this->createMock(ResponseInterface::class);

        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockLogger->expects($this->once())
            ->method('debug')
            ->with('Talus: walking through swagger doc looking for dispatch');

        $talus = $this->getMockBuilder(Talus::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'buildMiddlewareStack',
                'callStack',
                'getRequest',
                'getResponse',
                'outputResponse',
            ])
            ->getMock();

        $reflectedLogger->setValue($talus, $mockLogger);

        $talus->expects($this->once())
            ->method('buildMiddlewareStack');

        $talus->expects($this->once())
            ->method('callStack')
            ->willReturn($mockResponse);

        $talus->expects($this->once())
            ->method('getRequest')
            ->willReturn($mockRequest);

        $talus->expects($this->once())
            ->method('getResponse')
            ->willReturn($mockResponse);

        $talus->run();
    }

    public function testRunCallsToStack()
    {
        $reflectedTalus = new ReflectionClass(Talus::class);
        $reflectedLogger = $reflectedTalus->getProperty('logger');
        $reflectedLogger->setAccessible(true);

        $mockRequest = $this->createMock(RequestInterface::class);
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockLogger = $this->createMock(LoggerInterface::class);

        $talus = $this->getMockBuilder(Talus::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'buildMiddlewareStack',
                'callStack',
                'getRequest',
                'getResponse',
                'outputResponse',
            ])
            ->getMock();

        $reflectedLogger->setValue($talus, $mockLogger);

        $talus->expects($this->once())
            ->method('buildMiddlewareStack');

        $talus->expects($this->once())
            ->method('callStack')
            ->with($mockRequest, $mockResponse)
            ->willReturn($mockResponse);

        $talus->expects($this->once())
            ->method('getRequest')
            ->willReturn($mockRequest);

        $talus->expects($this->once())
            ->method('getResponse')
            ->willReturn($mockResponse);

        $talus->run();
    }

    public function testRunHandlesCallStackError()
    {
        $reflectedTalus = new ReflectionClass(Talus::class);
        $reflectedLogger = $reflectedTalus->getProperty('logger');
        $reflectedLogger->setAccessible(true);
        $reflectedErrorHandler = $reflectedTalus->getProperty('errorHandler');
        $reflectedErrorHandler->setAccessible(true);

        $mockRequest = $this->createMock(RequestInterface::class);
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockException = $this->createMock(Exception::class);

        $mockErrorHandler = $this->getMockBuilder(stdclass::class)
            ->setMethods(['__invoke'])
            ->getMock();
        $mockErrorHandler->expects($this->once())
            ->method('__invoke')
            ->with($mockRequest, $mockResponse, $mockException)
            ->willReturn($mockResponse);

        $talus = $this->getMockBuilder(Talus::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'buildMiddlewareStack',
                'callStack',
                'getRequest',
                'getResponse',
                'outputResponse',
            ])
            ->getMock();

        $reflectedLogger->setValue($talus, $mockLogger);
        $reflectedErrorHandler->setValue($talus, $mockErrorHandler);

        $talus->expects($this->once())
            ->method('buildMiddlewareStack');

        $talus->expects($this->once())
            ->method('callStack')
            ->with($mockRequest, $mockResponse)
            ->will($this->throwException($mockException));

        $talus->expects($this->once())
            ->method('getRequest')
            ->willReturn($mockRequest);

        $talus->expects($this->once())
            ->method('getResponse')
            ->willReturn($mockResponse);

        $talus->run();
    }

    public function testRunAttachesLoggerToErrorHandler()
    {
        $reflectedTalus = new ReflectionClass(Talus::class);
        $reflectedLogger = $reflectedTalus->getProperty('logger');
        $reflectedLogger->setAccessible(true);
        $reflectedErrorHandler = $reflectedTalus->getProperty('errorHandler');
        $reflectedErrorHandler->setAccessible(true);

        $mockRequest = $this->createMock(RequestInterface::class);
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockException = $this->createMock(Exception::class);

        $mockErrorHandler = $this->getMockBuilder(LoggerAwareInterface::class)
            ->setMethods([
                '__invoke',
                'setLogger',
            ])
            ->getMock();
        $mockErrorHandler->method('__invoke')
            ->willReturn($mockResponse);
        $mockErrorHandler->expects($this->once())
            ->method('setLogger')
            ->with($mockLogger);

        $talus = $this->getMockBuilder(Talus::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'buildMiddlewareStack',
                'callStack',
                'getRequest',
                'getResponse',
                'outputResponse',
            ])
            ->getMock();

        $reflectedLogger->setValue($talus, $mockLogger);
        $reflectedErrorHandler->setValue($talus, $mockErrorHandler);

        $talus->expects($this->once())
            ->method('buildMiddlewareStack');

        $talus->expects($this->once())
            ->method('callStack')
            ->with($mockRequest, $mockResponse)
            ->will($this->throwException($mockException));

        $talus->expects($this->once())
            ->method('getRequest')
            ->willReturn($mockRequest);

        $talus->expects($this->once())
            ->method('getResponse')
            ->willReturn($mockResponse);

        $talus->run();
    }

    public function testRunOutputsResultOfStack()
    {
        $reflectedTalus = new ReflectionClass(Talus::class);
        $reflectedLogger = $reflectedTalus->getProperty('logger');
        $reflectedLogger->setAccessible(true);

        $mockRequest = $this->createMock(RequestInterface::class);
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockLogger = $this->createMock(LoggerInterface::class);

        $talus = $this->getMockBuilder(Talus::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'buildMiddlewareStack',
                'callStack',
                'getRequest',
                'getResponse',
                'outputResponse',
            ])
            ->getMock();

        $reflectedLogger->setValue($talus, $mockLogger);

        $talus->expects($this->once())
            ->method('buildMiddlewareStack');

        $talus->expects($this->once())
            ->method('callStack')
            ->willReturn($mockResponse);

        $talus->expects($this->once())
            ->method('getRequest')
            ->willReturn($mockRequest);

        $talus->expects($this->once())
            ->method('getResponse')
            ->willReturn($mockResponse);

        $talus->expects($this->once())
            ->method('outputResponse')
            ->with($mockResponse);

        $talus->run();
    }

    public function testBuildMiddlewareStackAddsHeaderMiddleware()
    {
        $reflectedTalus = new ReflectionClass(Talus::class);
        $reflectedBuildMiddlewareStack = $reflectedTalus->getMethod('buildMiddlewareStack');
        $reflectedBuildMiddlewareStack->setAccessible(true);
        $reflectedLogger = $reflectedTalus->getProperty('logger');
        $reflectedLogger->setAccessible(true);
        $reflectedSwagger = $reflectedTalus->getProperty('swagger');
        $reflectedSwagger->setAccessible(true);

        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockSwagger = [ 'some value' ];

        $talus = $this->getMockBuilder(Talus::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'addMiddleware',
            ])
            ->getMock();

        $talus->expects($this->at(0))
            ->method('addMiddleware')
            ->with($this->logicalAnd(
                $this->isInstanceOf(Header::class),
                $this->classHasAttribute('logger', $mockLogger)
            ));

        $reflectedLogger->setValue($talus, $mockLogger);
        $reflectedSwagger->setValue($talus, $mockSwagger);

        $reflectedBuildMiddlewareStack->invoke($talus);
    }

    public function testBuildMiddlewareStackAddsRouterMiddleware()
    {
        $reflectedTalus = new ReflectionClass(Talus::class);
        $reflectedBuildMiddlewareStack = $reflectedTalus->getMethod('buildMiddlewareStack');
        $reflectedBuildMiddlewareStack->setAccessible(true);
        $reflectedLogger = $reflectedTalus->getProperty('logger');
        $reflectedLogger->setAccessible(true);
        $reflectedSwagger = $reflectedTalus->getProperty('swagger');
        $reflectedSwagger->setAccessible(true);

        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockSwagger = [ 'some value' ];

        $talus = $this->getMockBuilder(Talus::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'addMiddleware',
            ])
            ->getMock();

        $talus->expects($this->at(1))
            ->method('addMiddleware')
            ->with($this->logicalAnd(
                $this->isInstanceOf(Router::class),
                $this->classHasAttribute('logger', $mockLogger),
                $this->classHasAttribute('swagger', $mockSwagger)
            ));

        $reflectedLogger->setValue($talus, $mockLogger);
        $reflectedSwagger->setValue($talus, $mockSwagger);

        $reflectedBuildMiddlewareStack->invoke($talus);
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

        $reflectedTalus = new ReflectionClass(Talus::class);
        $reflectedOutput = $reflectedTalus->getMethod('outputResponse');
        $reflectedOutput->setAccessible(true);

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn($statusCode);
        $mockResponse->expects($this->once())
            ->method('getReasonPhrase')
            ->willReturn($reasonPhrase);

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

        $reflectedTalus = new ReflectionClass(Talus::class);
        $reflectedOutput = $reflectedTalus->getMethod('outputResponse');
        $reflectedOutput->setAccessible(true);

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn($statusCode);
        $mockResponse->expects($this->once())
            ->method('getReasonPhrase')
            ->willReturn($reasonPhrase);
        $mockResponse->expects($this->exactly(2))
            ->method('getHeaders')
            ->willReturn($headers);

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

        $reflectedTalus = new ReflectionClass(Talus::class);
        $reflectedOutput = $reflectedTalus->getMethod('outputResponse');
        $reflectedOutput->setAccessible(true);

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn($statusCode);
        $mockResponse->expects($this->once())
            ->method('getReasonPhrase')
            ->willReturn($reasonPhrase);
        $mockResponse->expects($this->exactly(2))
            ->method('getHeaders')
            ->willReturn($headers);

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

        $reflectedTalus = new ReflectionClass(Talus::class);
        $reflectedOutput = $reflectedTalus->getMethod('outputResponse');
        $reflectedOutput->setAccessible(true);

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn($statusCode);
        $mockResponse->expects($this->once())
            ->method('getReasonPhrase')
            ->willReturn($reasonPhrase);
        $mockResponse->expects($this->once())
            ->method('getBody')
            ->willReturn($body);

        $this->expectOutputString($expectedOutput);

        $talus = $this->getMockBuilder(Talus::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();
        $reflectedOutput->invokeArgs($talus, [$mockResponse]);
    }

    public function testInvokeCallsOnController()
    {
        $reflectedTalus = new ReflectionClass(Talus::class);
        $reflectedControllerList = $reflectedTalus->getProperty('controllerList');
        $reflectedControllerList->setAccessible(true);

        $mockRequest = $this->createMock(ServerRequestInterface::class);
        $mockRequest->expects($this->once())
            ->method('getAttribute')
            ->with('swagger')
            ->willReturn([
                'operation' => [
                    'operationId' => 'getThings',
                ],
            ]);

        $mockResponse = $this->createMock(ResponseInterface::class);

        $mockController = $this->getMockBuilder(stdclass::class)
            ->setMethods([ '__invoke' ])
            ->getMock();
        $mockController->expects($this->once())
            ->method('__invoke')
            ->with($mockRequest, $mockResponse);

        $talus = $this->getMockBuilder(Talus::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();
        $reflectedControllerList->setValue($talus, [
            'getThings' => $mockController,
        ]);

        $talus->__invoke($mockRequest, $mockResponse);
    }

    /**
     * @expectedException DomainException
     * @expectedExceptionMessage Operation is not defined with a controller
     */
    public function testInvokeBailsOnMissingController()
    {
        $reflectedTalus = new ReflectionClass(Talus::class);
        $reflectedControllerList = $reflectedTalus->getProperty('controllerList');
        $reflectedControllerList->setAccessible(true);

        $mockRequest = $this->createMock(ServerRequestInterface::class);
        $mockRequest->expects($this->once())
            ->method('getAttribute')
            ->with('swagger')
            ->willReturn([
                'operation' => [
                    'operationId' => 'getThings',
                ],
            ]);

        $mockResponse = $this->createMock(ResponseInterface::class);

        $talus = $this->getMockBuilder(Talus::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();
        $reflectedControllerList->setValue($talus, []);

        $talus->__invoke($mockRequest, $mockResponse);
    }

    public function testGetRequest()
    {
        $reflectedTalus = new ReflectionClass(Talus::class);
        $reflectedRequest = $reflectedTalus->getMethod('getRequest');
        $reflectedRequest->setAccessible(true);

        $talus = $this->getMockBuilder(Talus::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();
        $request = $reflectedRequest->invoke($talus);

        $this->assertInstanceOf(RequestInterface::class, $request);
    }

    public function testGetResponse()
    {
        $reflectedTalus = new ReflectionClass(Talus::class);
        $reflectedResponse = $reflectedTalus->getMethod('getResponse');
        $reflectedResponse->setAccessible(true);

        $talus = $this->getMockBuilder(Talus::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();
        $response = $reflectedResponse->invoke($talus);

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }
}
