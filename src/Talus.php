<?php

/**
 * What what
 */

namespace Jacobemerick\Talus;

use DomainException;
use InvalidArgumentException;
use Interop\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Swagger\Document as SwaggerDocument;
use Swagger\Object\Operation as SwaggerOperation;
use Swagger\Object\PathItem as SwaggerPath;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequestFactory;

class Talus implements LoggerAwareInterface
{

    /** @var ContainerInterface */
    protected $container;

    /** @var LoggerInterface */
    protected $logger;

    /** @var SwaggerDocument */
    protected $swagger;

    /** @var array */
    protected static $readableModes = [
        'r',
        'rb',
        'w+',
        'w+b',
    ];

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
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function addMiddleware()
    {
        // todo add some middleware somehow
    }

    public function run()
    {
        $request = $this->getRequest();
        $response = $this->getResponse();

        $this->logger->debug('Talus: walking through swagger doc looking for dispatch');
        foreach ($this->swagger->getPaths()->getAll() as $pathKey => $path) {

            // todo wildcard matching
            if ($request->getUri()->getPath() != $pathKey) {
                continue;
            }

            try {
                $operation = $this->matchOperation($path, $request);
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
     * @param SwaggerPath $path
     * @param RequestInterface $request
     * @return SwaggerOperation
     * @throws Exception
     */
    protected function matchOperation(SwaggerPath $path, RequestInterface $request)
    {
        $httpMethod = $request->getMethod();
        $httpMethod = strtolower($httpMethod);
        $httpMethod = ucwords($httpMethod);
        $method = "get{$httpMethod}";

        return $path->$method();
    }
}
