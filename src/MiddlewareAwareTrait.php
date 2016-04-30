<?php

/**
 * Middleware trait, heavily influenced by Slim PHP's awesomeness.
 */

namespace Jacobemerick\Talus;

use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
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

        $next = reset($this->stack);
        array_unshift($this->stack, function (Request $req, Response $res) use ($callable, $next) {
            $result = call_user_func($callable, $req, $res, $next);
            if (!($result instanceof Response)) {
                throw new UnexpectedValueException('Middleware must return instance of Psr Response');
            }
        });
    }

    protected function seedStack(callable $callable)
    {
        if (!empty($this->stack)) {
            throw new RuntimeException('Can only seed the stack once');
        }

        $this->stack = [];
        array_push($this->stack, $callable);
    }

    public function callStack(Request $req, Response $res)
    {
        if (empty($this->stack)) {
            $this->seedStack($this);
        }

        $top = reset($this->stack);
        return $top($req, $res);
    }
}
