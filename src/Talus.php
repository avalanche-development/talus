<?php

/**
 * What what
 */

namespace Jacobemerick\Talus;

use DomainException;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Swagger\Document as SwaggerDocument;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequestFactory;

class Talus implements LoggerAwareInterface
{

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
        // add some middleware somehow
    }

    public function run()
    {
        $request = $this->getRequest();
        $response = $this->getResponse();
        $this->logger('got the request and response');
        // do stuff
    }

    /**
     * @returns RequestInterface
     */
    protected function getRequest()
    {
        return ServerRequestFactory::fromGlobals();
    }

    /**
     * @returns ResponseInterface
     */
    protected function getResponse()
    {
        return new Response();
    }
}
