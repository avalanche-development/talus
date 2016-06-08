<?php

namespace AvalancheDevelopment\Talus;

use AvalancheDevelopment\Talus\Stub\MiddlewareAware as MiddlewareAwareStub;
use PHPUnit_Framework_TestCase;
use ReflectionClass;

class MiddlewareAwareTraitTest extends PHPUnit_Framework_TestCase
{

    public function testAddMiddleware()
    {
        $middleware = function ($req, $res, $next) {};
        $stub = new MiddlewareAwareStub();
        $decoratedMiddleware = function ($req, $res) use ($middleware, $stub) {};
        $stackSize = $stub->addMiddleware($middleware);

        $this->assertInternalType('integer', $stackSize);
        $this->assertAttributeCount($stackSize, 'stack', $stub);

        $reflectedStub = new ReflectionClass($stub);
        $reflectedStack = $reflectedStub->getProperty('stack');
        $reflectedStack->setAccessible(true);

        $result = $reflectedStack->getValue($stub);
        $result = reset($result);

        $this->assertEquals($decoratedMiddleware, $result);
    }

    public function testAddMiddlewareSeedsEmptyStack()
    {
        $middleware = function ($req, $res, $next) {};
        $stub = new MiddlewareAwareStub();
        $stub->addMiddleware($middleware);

        $this->assertAttributeContains($stub, 'stack', $stub);
    }

    public function testAddMiddlewareDoesNotRepeatSeed()
    {
        $middleware = function ($req, $res, $next) {};
        $stub = new MiddlewareAwareStub();

        $reflectedStub = new ReflectionClass($stub);
        $reflectedSeedStack = $reflectedStub->getMethod('seedStack');
        $reflectedSeedStack->setAccessible(true);

        $reflectedSeedStack->invokeArgs($stub, [$middleware]);

        $stub->addMiddleware($middleware);
        $this->assertAttributeNotContains($stub, 'stack', $stub);
    }

    public function testDecorateMiddleware()
    {
        $middleware = function ($req, $res, $next) {};
        $stub = new MiddlewareAwareStub();
        $decoratedMiddleware = function ($req, $res) use ($middleware, $stub) {};

        $reflectedStub = new ReflectionClass($stub);
        $reflectedDecorator = $reflectedStub->getMethod('decorateMiddleware');
        $reflectedDecorator->setAccessible(true);
        $reflectedStack = $reflectedStub->getProperty('stack');
        $reflectedStack->setAccessible(true);
        $reflectedStack->setValue($stub, [$stub]);

        $result = $reflectedDecorator->invokeArgs($stub, [$middleware]);

        $this->assertInternalType('callable', $result);
        $this->assertEquals($decoratedMiddleware, $result);
    }

    public function testDecoratedMiddlewareReturnsResponse()
    {
        $middleware = function ($req, $res, $next) { return $res; };
        $stub = new MiddlewareAwareStub();
        $request = $this->getMock('Psr\Http\Message\RequestInterface');
        $response = $this->getMock('Psr\Http\Message\ResponseInterface');

        $reflectedStub = new ReflectionClass($stub);
        $reflectedDecorator = $reflectedStub->getMethod('decorateMiddleware');
        $reflectedDecorator->setAccessible(true);
        $reflectedSeedStack = $reflectedStub->getMethod('seedStack');
        $reflectedSeedStack->setAccessible(true);

        $reflectedSeedStack->invokeArgs($stub, [$stub]);
        $decoratedMiddleware = $reflectedDecorator->invokeArgs($stub, [$middleware]);

        $result = $decoratedMiddleware($request, $response);

        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $result);
        $this->assertSame($response, $result);
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage Middleware must return instance of Psr Response
     */
    public function testDecoratedMiddlewareValidatesResponse()
    {
        $middleware = function ($req, $res, $next) { return 'foo'; };
        $stub = new MiddlewareAwareStub();
        $request = $this->getMock('Psr\Http\Message\RequestInterface');
        $response = $this->getMock('Psr\Http\Message\ResponseInterface');

        $reflectedStub = new ReflectionClass($stub);
        $reflectedDecorator = $reflectedStub->getMethod('decorateMiddleware');
        $reflectedDecorator->setAccessible(true);
        $reflectedSeedStack = $reflectedStub->getMethod('seedStack');
        $reflectedSeedStack->setAccessible(true);

        $reflectedSeedStack->invokeArgs($stub, [$stub]);
        $decoratedMiddleware = $reflectedDecorator->invokeArgs($stub, [$middleware]);

        $decoratedMiddleware($request, $response);
    }

    public function testSeedStack()
    {
        $stub = new MiddlewareAwareStub();

        $reflectedStub = new ReflectionClass($stub);
        $reflectedSeedStack = $reflectedStub->getMethod('seedStack');
        $reflectedSeedStack->setAccessible(true);

        $stackSize = $reflectedSeedStack->invokeArgs($stub, [$stub]);

        $this->assertAttributeEquals([$stub], 'stack', $stub);
        $this->assertInternalType('integer', $stackSize);
        $this->assertAttributeCount($stackSize, 'stack', $stub);
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage Can only seed the stack once
     */
    public function testSeedStackErrorsOnRepeatCalls()
    {
        $stub = new MiddlewareAwareStub();

        $reflectedStub = new ReflectionClass($stub);
        $reflectedSeedStack = $reflectedStub->getMethod('seedStack');
        $reflectedSeedStack->setAccessible(true);

        $reflectedSeedStack->invokeArgs($stub, [$stub]);
        $reflectedSeedStack->invokeArgs($stub, [$stub]);
    }

    public function testCallStack()
    {
        $middleware = function ($req, $res, $next) {
            $next($req, $res);
            return $res;
        };
        $stub = new MiddlewareAwareStub();
        $decoratedMiddleware = function ($req, $res) use ($middleware, $stub) {
            return call_user_func($middleware, $req, $res, $stub);
        };
        $request = $this->getMock('Psr\Http\Message\RequestInterface');
        $response = $this->getMock('Psr\Http\Message\ResponseInterface');

        $reflectedStub = new ReflectionClass($stub);
        $reflectedStack = $reflectedStub->getProperty('stack');
        $reflectedStack->setAccessible(true);
        $reflectedStack->setValue($stub, [$stub, $decoratedMiddleware]);

        $result = $stub->callStack($request, $response);

        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $result);
    }

    public function testCallStackSeedsEmptyStack()
    {
        $middleware = function ($req, $res, $next) {};
        $stub = new MiddlewareAwareStub();
        $request = $this->getMock('Psr\Http\Message\RequestInterface');
        $response = $this->getMock('Psr\Http\Message\ResponseInterface');

        $stub->callStack($request, $response);

        $this->assertAttributeContains($stub, 'stack', $stub);
    }
}
