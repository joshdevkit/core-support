<?php

namespace Forpart\Core;

/**
 * URL generation helper class
 */
class Url
{
    /**
     * Router instance
     * 
     * @var Router
     */
    protected static $router;

    /**
     * Set the router instance
     * 
     * @param Router $router
     * @return void
     */
    public static function setRouter(Router $router)
    {
        self::$router = $router;
    }

    /**
     * Generate a URL for a named route
     * 
     * @param string $name Route name
     * @param array $params Route parameters
     * @return string
     * @throws \Exception If the router is not initialized, the route doesn't exist, or parameters are missing
     */
    public static function route(string $name, array $params = [])
    {
        if (!self::$router) {
            throw new \Exception("Router is not initialized. Make sure to call Url::setRouter() before using route generation.");
        }

        // Check if the route exists
        $route = self::$router->getNamedRoute($name);

        if (!$route) {
            throw new \Exception("Route with name '{$name}' not found in the routes collection.");
        }

        // Extract required parameters from route path
        preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $route['path'], $matches);
        $requiredParams = $matches[1] ?? [];

        // Check if all required parameters are provided
        foreach ($requiredParams as $param) {
            if (!isset($params[$param])) {
                throw new \Exception("Missing required parameter '{$param}' for route '{$name}'.");
            }
        }

        return self::$router->route($name, $params);
    }

    /**
     * Generate a URL with base URL prefixed
     * 
     * @param string $path
     * @return string
     */
    public static function to(string $path)
    {
        // Remove leading slash if exists
        $path = ltrim($path, '/');

        // Get base URL from server variables
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = "$protocol://$host";

        return "$baseUrl/$path";
    }

    /**
     * Generate a URL for an asset
     * 
     * @param string $path
     * @return string
     */
    public static function asset(string $path)
    {
        // Remove leading slash if exists
        $path = ltrim($path, '/');

        return self::to("assets/$path");
    }
}
