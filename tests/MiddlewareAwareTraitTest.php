<?php

namespace Jacobemerick\Talus;

use Jacobemerick\Talus\Stub\MiddlewareAware as MiddlewareAwareStub;
use PHPUnit_Framework_TestCase;
use ReflectionClass;

class MiddlewareAwareTraitTest extends PHPUnit_Framework_TestCase
{

    public function testAddMiddleware()
    {
        $this->markTestIncomplete('Needs some more finesse');

        $stub = new MiddlewareAwareStub();
        $middleware = function ($req, $res, $next) {};
        $stub->addMiddleware($middleware);

        $this->assertAttributeContains($middleware, 'stack', $stub);
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
