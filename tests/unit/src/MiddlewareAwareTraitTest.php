<?php

namespace AvalancheDevelopment\Talus;

use PHPUnit_Framework_TestCase;
use ReflectionClass;

class MiddlewareAwareTraitTest extends PHPUnit_Framework_TestCase
{

    public function testAddMiddlewareSeedsEmptyStack()
    {
        $trait = $this->getMockForTrait(
            MiddlewareAwareTrait::class,
            [],
            '',
            false,
            false,
            true,
            [
                'decorateMiddleware',
                'seedStack',
            ]
        );
        $trait->expects($this->once())
            ->method('seedStack')
            ->with($trait);

        $trait->addMiddleware(function () {});
    }

    public function testAddMiddlewareDoesNotRepeatSeed()
    {
        $trait = $this->getMockForTrait(
            MiddlewareAwareTrait::class,
            [],
            '',
            false,
            false,
            true,
            [
                'decorateMiddleware',
                'seedStack',
            ]
        );
        $trait->expects($this->never())
            ->method('seedStack');

        $reflectedTrait = new ReflectionClass($trait);
        $reflectedStack = $reflectedTrait->getProperty('stack');
        $reflectedStack->setAccessible(true);
        $reflectedStack->setValue($trait, [ 'some value' ]);

        $trait->addMiddleware(function () {});
    }

    public function testAddMiddlewareDecoratesMiddleware()
    {
        $middleware = function () {};

        $trait = $this->getMockForTrait(
            MiddlewareAwareTrait::class,
            [],
            '',
            false,
            false,
            true,
            [
                'decorateMiddleware',
                'seedStack',
            ]
        );
        $trait->expects($this->once())
            ->method('decorateMiddleware')
            ->with($middleware);

        $trait->addMiddleware($middleware);
    }

    public function testAddMiddlewareStacksDecoratedMiddleware()
    {
        $decoratedMiddleware = function () {};

        $trait = $this->getMockForTrait(
            MiddlewareAwareTrait::class,
            [],
            '',
            false,
            false,
            true,
            [
                'decorateMiddleware',
                'seedStack',
            ]
        );
        $trait->method('decorateMiddleware')
            ->willReturn($decoratedMiddleware);

        $trait->addMiddleware(function () {});

        $this->assertAttributeContains($decoratedMiddleware, 'stack', $trait);
    }

    public function testAddMiddlewareReturnsStackSize()
    {
        $stack = [
            'one',
            'two',
        ];

        $trait = $this->getMockForTrait(
            MiddlewareAwareTrait::class,
            [],
            '',
            false,
            false,
            true,
            [
                'decorateMiddleware',
                'seedStack',
            ]
        );
        $trait->method('decorateMiddleware')
            ->willReturn('three');

        $reflectedTrait = new ReflectionClass($trait);
        $reflectedStack = $reflectedTrait->getProperty('stack');
        $reflectedStack->setAccessible(true);
        $reflectedStack->setValue($trait, $stack);

        $stackSize = $trait->addMiddleware(function () {});

        $this->assertEquals(count($stack) + 1, $stackSize);
    }

    public function testDecorateMiddlewareReturnsCallable()
    {
        $trait = $this->getMockForTrait(
            MiddlewareAwareTrait::class,
            [],
            '',
            false,
            false
        );

        $middleware = function () {};

        $reflectedTrait = new ReflectionClass($trait);
        $reflectedDecorator = $reflectedTrait->getMethod('decorateMiddleware');
        $reflectedDecorator->setAccessible(true);
        $reflectedTrait = $reflectedTrait->getProperty('stack');
        $reflectedTrait->setAccessible(true);
        $reflectedTrait->setValue($trait, [ $trait ]);

        $result = $reflectedDecorator->invokeArgs($trait, [ $middleware ]);

        $this->assertInternalType('callable', $result);
    }

    public function testDecoratedMiddlewareReturnsMiddlewareResponse()
    {
        $trait = $this->getMockForTrait(
            MiddlewareAwareTrait::class,
            [],
            '',
            false,
            false
        );

        $middleware = function ($req, $res) { return $res; };
        $request = $this->createMock('Psr\Http\Message\RequestInterface');
        $response = $this->createMock('Psr\Http\Message\ResponseInterface');

        $reflectedTrait = new ReflectionClass($trait);
        $reflectedDecorator = $reflectedTrait->getMethod('decorateMiddleware');
        $reflectedDecorator->setAccessible(true);
        $reflectedTrait = $reflectedTrait->getProperty('stack');
        $reflectedTrait->setAccessible(true);

        $reflectedTrait->setValue($trait, [ $trait ]);
        $decoratedMiddleware = $reflectedDecorator->invokeArgs($trait, [ $middleware ]);

        $result = $decoratedMiddleware($request, $response);

        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $result);
        $this->assertSame($response, $result);
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage Middleware must return instance of Psr Response
     */
    public function testDecoratedMiddlewareBailsOnNonResponseReturns()
    {
        $trait = $this->getMockForTrait(
            MiddlewareAwareTrait::class,
            [],
            '',
            false,
            false
        );

        $middleware = function ($req, $res) { return 'foo'; };
        $request = $this->createMock('Psr\Http\Message\RequestInterface');
        $response = $this->createMock('Psr\Http\Message\ResponseInterface');

        $reflectedTrait = new ReflectionClass($trait);
        $reflectedDecorator = $reflectedTrait->getMethod('decorateMiddleware');
        $reflectedDecorator->setAccessible(true);
        $reflectedTrait = $reflectedTrait->getProperty('stack');
        $reflectedTrait->setAccessible(true);

        $reflectedTrait->setValue($trait, [ $trait ]);
        $decoratedMiddleware = $reflectedDecorator->invokeArgs($trait, [ $middleware ]);

        $result = $decoratedMiddleware($request, $response);
    }

