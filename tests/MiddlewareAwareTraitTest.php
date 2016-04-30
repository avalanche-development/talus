<?php

namespace Jacobemerick\Talus;

use Jacobemerick\Talus\Stub\MiddlewareAware as MiddlewareAwareStub;
use PHPUnit_Framework_TestCase;

class MiddlewareAwareTraitTest extends PHPUnit_Framework_TestCase
{

    public function testAddMiddleware()
    {
        $stub = new MiddlewareAwareStub();
        $middleware = function ($req, $res, $next) {};
        $stub->addMiddleware($middleware);

        $this->assertAttributeContains($middleware, 'stack', $stub);
    }
}
