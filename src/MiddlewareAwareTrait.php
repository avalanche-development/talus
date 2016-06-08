<?php

/**
 * Middleware trait, heavily influenced by Slim PHP's awesomeness.
 */

namespace AvalancheDevelopment\Talus;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use UnexpectedValueException;

trait MiddlewareAwareTrait
{

    /** @var array */
    protected $stack;

    /**
     * @param callable $callable
     * @returns integer
     */
    public function addMiddleware(callable $callable)
    {
        if (empty($this->stack)) {
            // todo not sure if i like seeding the stack like this...
            $this->seedStack($this);
        }

        $decoratedMiddleware = $this->decorateMiddleware($callable);
        return array_unshift($this->stack, $decoratedMiddleware);
    }

    /**
     * @param callable $callable
     * @returns callable
     */
    protected function decorateMiddleware(callable $callable)
    {
        $next = reset($this->stack);
        return function (RequestInterface $request, ResponseInterface $response) use ($callable, $next) {
            $result = call_user_func($callable, $request, $response, $next);
            if (!($result instanceof ResponseInterface)) {
                throw new UnexpectedValueException('Middleware must return instance of Psr Response');
            }
            return $result;
        };
    }

    /**
     * @param callable $callable
     * @returns integer
     */
    protected function seedStack(callable $callable)
    {
        if (!empty($this->stack)) {
            throw new RuntimeException('Can only seed the stack once');
        }

        $this->stack = [];
        return array_push($this->stack, $callable);
    }

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @returns RequestInterface
     */
    public function callStack(RequestInterface $request, ResponseInterface $response)
    {
        if (empty($this->stack)) {
            $this->seedStack($this);
        }

        $top = reset($this->stack);
        return $top($request, $response);
    }
}
