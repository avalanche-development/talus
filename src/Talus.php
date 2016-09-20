<?php

/**
 * What what
 */

namespace AvalancheDevelopment\Talus;

use DomainException;
use Exception;
use InvalidArgumentException;

use Interop\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequestFactory;

class Talus implements LoggerAwareInterface
{

    use LoggerAwareTrait;

    use MiddlewareAwareTrait;

    /** @var ContainerInterface $container */
    protected $container;

    /** @var array $swagger */
    protected $swagger;

    /** @var callable $errorHandler */
    protected $errorHandler;

    /**
     * @param array $config
     */
    public function __construct(array $config)
    {
        if (!empty($config['container'])) {
            if (!($config['container'] instanceof ContainerInterface)) {
                throw new InvalidArgumentException('container must be instance of ContainerInterface');
            }
            $this->container = $config['container'];
        }

        if (!empty($config['logger'])) {
            if (!($config['logger'] instanceof LoggerInterface)) {
                throw new InvalidArgumentException('logger must be instance of LoggerInterface');
            }
            $this->logger = $config['logger'];
        } else {
            $this->logger = new NullLogger();
        }

        if (!empty($config['swagger'])) {
            $this->swagger = $config['swagger'];
        } else {
            throw new DomainException('missing swagger information');
        }
    }

    /**
     * @param callable $errorHandler
     */
    public function setErrorHandler(callable $errorHandler)
    {
        if (!is_callable($errorHandler)) {
            throw new DomainException('error handler must be callable');
        }

        $this->errorHandler = $errorHandler;
    }

    public function run()
    {
        $request = $this->getRequest();
        $response = $this->getResponse();

        $this->logger->debug('Talus: walking through swagger doc looking for dispatch');

        try {
            $result = $this->callStack($request, $response);
        } catch (Exception $e) {
            $result = $this->handleError($request, $response, $e);
        }

        $this->outputResponse($result);
    }

    /**
     * @param ResponseInterface $response
     */
    protected function outputResponse(ResponseInterface $response)
    {
        header(
            sprintf('HTTP/1.1 %d %s', $response->getStatusCode(), $response->getReasonPhrase()),
            true,
            $response->getStatusCode()
        );

        if ($response->getHeaders() !== NULL) {
            foreach ($response->getHeaders() as $header => $values) {
                header(
                    sprintf('%s: %s', $header, implode(', ', $values)),
                    true
                );
            }
        }

        // todo do we care about chunking?
        echo (string) $response->getBody();
    }

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public function __invoke(RequestInterface $request, ResponseInterface $response)
    {
        if ($request->getUri()->getPath() == '/api-docs') {
            $swaggerDoc = json_encode($this->swagger); // todo handle errors
            $response->getBody()->write($swaggerDoc);
            return $response;
        }

        foreach ($this->swagger['paths'] as $route => $pathItem) {
            $matchResult = $this->matchPath($request, $route, $pathItem);
            if ($matchResult === false) {
                continue;
            }
            $request = $matchResult;

            try {
                $method = strtolower($request->getMethod());
                $operation = $pathItem[$method];
            } catch (Exception $e) {
                throw $e;
            }

            $this->logger->debug('Talus: routing matched, dispatching now');

            // todo should verify that operationId exists
            try {
                // todo this could be operation-level
                $controllerName = $pathItem['x-swagger-router-controller'];
                $methodName = $operation['operationId'];
            } catch (Exception $e) {
                // todo handle straight functions
                throw $e;
            }

            $controller = new $controllerName($this->container);
            return $controller->$methodName($request, $response);
        }

        throw new Exception('Path not found');
    }

    /**
     * @param RequestInterface $request
     * @param string $route
     * @param array $pathItem
     * @response boolean
     */
    // todo a better response
    protected function matchPath(RequestInterface $request, $route, array $pathItem)
    {
        if ($request->getUri()->getPath() === $route) {
            return $request;
        }

        // todo what are acceptable path param values, anyways?
        $isVariablePath = preg_match_all('/{([a-z_]+)}/', $route, $pathMatches);
        if (!$isVariablePath) {
            return false;
        }

        // loop da loop
        // todo feels weird that we pull operation out here and then do it again later
        $method = strtolower($request->getMethod());
        $operation = $pathItem[$method]; // todo invalid operations?
        foreach ($pathMatches[1] as $pathParam) {
            foreach ($operation['parameters'] as $parameter) {
                if ($pathParam == $parameter['name']) {
                    if ($parameter['type'] == 'string') {
                        $pathKey = str_replace(
                            '{' . $pathParam . '}',
                            '(?P<' . $pathParam . '>\w+)',
                            $route
                        );
                        continue 2;
                    }
                }
            }
            return false;
        }

        $matchedVariablePath = preg_match(
            '@' . $pathKey . '@',
            $request->getUri()->getPath(),
            $pathMatches
        );
        if (!$matchedVariablePath) {
            return false;
        }

        $pathMatches = array_filter($pathMatches, function ($key) {
            return !is_numeric($key);
        }, ARRAY_FILTER_USE_KEY);

        foreach ($pathMatches as $key => $value) {
            $request = $request->withAttribute($key, $value);
        }

        return $request;
    }

    /**
     * @return RequestInterface
     */
    protected function getRequest()
    {
        return ServerRequestFactory::fromGlobals();
    }

    /**
     * @return ResponseInterface
     */
    protected function getResponse()
    {
        return new Response();
    }

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param Exception $e
     * @return ResponseInterface
     */
    protected function handleError($request, $response, $e)
    {
        if (!isset($this->errorHandler)) {
            $response->getBody()->write("Error: {$e->getMessage()}");
            return $response;
        }

        return $this->errorHandler->__invoke($request, $response, $e);
    }
}
