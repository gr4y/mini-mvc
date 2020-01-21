<?php

namespace MiniMVC;

use \FastRoute\RouteCollector as RouteCollector;
use \Symfony\Component\HttpFoundation\Request;
use \Symfony\Component\HttpFoundation\Response;
use \Symfony\Component\HttpFoundation\Session\Session as Session;
use \League\Plates\Engine as Engine;

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
   * @param string currentDir
   * @param string templateDir
   */
  public function __construct($currentDir, $conf) {
    $this->workingDir = $currentDir;

    // if the templateDir is not set, we get the fuck out
    // any fallback path doesn't make any sense
    if($conf['views'] == null) {
      throw new Exception('Template Path not set');
    }

    // In any other cases, we will set the templateDir
    $this->templateDir = $conf['views'];

    // Pretty Exception Handling by Whoops
    $whoops = new \Whoops\Run;
    $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
    $whoops->register();

    // Initialize Session
    $this->session = new Session();

    // Initialize Leagues Plate Engine
    $this->templateEngine = new Engine($this->templateDir);
  }

  public function getTemplateEngine() {
    return $this->templateEngine;
  }

  public function getSession() {
    return $this->session;
  }

  /**
   * Pull all Namespaces from @see{\Composer\Autoload\ClassLoader}#setPrefixesPsr4() and look for "Controllers"-Namespace
   * then scans the directory and strips the extension from all filenames and pushes them into an array.
   * 
   * This is neccessary to be able to define all routes for each Controller in the Controller Class itself
   *
   * @param \Composer\Autoload\ClassLoader $loader
   */
  private function getControllers (\Composer\Autoload\ClassLoader $loader) {
    $controllers = array();
    $prefixes = $loader->getPrefixesPsr4();
    foreach($prefixes as $namespace => $path) {
      $path = $path[0];
      if(strpos($path, 'Controllers')) {
        $files = scandir($path);
        foreach($files as $file) {
          // Ignore all files named '.', '..' and files not called Controller
          if($file == ".." || $file == "." || strpos($file, 'Controller') == 0) continue;
          $controller = substr($file, 0, strlen($file) - 4);
          array_push($controllers, join([$namespace, $controller]));
        }
      }
    }

    // If there is Namespace with Controllers in it, the Application will just not work.
    if(empty($controllers)) 
      throw new \Exception("Add an Namespace called YourApplication\\Controllers and add it to PSR-4 in Composer");

    return $controllers;
  }

  /**
   * Run the actual Application
   */
  public function run ($loader = null){

    if(!$loader) 
      throw new \Exception("ClassLoader not specified. Please pass \Composer\Autoload\ClassLoader into \MiniMVC\Application::run");

    // Start Session
    $this->session->start();

    // Init Request
    $request = Request::createFromGlobals();

    // Fetch all Controllers
    $controllers = $this->getControllers($loader);

    // Initialize the Dispatcher using an Anonymous Function that calls the static method registerRoutes on each Controller
    $dispatcher = \FastRoute\simpleDispatcher(function(RouteCollector $r) use ($controllers){
      // iterate over controllers array
      foreach($controllers as $controller) {
        // if the class doesn't have the registerRoutes method, it will be skipped
        if(!method_exists($controller, "registerRoutes")) continue;
        // call registerRoutes method
        $controller::registerRoutes($r);
      }
    });

    // A dispatcher does what a dispatcher does... Like the spiderpig.
    $route = $dispatcher->dispatch($request->getMethod(), $request->getPathInfo());

    // Init Response
    $response = new Response();

    // Handling of route
    switch($route[0]){
      case \FastRoute\Dispatcher::NOT_FOUND:
        $response->setStatusCode(Response::HTTP_NOT_FOUND);
        $response->setContent(
          view('msg/not_found')
        );
        break;
      case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        $response->setStatusCode(Response::HTTP_METHOD_NOT_ALLOWED);
        $response->setContent(
          view('msg/method_not_allowed', ['allowed_methods' => $route[1]])
        );
        break;
      case \FastRoute\Dispatcher::FOUND:
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