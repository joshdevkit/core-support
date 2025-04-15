<?php

namespace Core;

class Router
{
    /**
     * Route collection
     * @var array
     */
    protected $routes = [];

    /**
     * Named routes collection
     * @var array
     */
    protected $namedRoutes = [];

    /**
     * Route parameters
     * @var array
     */
    protected $params = [];

    /**
     * Current middleware stack
     * @var array
     */
    protected $middlewares = [];

    /**
     * Current middleware group controller
     * @var string|null
     */
    protected $groupController = null;

    /**
     * Current middleware group prefix
     * @var string
     */
    protected $groupPrefix = '';

    /**
     * Model bindings configuration
     * @var array
     */
    protected $modelBindings = [];

    /**
     * Add a route to the collection with an exact match flag
     * 
     * @param string $method HTTP method
     * @param string $path Route path
     * @param mixed $handler Controller and method to call
     * @param bool $exactMatch Whether this route should be matched exactly
     * @return Router
     */
    public function add($method, $path, $handler, $exactMatch = false)
    {
        // Apply group prefix if set
        if ($this->groupPrefix) {
            $path = rtrim($this->groupPrefix, '/') . '/' . ltrim($path, '/');
        }

        // Convert path to a regular expression pattern
        $pattern = $this->pathToRegex($path);

        // If we're in a group with a controller set, adjust the handler
        if ($this->groupController && is_string($handler)) {
            $handler = [$this->groupController, $handler];
        }

        $route = [
            'method' => strtoupper($method),
            'path' => $path,
            'pattern' => $pattern,
            'handler' => $handler,
            'middlewares' => $this->middlewares,
            'exactMatch' => $exactMatch
        ];

        // If this is an exact match route, prepend it to the routes array
        // to ensure it gets checked first
        if ($exactMatch) {
            array_unshift($this->routes, $route);
        } else {
            $this->routes[] = $route;
        }

        // Reset middlewares if not in a group context
        if (empty($this->groupPrefix) && empty($this->groupController)) {
            $this->middlewares = [];
        }

        // Return a RouteRegistrar to allow method chaining for naming
        return new RouteRegistrar($this, $route);
    }
    /**
     * Get all registered routes (for debugging)
     * 
     * @return array
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    /**
     * Add a GET route
     * 
     * @param string $path Route path
     * @param mixed $handler Controller and method to call
     * @return RouteRegistrar
     */
    public function get($path, $handler)
    {
        return $this->add('GET', $path, $handler);
    }

    /**
     * Add a POST route
     * 
     * @param string $path Route path
     * @param mixed $handler Controller and method to call
     * @return RouteRegistrar
     */
    public function post($path, $handler)
    {
        return $this->add('POST', $path, $handler);
    }

    /**
     * Add a PUT route
     * 
     * @param string $path Route path
     * @param mixed $handler Controller and method to call
     * @return RouteRegistrar
     */
    public function put($path, $handler)
    {
        return $this->add('PUT', $path, $handler);
    }

    /**
     * Add a DELETE route
     * 
     * @param string $path Route path
     * @param mixed $handler Controller and method to call
     * @return RouteRegistrar
     */
    public function delete($path, $handler)
    {
        return $this->add('DELETE', $path, $handler);
    }

    /**
     * Set middleware for next routes
     * 
     * @param string|array $middleware
     * @return Router
     */
    public function middleware($middleware = [])
    {
        if (is_string($middleware)) {
            $middleware = [$middleware];
        }

        $this->middlewares = $middleware;
        return $this;
    }

    /**
     * Group routes with shared attributes
     * 
     * @param callable $callback
     * @param string $prefix Optional URL prefix for all routes in the group
     * @return Router
     */
    public function group(callable $callback, $prefix = '')
    {
        // Save current middleware state
        $previousMiddlewares = $this->middlewares;
        $previousGroupController = $this->groupController;
        $previousGroupPrefix = $this->groupPrefix;

        // If prefix is provided, set it
        if ($prefix) {
            $this->groupPrefix = $previousGroupPrefix
                ? rtrim($previousGroupPrefix, '/') . '/' . trim($prefix, '/')
                : $prefix;
        }

        // Call the group definition callback
        $callback($this);

        // Restore previous middleware state
        $this->middlewares = $previousMiddlewares;
        $this->groupController = $previousGroupController;
        $this->groupPrefix = $previousGroupPrefix;

        return $this;
    }

