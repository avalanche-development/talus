<?php

/**
 * What what
 */

namespace AvalancheDevelopment\Talus;

use gossi\swagger\Path as SwaggerPath;
use gossi\swagger\Swagger;
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

    /** @var Swagger $swagger */
    protected $swagger;

    /** @var \Closure $errorHandler */
    protected $errorHandler;

    /**
     * @param array $config
     */
    public function __construct(array $config)
    {
        if (!empty($config['container'])) {
            if (!($config['container'] instanceof ContainerInterface)) {
                throw new \InvalidArgumentException('container must be instance of ContainerInterface');
            }
            $this->container = $config['container'];
        }

        if (!empty($config['logger'])) {
            if (!($config['logger'] instanceof LoggerInterface)) {
                throw new \InvalidArgumentException('logger must be instance of LoggerInterface');
            }
            $this->logger = $config['logger'];
        } else {
            $this->logger = new NullLogger();
        }

        if (!empty($config['swagger'])) {
            $this->swagger = new Swagger($config['swagger']);
        } else {
            throw new \DomainException('missing swagger information');
        }
    }

    /**
     * @param \Closure $errorHandler
     */
    public function setErrorHandler(\Closure $errorHandler)
    {
        $this->errorHandler = $errorHandler;
    }

    public function run()
    {
        $request = $this->getRequest();
        $response = $this->getResponse();

        $this->logger->debug('Talus: walking through swagger doc looking for dispatch');

        try {
            $result = $this->callStack($request, $response);
        } catch (\Exception $e) {
            $result = $this->errorHandler->__invoke($request, $response, $e);
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
            $swaggerDoc = $this->swagger->toArray();
            $swaggerDoc = json_encode($swaggerDoc);
            $response->getBody()->write($swaggerDoc);
            return $response;
        }

        foreach ($this->swagger->getPaths() as $path) {
            $matchResult = $this->matchPath($request, $path);
            if ($matchResult === false) {
                continue;
            }
            $request = $matchResult;

            try {
                $method = strtolower($request->getMethod());
                $operation = $path->getOperation($method);
            } catch (\Exception $e) {
                throw $e;
            }

            $this->logger->debug('Talus: routing matched, dispatching now');

            // todo should verify that operationId exists
            try {
                // todo this could be operation-level
                $controllerName = $path->getExtensions()->get('swagger-router-controller');
                $methodName = $operation->getOperationId();
            } catch (Exception $e) {
                // todo handle straight functions
                throw $e;
            }

            $controller = new $controllerName($this->container);
            return $controller->$methodName($request, $response);
        }

        throw new \Exception('Path not found');
    }

    /**
     * @param RequestInterface $request
     * @param SwaggerPath $swaggerPath
     * @response boolean
     */
    // todo a better response
    protected function matchPath(RequestInterface $request, SwaggerPath $swaggerPath)
    {
        if ($request->getUri()->getPath() === $swaggerPath->getPath()) {
            return $request;
        }

        // todo what are acceptable path param values, anyways?
        $isVariablePath = preg_match_all('/{([a-z_]+)}/', $swaggerPath->getPath(), $pathMatches);
        if (!$isVariablePath) {
            return false;
        }

        // loop da loop
        // todo feels weird that we pull operation out here and then do it again later
        $method = strtolower($request->getMethod());
        $operation = $swaggerPath->getOperation($method);
        foreach ($pathMatches[1] as $pathParam) {
            foreach ($operation->getParameters() as $parameter) {
                if ($pathParam == $parameter->getName()) {
                    if ($parameter->getType() == 'string') {
                        $pathKey = str_replace(
                            '{' . $pathParam . '}',
                            '(?P<' . $pathParam . '>\w+)',
                            $swaggerPath->getPath()
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
}
