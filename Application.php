<?php

namespace miniMVC;

use FastRoute\RouteCollector as RouteCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session as Session;
use League\Plates\Engine as Engine;

/*
 * The Application Class is my way of maintaining my own sanity
 */
class Application {

  /**
   * Specifies the working directory
   *
   * @var string
   */
  private $workingDir;

  /**
   * Specifies the template directory
   *
   * @var string
   */
  private $templateDir;

  /**
   * An instance of {@see Symfony\Component\HttpFoundation\Session\Session}
   *
   * @var Symfony\Component\HttpFoundation\Session\Session
   */
  private $session;

  /**
   * Instance of {@see FastRoute\RouteCollector}
   *
   * @var \FastRoute\RouteCollector
   */
  private $routeCollector;

  /**
   * Instance of {@see League\Plates\Engine}
   *
   * @var \League\Plates\Engine
   */
  private $templateEngine;

  /**
   * Initializer
   *
   * @param string currentDirectory
   */
  public function __construct($currentDir, $templateDir = null) {
    $this->workingDir = $currentDir;
    // if the templateDir is not set we will assume it's in `../app/views`
    if($templateDir == null) {
      $this->templateDir = $currentDir . '/../app/views';
    }

    // Pretty Exception Handling by Whoops
    $whoops = new \Whoops\Run;
    $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
    $whoops->register();

    // Initialize Session
    $this->session = new Session();

    // Initialize Leagues Plate Engine
    $this->templateEngine = new Engine($this->templateDir);
  }

  /**
   * @deprecated
   * TODO I think a routes.php would be a better solution than this method.
   */
  public function declareRoutes(callable $callback) {
    $this->routes = $callback;
  }

  public function getTemplateEngine() {
    return $this->templateEngine;
  }

  public function getSession() {
    return $this->session;
  }

  /**
   * Run the actual Application
   */
  public function run(){
    $this->session->start();

    // Init Request
    $request = Request::createFromGlobals();

    // init dispatcher
    $dispatcher = FastRoute\simpleDispatcher($this->routes);

    // A dispatcher does what a dispatcher does... Like the spiderpig.
    $route = $dispatcher->dispatch($request->getMethod(), $request->getPathInfo());

    // Init Response
    $response = new Response();

    // Handling of route
    switch($route[0]){
      case FastRoute\Dispatcher::NOT_FOUND:
        $response->setStatusCode(Response::HTTP_NOT_FOUND);
        $response->setContent(
          view('msg/not_found')
        );
        break;
      case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        $response->setStatusCode(Response::HTTP_METHOD_NOT_ALLOWED);
        $response->setContent(
          view('msg/method_not_allowed', ['allowed_methods' => $route[1]])
        );
        break;
      case FastRoute\Dispatcher::FOUND:
        // Replace Query Params in Request with Route Path Params
        $pathParams = $route[2];
        if(!empty($pathParams)) $request->query->replace($pathParams);
        $handler = $route[1];
        // Run the Handler
        $response = $handler($request, $response);
      break;
    }

    // Send Repsonse
    $response->send();

  }

}

?>