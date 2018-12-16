<?php


namespace Hanson\Foundation;


use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\FilesystemCache;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Pimple\Container;
use Symfony\Component\HttpFoundation\Request;

class Foundation extends Container
{

    /**
     * an array of service providers.
     *
     * @var
     */
    protected $providers = [];

    public function __construct($config)
    {
        parent::__construct();

        $this['config'] = $config;

        if ($this->config['debug'] ?? false) {
            error_reporting(E_ALL);
        }

        $this->registerProviders();
        $this->registerBase();
        $this->initializeLogger();
    }

    /**
     * Register basic providers.
     */
    private function registerBase()
    {
        $this['request'] = function () {
            return Request::createFromGlobals();
        };

        if ($cache = $this['config']['cache'] ?? null AND $cache instanceof Cache) {
            $this['cache'] = $this['config']['cache'];
        } else {
            $this['cache'] = function () {
                return new FilesystemCache(sys_get_temp_dir());
            };
        }
    }

    /**
     * Initialize logger.
     */
    private function initializeLogger()
    {
        if (Log::hasLogger()) {
            return;
        }

        $logger = new Logger($this['config']['log']['name'] ?? 'foundation');

        if (!$this['config']['debug'] ?? false || defined('PHPUNIT_RUNNING')) {
            $logger->pushHandler(new NullHandler());
        } elseif ($this['config']['log']['handler'] instanceof HandlerInterface) {
            $logger->pushHandler($this['config']['log']['handler']);
        } elseif ($logFile = $this['config']['log']['file'] ?? null) {
            $logger->pushHandler(new StreamHandler(
                    $logFile,
                    $this['config']['log']['level'] ?? Logger::WARNING,
                    true,
                    $this['config']['log']['permission'] ?? null
            ));
        }

        Log::setLogger($logger);
    }

    /**
     * Register providers.
     */
    protected function registerProviders()
    {
        foreach ($this->providers as $provider) {
            $this->register(new $provider());
        }
    }

    /**
     * Magic get access.
     *
     * @param string $id
     *
     * @return mixed
     */
    public function __get($id)
    {
        return $this->offsetGet($id);
    }

    /**
     * Magic set access.
     *
     * @param string $id
     * @param mixed  $value
     */
    public function __set($id, $value)
    {
        $this->offsetSet($id, $value);
    }
}