    /**
     * Set controller for a group
     * 
     * @param string $controller Controller class
     * @return Router
     */
    public function controller($controller)
    {
        $this->groupController = $controller;
        return $this;
    }

    /**
     * Set prefix for a group
     * 
     * @param string $prefix URL prefix
     * @return Router
     */
    public function prefix($prefix)
    {
        $this->groupPrefix = $prefix;
        return $this;
    }

    /**
     * Register a model binding
     * 
     * @param string $param Route parameter name
     * @param string $modelClass Fully qualified model class name
     * @param string $field Model field to query (default: 'id')
     * @return Router
     */
    public function bind($param, $modelClass, $field = 'id')
    {
        $this->modelBindings[$param] = [
            'class' => $modelClass,
            'field' => $field
        ];

        return $this;
    }

    /**
     * Name a route
     * 
     * @param array $route Route data
     * @param string $name Route name
     * @return Router
     */
    public function nameRoute(array $route, string $name)
    {
        if (isset($this->namedRoutes[$name])) {
            throw new \Exception("Route name '$name' already in use");
        }

        $this->namedRoutes[$name] = $route;

        // Reset middlewares after naming a route that's not in a group
        if (empty($this->groupPrefix) && empty($this->groupController)) {
            $this->middlewares = [];
        }

        return $this;
    }

    /**
     * Get a named route by name
     * 
     * @param string $name Route name
     * @return array|null
     */
    public function getNamedRoute(string $name)
    {
        return $this->namedRoutes[$name] ?? null;
    }

