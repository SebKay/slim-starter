<?php

namespace App;

use App\Container\Container;
use App\Database\Database;
use App\Middleware\ExampleMiddleware;
use App\Handlers\HttpErrorHandler;
use App\Handlers\ShutdownHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App as SlimApp;
use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;

class App
{
    /**
     * @var boolean
     */
    public $dev_mode;

    /**
     * @var Container
     */
    protected $container;

    /**
     * @var SlimApp
     */
    protected $slim;

    /**
     * @var HttpErrorHandler
     */
    protected $error_handler;

    /**
     * @var Database
     */
    protected $database;

    /**
     * Set up
     */
    public function __construct()
    {
        $this->dev_mode = $this->isDevelopmentMode();

        $this->container = new Container($this);
        $this->slim      = AppFactory::createFromContainer($this->container()->get());

        $this->container()->setup();
        $this->setupSlim();

        $this->database = new Database();
    }

    /**
     * Decide if we're in development mode or not
     *
     * @return bool
     */
    public function isDevelopmentMode(): bool
    {
        return ($_ENV['APP_ENV'] == 'development' || $_ENV['APP_ENV'] == 'test' ? true : false);
    }

    /**
     * Get the container
     *
     * @return Container
     */
    public function container(): Container
    {
        return $this->container;
    }

    /**
     * Get the slim app
     *
     * @return SlimApp
     */
    public function slim(): SlimApp
    {
        return $this->slim;
    }

    /**
     * Add middleware
     */
    protected function addMiddleware(): void
    {
        $this->slim()->addRoutingMiddleware();

        $this->slim()
            ->addErrorMiddleware($this->dev_mode, false, false)
            ->setDefaultErrorHandler($this->error_handler);

        $this->slim()->add('csrf');
        // $this->slim()->add(ExampleMiddleware::class);
    }

    /**
     * Define routes
     */
    protected function addRoutes(): void
    {
        require_once __DIR__ . '/../inc/routes/web.php';
    }

    /**
     * Add error handler
     */
    protected function addErrorHandler(): void
    {
        $this->error_handler = new HttpErrorHandler(
            $this->container()->get(),
            $this->slim()->getCallableResolver(),
            $this->slim()->getResponseFactory()
        );
    }

    /**
     * Add shutdown (fatal error) handler
     */
    protected function addShutdownHandler(): void
    {
        $serverRequestCreator = ServerRequestCreatorFactory::create();
        $request              = $serverRequestCreator->createServerRequestFromGlobals();
        $shutdownHandler      = new ShutdownHandler($request, $this->error_handler, $this->dev_mode);

        \register_shutdown_function($shutdownHandler);
    }

    /**
     * Setup the app (called in the constructor)
     */
    protected function setupSlim(): void
    {
        $this->addErrorHandler();
        $this->addShutdownHandler();
        $this->addMiddleware();
        $this->addRoutes();
    }

    /**
     * Hanndle a request object
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->slim()->handle($request);
    }

    /**
     * Handle the global request and run the app
     *
     * @codeCoverageIgnore
     */
    public function run(): void
    {
        $this->slim()->run();
    }
}
