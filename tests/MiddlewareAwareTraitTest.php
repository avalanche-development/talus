<?php

namespace Jacobemerick\Talus;

use Jacobemerick\Talus\Stub\MiddlewareAware as MiddlewareAwareStub;
use PHPUnit_Framework_TestCase;
use ReflectionClass;

class MiddlewareAwareTraitTest extends PHPUnit_Framework_TestCase
{

    public function testAddMiddleware()
    {
        $this->markTestIncomplete('Closures are hard to match');

        $middleware = function ($req, $res, $next) {};
        $stub = new MiddlewareAwareStub();
        $stub->addMiddleware($middleware);

        $reflectedStub = new ReflectionClass($stub);
        $reflectedDecorator = $reflectedStub->getMethod('decorateMiddleware');
        $reflectedDecorator->setAccessible(true);

        $decoratedMiddleware = $reflectedDecorator->invokeArgs($stub, [$middleware]);

        $this->assertAttributeContains($decoratedMiddleware, 'stack', $stub);
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

    public function testAddMiddlewareResponse()
    {
        $middleware = function ($req, $res, $next) {};
        $stub = new MiddlewareAwareStub();
        $stackSize = $stub->addMiddleware($middleware);

        $this->assertInternalType('integer', $stackSize);
        $this->assertAttributeCount($stackSize, 'stack', $stub);
    }

    public function testSeedStack()
    {
        $stub = new MiddlewareAwareStub();

        $reflectedStub = new ReflectionClass($stub);
        $reflectedSeedStack = $reflectedStub->getMethod('seedStack');
        $reflectedSeedStack->setAccessible(true);

        $reflectedSeedStack->invokeArgs($stub, [$stub]);

        $this->assertAttributeEquals([$stub], 'stack', $stub);
    }

    public function testSeedStackResponse()
    {
        $stub = new MiddlewareAwareStub();

        $reflectedStub = new ReflectionClass($stub);
        $reflectedSeedStack = $reflectedStub->getMethod('seedStack');
        $reflectedSeedStack->setAccessible(true);

        $stackSize = $reflectedSeedStack->invokeArgs($stub, [$stub]);

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
}