    /**
     * Generate a URL for a named route
     * 
     * @param string $name Route name
     * @param array $params Route parameters
     * @return string
     * @throws \Exception If the route name doesn't exist
     */
    public function route(string $name, array $params = [])
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new \Exception("Route with name '$name' not found");
        }

        $route = $this->namedRoutes[$name];
        $path = $route['path'];

        // Create a copy of params to track which ones are used in the path
        $unusedParams = $params;

        // Replace named parameters in the URL
        if (!empty($params)) {
            $path = preg_replace_callback(
                '/\{([a-zA-Z0-9_]+)\}/',
                function ($matches) use (&$unusedParams) {
                    $paramName = $matches[1];

                    if (isset($unusedParams[$paramName])) {
                        $value = $unusedParams[$paramName];
                        // Remove the parameter from unused list once it's used in the path
                        unset($unusedParams[$paramName]);
                        return $value;
                    }

                    return $matches[0];
                },
                $path
            );
        }

        // Add any remaining unused parameters as query string
        if (!empty($unusedParams)) {
            $path .= '?' . http_build_query($unusedParams);
        }

        return $path;
    }

    /**
     * Convert a path to a regular expression pattern
     * 
     * @param string $path
     * @return string
     */
    private function pathToRegex($path)
    {
        // Escape forward slashes for the regex pattern
        $path = str_replace('/', '\/', $path);

        // Replace placeholders like {id} with a regex pattern
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^\/]+)', $path);

        // Anchor the pattern to start and end
        $pattern = '#^' . $pattern . '$#';

        return $pattern;
    }

    /**
     * Match the current request to a route
     * 
     * @param string $path Request path
     * @param string $method HTTP method
     * @return bool|array
     */
    private function match($path, $method)
    {
        // Create a temporary array with routes sorted by specificity
        $sortedRoutes = $this->routes;

        // Sort routes by path length (descending) for more specific matching
        usort($sortedRoutes, function ($a, $b) {
            // More segments should match first
            $aSegments = count(array_filter(explode('/', $a['path'])));
            $bSegments = count(array_filter(explode('/', $b['path'])));

            if ($aSegments !== $bSegments) {
                return $bSegments - $aSegments; // More segments first
            }

            // If same number of segments, longer paths are more specific
            return strlen($b['path']) - strlen($a['path']);
        });

        foreach ($sortedRoutes as $route) {
            // Check if the HTTP method matches
            if ($route['method'] != $method) {
                continue;
            }

            // Match the path against the route's regular expression
            if (preg_match($route['pattern'], $path, $matches)) {
                // Extract parameters from the match (excluding numeric matches)
                $params = [];
                foreach ($matches as $key => $value) {
                    if (!is_numeric($key)) {
                        $params[$key] = $value;
                    }
                }

                // Save parameters to be used later
                $this->params = $params;

                return $route;
            }
        }

        return false;
    }

    /**
     * Execute middleware stack
     * 
     * @param array $middlewares
     * @param Request $request
     * @param callable $next
     * @return mixed
     */
    private function runMiddleware(array $middlewares, Request $request, callable $next)
    {
        if (empty($middlewares)) {
            return $next($request);
        }

        $middleware = array_shift($middlewares);

        // If middleware is a string, instantiate it
        if (is_string($middleware)) {
            $middleware = new $middleware();
        }

        // Execute middleware
        return $middleware->handle($request, function ($request) use ($middlewares, $next) {
            return $this->runMiddleware($middlewares, $request, $next);
        });
    }

    /**
     * Resolve a model by its class name and ID
     * 
     * @param string $className Fully qualified class name
     * @param mixed $id ID value from route
     * @return object|null
     * @throws \Exception If model cannot be resolved
     */
    private function resolveModel($className, $id)
    {
        // Check if the class exists
        if (!class_exists($className)) {
            throw new \Exception("Model class {$className} does not exist.");
        }

        // Create a new instance of the model
        $model = new $className();

        // Check if the model uses a dynamic method system via a methods property
        $reflection = new \ReflectionClass($model);

        $methodsProperty = $reflection->getProperty('methods');
        $methodsProperty->setAccessible(true);
        $methods = $methodsProperty->getValue($model);

        // Check if 'find' exists in the methods array
        if (isset($methods['find'])) {
            $result = $methods['find']->call($model, $id);
            if ($result) {
                return $result;
            }
        }
        // Check if 'findBy' exists in the methods array
        elseif (isset($methods['findBy'])) {
            $result = $methods['findBy']->call($model, 'id', $id);
            if ($result) {
                return $result;
            }
        }
        // Check if 'get' exists after setting a where condition
        elseif (isset($methods['where']) && isset($methods['first'])) {
            $methods['where']->call($model, 'id', $id);
            $result = $methods['first']->call($model);
            if ($result) {
                return $result;
            }
        }

        // Fall back to traditional method checks
        if (method_exists($model, 'find')) {
            $result = $model->find($id);
            if ($result) {
                return $result;
            }
        } elseif (method_exists($className, 'findById')) {
            $result = $className::findById($id);
            if ($result) {
                return $result;
            }
        } elseif (method_exists($className, 'findBy')) {
            $result = $className::findBy('id', $id);
            if ($result) {
                return $result;
            }
        } elseif (method_exists($className, 'get')) {
            $result = $className::get($id);
            if ($result) {
                return $result;
            }
        }

        throw new \Exception("Could not resolve model {$className} with ID {$id}. Model resolution failed.");
    }

    /**
     * Dispatch the request to the appropriate controller
     * 
     * @param Request $request
     * @return mixed
     */
    public function dispatch(Request $request)
    {
        $path = $request->getPath();
        $method = $request->getMethod();
        // Match the route
        $route = $this->match($path, $method);
        if ($route) {
            try {
                // Add the matched parameters to the request object
                $request->setRouteParams($this->params);
                // Handler function to execute the controller
                $controllerHandler = function ($request) use ($route) {
                    $handler = $route['handler'];

                    // Resolve the controller and method
                    if (is_array($handler) && count($handler) === 2) {
                        [$controllerClass, $method] = $handler;
                        $controller = new $controllerClass();
                    } elseif (is_string($handler) && strpos($handler, '@') !== false) {
                        [$controllerClass, $method] = explode('@', $handler);
                        $controller = new $controllerClass();
                    } elseif (is_string($handler) && class_exists($handler)) {
                        $controller = new $handler();
                        if (!is_callable($controller)) {
                            throw new \Exception("Controller {$handler} is not invokable.");
                        }
                        return $controller($request); // Directly call the __invoke method
                    } else {
                        throw new \Exception('Invalid route handler format');
                    }

                    // Reflection for method parameters
                    $reflection = new \ReflectionMethod($controller, $method);
                    $params = $reflection->getParameters();

                    // Prepare parameters for the controller method
                    $parameters = [];

                    foreach ($params as $param) {
                        $paramName = $param->getName();
                        $paramType = $param->getType();

                        // If parameter is type-hinted with a class (potential model binding)
                        if ($paramType && !$paramType->isBuiltin()) {
                            $className = $paramType->getName();

                            // Check if parameter name exists in route parameters
                            if (isset($this->params[$paramName])) {
                                try {
                                    // Try to resolve the model
                                    $model = $this->resolveModel($className, $this->params[$paramName]);
                                    $parameters[] = $model;
                                } catch (\Exception $e) {
                                    // If model resolution fails and parameter is nullable, use null
                                    if ($paramType->allowsNull()) {
                                        $parameters[] = null;
                                    } else {
                                        throw $e;
                                    }
                                }
                            }
                            // Check if it matches a model name convention (e.g. User class for {user} parameter)
                            else {
                                $shortName = (new \ReflectionClass($className))->getShortName();
                                $possibleParamName = strtolower($shortName);

                                if (isset($this->params[$possibleParamName])) {
                                    try {
                                        // Try to resolve the model
                                        $model = $this->resolveModel($className, $this->params[$possibleParamName]);
                                        $parameters[] = $model;
                                    } catch (\Exception $e) {
                                        // If model resolution fails and parameter is nullable, use null
                                        if ($paramType->allowsNull()) {
                                            $parameters[] = null;
                                        } else {
                                            throw $e;
                                        }
                                    }
                                }
                                // If we can't find a matching parameter and it's Request class
                                elseif ($className === 'Core\Request') {
                                    $parameters[] = $request;
                                }
                                // If parameter has default value or is nullable
                                elseif ($param->isDefaultValueAvailable()) {
                                    $parameters[] = $param->getDefaultValue();
                                } elseif ($paramType->allowsNull()) {
                                    $parameters[] = null;
                                } else {
                                    throw new \Exception("Cannot resolve parameter {$paramName} of type {$className}.");
                                }
                            }
                        }
                        // If parameter is the Request object
                        elseif ($paramType && $paramType->getName() === 'Core\Request') {
                            $parameters[] = $request;
                        }
                        // For primitive type parameters, just use the route parameter value
                        else {
                            if (isset($this->params[$paramName])) {
                                $parameters[] = $this->params[$paramName];
                            } elseif ($param->isDefaultValueAvailable()) {
                                $parameters[] = $param->getDefaultValue();
                            } elseif ($paramType && $paramType->allowsNull()) {
                                $parameters[] = null;
                            } else {
                                throw new \Exception("Missing required parameter {$paramName}.");
                            }
                        }
                    }

                    // Call the controller method with the resolved parameters
                    return $reflection->invokeArgs($controller, $parameters);
                };

                // Execute middleware stack (if any) followed by the controller
                $result = !empty($route['middlewares'])
                    ? $this->runMiddleware($route['middlewares'], $request, $controllerHandler)
                    : $controllerHandler($request);

                // Check if result is a Redirect instance
                if ($result instanceof Redirect) {
                    // Execute the redirect immediately
                    $result->execute();
                }

                return $result;
            } catch (\Exception $e) {
                // Handle errors
                echo 'Error: ' . $e->getMessage();
            }
        } else {
            return view('errors/_404');
        }
    }
}
