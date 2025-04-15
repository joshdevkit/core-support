<?php

namespace Forpart\Core;

class RouteRegistrar
{
    /**
     * Router instance
     * 
     * @var Router
     */
    protected $router;

    /**
     * Route data
     * 
     * @var array
     */
    protected $route;

    /**
     * Constructor
     * 
     * @param Router $router
     * @param array $route
     */
    public function __construct(Router $router, array $route)
    {
        $this->router = $router;
        $this->route = $route;
    }

    /**
     * Name the route
     * 
     * @param string $name
     * @return Router
     */
    public function name(string $name)
    {
        return $this->router->nameRoute($this->route, $name);
    }

    /**
     * Proxy all other method calls to the router
     * 
     * @param string $method
     * @param array $arguments
     * @return mixed
     */
    public function __call(string $method, array $arguments)
    {
        return call_user_func_array([$this->router, $method], $arguments);
    }
}