    public function testSeedStackAddsSelfToStack()
    {
        $trait = $this->getMockForTrait(
            MiddlewareAwareTrait::class,
            [],
            '',
            false,
            false
        );

        $reflectedTrait = new ReflectionClass($trait);
        $reflectedSeedStack = $reflectedTrait->getMethod('seedStack');
        $reflectedSeedStack->setAccessible(true);

        $reflectedSeedStack->invokeArgs($trait, [ $trait ]);

        $this->assertAttributeEquals([ $trait ], 'stack', $trait);
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage Can only seed the stack once
     */
    public function testSeedStackErrorsOnRepeatCalls()
    {
        $trait = $this->getMockForTrait(
            MiddlewareAwareTrait::class,
            [],
            '',
            false,
            false
        );

        $reflectedTrait = new ReflectionClass($trait);
        $reflectedSeedStack = $reflectedTrait->getMethod('seedStack');
        $reflectedSeedStack->setAccessible(true);

        $reflectedSeedStack->invokeArgs($trait, [ $trait ]);
        $reflectedSeedStack->invokeArgs($trait, [ $trait ]);
    }

    public function testSeedStackReturnsSizeOfStack()
    {
        $trait = $this->getMockForTrait(
            MiddlewareAwareTrait::class,
            [],
            '',
            false,
            false
        );

        $reflectedTrait = new ReflectionClass($trait);
        $reflectedSeedStack = $reflectedTrait->getMethod('seedStack');
        $reflectedSeedStack->setAccessible(true);

        $result = $reflectedSeedStack->invokeArgs($trait, [ $trait ]);

        $this->assertInternalType('integer', $result);
        $this->assertAttributeCount($result, 'stack', $trait);
    }

    public function testCallStackSeedsEmptyStack()
    {
        $trait = $this->getMockForTrait(
            MiddlewareAwareTrait::class,
            [],
            '',
            false,
            false,
            true,
            [ 'seedStack' ]
        );
        $trait->expects($this->once())
            ->method('seedStack')
            ->with($trait)
            ->will($this->returnCallback(function () use ($trait) {
                $reflectedTrait = new ReflectionClass($trait);
                $reflectedTrait = $reflectedTrait->getProperty('stack');
                $reflectedTrait->setAccessible(true);
                $reflectedTrait->setValue($trait, [ function () {} ]);
            }));

        $request = $this->createMock('Psr\Http\Message\RequestInterface');
        $response = $this->createMock('Psr\Http\Message\ResponseInterface');

        $trait->callStack($request, $response);
    }

    public function testCallStackDoesNotRepeatSeed()
    {
        $trait = $this->getMockForTrait(
            MiddlewareAwareTrait::class,
            [],
            '',
            false,
            false,
            true,
            [ 'seedStack' ]
        );
        $trait->expects($this->never())
            ->method('seedStack');

        $request = $this->createMock('Psr\Http\Message\RequestInterface');
        $response = $this->createMock('Psr\Http\Message\ResponseInterface');

        $reflectedTrait = new ReflectionClass($trait);
        $reflectedStack = $reflectedTrait->getProperty('stack');
        $reflectedStack->setAccessible(true);
        $reflectedStack->setValue($trait, [ function () {} ]);

        $trait->callStack($request, $response);
    }

    public function testCallStackExecutesStack()
    {
        $trait = $this->getMockForTrait(
            MiddlewareAwareTrait::class,
            [],
            '',
            false,
            false,
            true,
            [ 'seedStack' ]
        );

        $request = $this->createMock('Psr\Http\Message\RequestInterface');
        $response = $this->createMock('Psr\Http\Message\ResponseInterface');

        $middleware = function ($req, $res) { return $res; };

        $reflectedTrait = new ReflectionClass($trait);
        $reflectedStack = $reflectedTrait->getProperty('stack');
        $reflectedStack->setAccessible(true);
        $reflectedStack->setValue($trait, [ $middleware ]);

        $result = $trait->callStack($request, $response);

        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $result);
        $this->assertSame($response, $result);
    }
}
