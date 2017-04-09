<?php

/**
 * What what
 */

namespace AvalancheDevelopment\Talus;

use DomainException;
use Exception;
use InvalidArgumentException;

use AvalancheDevelopment\CrashPad\ErrorHandler;
use AvalancheDevelopment\SwaggerCasterMiddleware\Caster;
use AvalancheDevelopment\SwaggerHeaderMiddleware\Header;
use AvalancheDevelopment\SwaggerRouterMiddleware\Router;
use AvalancheDevelopment\SwaggerValidationMiddleware\Validation;
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

    /** @var array $swagger */
    protected $swagger;

    /** @var array $controllerList */
    protected $controllerList;

    /** @var callable $errorHandler */
    protected $errorHandler;

    /**
     * @param array $swagger
     */
    public function __construct(array $swagger)
    {
        $this->swagger = $swagger;

        $this->logger = new NullLogger;
        $this->errorHandler = new ErrorHandler;
    }

    /**
     * @param string $operationId
     * @param callable $controller
     */
    public function addController($operationId, $controller)
    {
        if (!is_callable($controller)) {
            throw new InvalidArgumentException('Controller must be callable');
        }

        $this->controllerList[$operationId] = $controller;
    }

    /**
     * @param callable $errorHandler
     */
    public function setErrorHandler($errorHandler)
    {
        if (!is_callable($errorHandler)) {
            throw new InvalidArgumentException('Error handler must be callable');
        }

        $this->errorHandler = $errorHandler;
    }

    public function run()
    {
        $request = $this->getRequest();
        $response = $this->getResponse();

        $this->logger->debug('Talus: walking through swagger doc looking for dispatch');

        $this->buildMiddlewareStack();

        try {
            $result = $this->callStack($request, $response);
        } catch (Exception $exception) {
            if ($this->errorHandler instanceof LoggerAwareInterface) {
                $this->errorHandler->setLogger($this->logger);
            }

            $result = $this->errorHandler->__invoke($request, $response, $exception);
        }

        $this->outputResponse($result);
    }

    public function buildMiddlewareStack()
    {
        $header = new Header;
        $header->setLogger($this->logger);
        $this->addMiddleware($header);

        $caster = new Caster;
        $caster->setLogger($this->logger);
        $this->addMiddleware($caster);

        $validation = new Validation;
        $validation->setLogger($this->logger);
        $this->addMiddleware($validation);

        $router = new Router($this->swagger);
        $router->setLogger($this->logger);
        $this->addMiddleware($router);
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
        $operation = $request->getAttribute('swagger')->getOperation()['operationId'];

        if (!array_key_exists($operation, $this->controllerList)) {
            throw new DomainException('Operation is not defined with a controller');
        }

        return call_user_func($this->controllerList[$operation], $request, $response);
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
