<?php

/**
 * What what
 */

namespace AvalancheDevelopment\Talus;

use DomainException;
use Exception;
use InvalidArgumentException;

use AvalancheDevelopment\SwaggerRouterMiddleware\Router;
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
    // todo do we really need an array here
    public function __construct(array $config)
    {
        if (!empty($config['container'])) {
            if (!($config['container'] instanceof ContainerInterface)) {
                throw new InvalidArgumentException('container must be instance of ContainerInterface');
            }
            $this->container = $config['container'];
        }

        $this->logger = new NullLogger();
        // todo we probably shouldn't allow passing in logger as config key
        if (!empty($config['logger'])) {
            if (!($config['logger'] instanceof LoggerInterface)) {
                throw new InvalidArgumentException('logger must be instance of LoggerInterface');
            }
            $this->logger = $config['logger'];
        }

        if (empty($config['swagger'])) {
            throw new DomainException('missing swagger information');
        }
        $this->swagger = $config['swagger'];
    }

    /**
     * @param callable $errorHandler
     */
    public function setErrorHandler($errorHandler)
    {
        if (!is_callable($errorHandler)) {
            throw new InvalidArgumentException('error handler must be callable');
        }

        $this->errorHandler = $errorHandler;
    }

    public function run()
    {
        $request = $this->getRequest();
        $response = $this->getResponse();

        $this->logger->debug('Talus: walking through swagger doc looking for dispatch');

        $swaggerRouter = new Router($this->swagger);
        $swaggerRouter->setLogger($this->logger);
        $this->addMiddleware($swaggerRouter);

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

        if ($response->getHeaders() !== null) {
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
        // todo this could be operation-level
        $controllerName = $request->getAttribute('swagger')['path']['x-swagger-router-controller'];
        $methodName = $request->getAttribute('swagger')['operation']['operationId'];

        try {
            // todo this should be container-controlled
            $controller = new $controllerName($this->container);
            return $controller->$methodName($request, $response);
        } catch (Exception $e) {
            // todo handle straight errors too
            throw $e;
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
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param Exception $exception
     * @return ResponseInterface
     */
    protected function handleError($request, $response, $exception)
    {
        if (!isset($this->errorHandler)) {
            $response->withStatus(500);
            $response->getBody()->write("Error: {$exception->getMessage()}");
            return $response;
        }

        return $this->errorHandler->__invoke($request, $response, $exception);
    }
}
