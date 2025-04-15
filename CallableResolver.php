<?php

namespace Forpart\Core;

/**
 * Class to handle callable resolution for controllers and methods
 */
class CallableResolver
{
    /**
     * Resolve a string controller/method definition into a callable
     * Format: 'App\Controllers\HomeController@index' or 'App\Controllers\HomeController::index'
     * 
     * @param string|array $handler
     * @return callable|null
     * @throws \Exception
     */
    public static function resolve($handler)
    {
        // If it's already a callable, return it
        if (is_callable($handler)) {
            return $handler;
        }

        // If it's an array with [ControllerClass, method], use it directly
        if (is_array($handler) && count($handler) === 2) {
            list($class, $method) = $handler;

            if (!class_exists($class)) {
                throw new \Exception("Controller class '{$class}' not found");
            }

            $instance = new $class();

            if (!method_exists($instance, $method)) {
                throw new \Exception("Method '{$method}' not found in controller '{$class}'");
            }

            return [$instance, $method];
        }

        // Handle string format: 'ControllerClass@method' or 'ControllerClass::method'
        if (is_string($handler)) {
            // Check for @ or :: notation
            if (strpos($handler, '@') !== false) {
                list($class, $method) = explode('@', $handler, 2);
            } elseif (strpos($handler, '::') !== false) {
                list($class, $method) = explode('::', $handler, 2);
            } else {
                throw new \Exception("Invalid handler format. Use 'ControllerClass@method' or 'ControllerClass::method'");
            }

            if (!class_exists($class)) {
                throw new \Exception("Controller class '{$class}' not found");
            }

            $instance = new $class();

            if (!method_exists($instance, $method)) {
                throw new \Exception("Method '{$method}' not found in controller '{$class}'");
            }

            return [$instance, $method];
        }

        throw new \Exception("Invalid handler type. Must be a callable, array [class, method] or string 'Class@method'");
    }

    /**
     * Call a handler with parameters
     * 
     * @param string|array|callable $handler
     * @param array $params Parameters to pass to the handler
     * @return mixed
     */
    public static function call($handler, array $params = [])
    {
        $callable = self::resolve($handler);
        return call_user_func_array($callable, $params);
    }

    /**
     * Check if a handler exists and is callable
     * 
     * @param string|array|callable $handler
     * @return bool
     */
    public static function exists($handler)
    {
        try {
            $callable = self::resolve($handler);
            return is_callable($callable);
        } catch (\Exception $e) {
            return false;
        }
    }
}
