<?php

/**
 * What what
 */

namespace AvalancheDevelopment\Talus;

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

    /** @var ContainerInterface */
    protected $container;

    /** @var Swagger */
    protected $swagger;

    /** @var \Closure */
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

        $result = $this->callStack($request, $response);
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
            $result = $this->matchPath($request, $path);
            if ($result === false) {
                continue;
            }
            $request = $result;

            try {
                $httpMethodName = $this->mapHttpMethod($request);
                $operation = $path->$httpMethodName();
            } catch (Exception $e) {
                // todo 404 handler
                throw $e;
            }

            $this->logger->debug('Talus: routing matched, dispatching now');

            // todo should verify that operationId exists
            try {
                // todo this could be operation-level
                $controllerName = $path->getVendorExtension('swagger-router-controller');
                $methodName = $operation->getOperationId();
            } catch (Exception $e) {
                // todo handle straight functions
                throw $e;
            }

            $controller = new $controllerName($this->container);
            return $controller->$methodName($request, $response);
        }
    }

    /**
     * @param RequestInterface $request
     * @param SwaggerPath $swaggerPath
     * @response boolean
     */
    // todo a better response
    protected function matchPath(RequestInterface $request, SwaggerPath $swaggerPath)
    {
        if ($request->getUri()->getPath() == $pathKey) {
            return $request;
        }

        // todo what are acceptable path param values, anyways?
        $isVariablePath = preg_match_all('/{([a-z_]+)}/', $pathKey, $pathMatches);
        if (!$isVariablePath) {
            return false;
        }

        // loop da loop
        foreach ($pathMatches[1] as $pathParam) {
            foreach ($swaggerPath->getParameters() as $parameter) {
                // why oh why is this necessary
                if ($parameter->hasDocumentProperty('$ref')) {
                    $resolver = new SchemaResolver($this->swagger);
                    $pointer = $parameter->getDocumentProperty('$ref');
                    $pointer = substr($pointer, 2);
                    $pointer = new SwaggerPointer($pointer);
                    $parameter = $resolver->findTypeAtPointer($pointer);
                }
                if ($pathParam == $parameter->getName()) {
                    // todo extract extract will robinson
                    if ($parameter->getDocumentProperty('type') == 'string') {
                        $pathKey = str_replace('{' . $pathParam . '}', '(?P<' . $pathParam . '>\w+)', $pathKey);
                        continue 2;
                    }
                }
            }
            return false;
        }

        $matchedVariablePath = preg_match('@' . $pathKey . '@', $request->getUri()->getPath(), $pathMatches);
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
     * @return string
     */
    protected function mapHttpMethod(RequestInterface $request)
    {
        $httpMethod = $request->getMethod();
        $httpMethod = strtolower($httpMethod);
        $httpMethod = ucwords($httpMethod);
        return "get{$httpMethod}";
    }
}
