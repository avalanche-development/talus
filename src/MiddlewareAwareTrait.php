<?php

/**
 * Middleware trait, heavily influenced by Slim PHP's awesomeness.
 */

namespace Jacobemerick\Talus;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use UnexpectedValueException;

trait MiddlewareAwareTrait
{

    /** @var array */
    protected $stack;

    public function addMiddleware(callable $callable)
    {
        if (empty($this->stack)) {
            $this->seedStack($this);
        }

        $decoratedMiddleware = $this->decorateMiddleware($callable);
        array_unshift($this->stack, $decoratedMiddleware);
    }

    protected function decorateMiddleware(callable $callable)
    {
        $next = reset($this->stack);
        return function (RequestInterface $request, ResponseInterface $response) use ($callable, $next) {
            $result = call_user_func($callable, $request, $response, $next);
            if (!($result instanceof ResponseInterface)) {
                throw new UnexpectedValueException('Middleware must return instance of Psr Response');
            }
        };
    }

    protected function seedStack(callable $callable)
    {
        if (!empty($this->stack)) {
            throw new RuntimeException('Can only seed the stack once');
        }

        $this->stack = [];
        array_push($this->stack, $callable);
    }

    public function callStack(RequestInterface $request, ResponseInterface $response)
    {
        if (empty($this->stack)) {
            $this->seedStack($this);
        }

        $top = reset($this->stack);
        return $top($request, $response);
    }
}
