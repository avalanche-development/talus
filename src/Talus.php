<?php

/**
 * What what
 */

namespace Jacobemerick\Talus;

use Closure;
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
use Swagger\Document as SwaggerDocument;
use Swagger\Object\Operation as SwaggerOperation;
use Swagger\Object\PathItem as SwaggerPath;
use Swagger\SchemaResolver;
use Swagger\Json\Pointer as SwaggerPointer;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequestFactory;

class Talus implements LoggerAwareInterface
{

    use LoggerAwareTrait;

    use MiddlewareAwareTrait;

    /** @var ContainerInterface */
    protected $container;

    /** @var SwaggerDocument */
    protected $swagger;

    /** @var array */
    protected static $readableModes = [
        'r',
        'rb',
        'w+',
        'w+b',
    ];

    /** @var Closure */
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
        } else {
            // todo NullContainer?
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
            $spec = $this->getSwaggerSpec($config['swagger']);
            $this->swagger = new SwaggerDocument($spec);
        } else {
            throw new DomainException('missing swagger information');
        }
    }

    /**
     * @param streamable $resource
     * @return array
     */
    protected function getSwaggerSpec($resource)
    {
        $meta = stream_get_meta_data($resource);
        if (!in_array($meta['mode'], self::$readableModes)) {
            throw new DomainException('swagger stream is not readable');
        }

        $spec = '';
        while (!feof($resource)) {
            $spec .= fread($resource, 8192);
        }

        $spec = json_decode($spec);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new DomainException('swagger stream is not parseable');
        }
        return $spec;
    }

    /**
     * @param Closure $errorHandler
     */
    public function setErrorHandler(Closure $errorHandler)
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
                header(sprintf('%s: %s', $header, implode(', ', $values)));
            }
        }

        // todo do we care about chunking?
        echo (string) $response->getBody();
    }

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @returns ResponseInterface
     */
    public function __invoke(RequestInterface $request, ResponseInterface $response)
    {
        foreach ($this->swagger->getPaths()->getAll() as $pathKey => $path) {
            $result = $this->matchPath($request, $pathKey, $path);
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
     * @param string $pathKey
     * @param SwaggerPath $swaggerPath
     * @response boolean
     */
    // todo a better response
    protected function matchPath(RequestInterface $request, $pathKey, SwaggerPath $swaggerPath)
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
