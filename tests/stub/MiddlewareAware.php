<?php

namespace Jacobemerick\Talus\Stub;

use Jacobemerick\Talus\MiddlewareAwareTrait;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class MiddlewareAware
{

    use MiddlewareAwareTrait;

    public function __invoke(Request $request, Response $response) {
        return $response;
    }
}
