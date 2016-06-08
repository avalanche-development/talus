<?php

namespace AvalancheDevelopment\Talus\Stub;

use AvalancheDevelopment\Talus\MiddlewareAwareTrait;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class MiddlewareAware
{

    use MiddlewareAwareTrait;

    public function __invoke(Request $request, Response $response) {
        return $response;
    }
}
