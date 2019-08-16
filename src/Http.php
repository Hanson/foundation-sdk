<?php

namespace Hanson\Foundation;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use Hanson\Foundation\Exception\HttpException;
use Psr\Http\Message\ResponseInterface;

/**
 * Class Http.
 */
class Http
{
    /**
     * Used to identify handler defined by client code
     * Maybe useful in the future.
     */
    const USER_DEFINED_HANDLER = 'userDefined';

    /**
     * Http client.
     *
     * @var HttpClient
     */
    protected $client;

    /**
     * The middlewares.
     *
     * @var array
     */
    protected $middlewares = [];

    /**
     * Guzzle client default settings.
     *
     * @var array
     */
    protected static $defaults = [
        'curl' => [
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        ],
    ];

    /**
     * Set guzzle default settings.
     *
     * @param array $defaults
     */
    public static function setDefaultOptions($defaults = [])
    {
        self::$defaults = $defaults;
    }

    /**
     * Return current guzzle default settings.
     *
     * @return array
     */
    public static function getDefaultOptions()
    {
        return self::$defaults;
    }

    /**
     * GET request.
     *
     * @param string $url
     * @param array  $options
     *
     * @return ResponseInterface
     *
     * @throws HttpException
     */
    public function get($url, array $options = [])
    {
        return $this->request('GET', $url, ['query' => $options]);
    }

    /**
     * POST request.
     *
     * @param string $url
     * @param array $form
     *
     * @return ResponseInterface
     */
    public function post($url, array $form = [])
    {
        return $this->request('POST', $url, ['form_params' => $form]);
    }

    /**
     * JSON request.
     *
     * @param string $url
     * @param $query
     *
     * @return ResponseInterface
     */
     public function json($url, $query = [])
     {
         return $this->request('POST', $url, ['json' => $query]);
     }

    /**
     * Upload file.
     *
     * @param string $url
     * @param array $files
     * @param array $form
     * @param array $queries
     *
     * @return ResponseInterface
     */
    public function upload($url, array $queries = [], array $files = [], array $form = [])
    {
        $multipart = [];

        foreach ($files as $name => $path) {
            if (is_array($path)){
                foreach ($path as $item) {
                    $multipart[] = [
                            'name' => $name . '[]',
                        ] + $item;
                }
            }else{
                $multipart[] = [
                        'name' => $name,
                    ] + $path;
            }
        }

        foreach ($form as $name => $contents) {
            $multipart[] = compact('name', 'contents');
        }

        return $this->request('POST', $url, ['query' => $queries, 'multipart' => $multipart]);
    }

    /**
     * Set GuzzleHttp\Client.
     * @param HttpClient $client
     * @return $this
     * @throws HttpException
     * @author tu6ge
     * @date 2019/8/15 下午11:00
     */
    public function setClient(ClientInterface $client)
    {
        $this->client = $client;

        return $this;
    }

    /**
     * Return GuzzleHttp\Client instance.
     *
     * @return \GuzzleHttp\Client
     */
    public function getClient()
    {
        if (empty($this->client)) {
            $this->client = new HttpClient();
        }

        return $this->client;
    }

    /**
     * Add a middleware.
     *
     * @param callable $middleware
     *
     * @return $this
     */
    public function addMiddleware(callable $middleware)
    {
        array_push($this->middlewares, $middleware);

        return $this;
    }

    /**
     * Return all middlewares.
     *
     * @return array
     */
    public function getMiddlewares()
    {
        return $this->middlewares;
    }

    /**
     * Make a request.
     *
     * @param string $url
     * @param string $method
     * @param array $options
     *
     * @return ResponseInterface
     */
    public function request($method, $url, $options = [])
    {
        $method = strtoupper($method);

        $options = array_merge(self::$defaults, $options);

        Log::debug('Client Request:', compact('url', 'method', 'options'));

        $options['handler'] = $this->getHandler();

        $response = $this->getClient()->request($method, $url, $options);

        Log::debug('API response:', [
            'Status' => $response->getStatusCode(),
            'Reason' => $response->getReasonPhrase(),
            'Headers' => $response->getHeaders(),
            'Body' => strval($response->getBody()),
        ]);

        return $response;
    }

    /**
     * Build a handler.
     *
     * @return HandlerStack
     */
    protected function getHandler()
    {
        $stack = HandlerStack::create();

        foreach ($this->middlewares as $middleware) {
            $stack->push($middleware);
        }

        if (isset(static::$defaults['handler']) && is_callable(static::$defaults['handler'])) {
            $stack->push(static::$defaults['handler'], self::USER_DEFINED_HANDLER);
        }

        return $stack;
    }
}
