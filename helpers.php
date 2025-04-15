<?php

use Core\Auth;
use Core\PathResolver;
use Core\Redirect;
use Core\Response;
use Core\Url;
use Core\View;

if (!function_exists('response')) {
    function response()
    {
        return new class {
            public function json($data = [], $statusCode = 200, $headers = [])
            {
                return Response::json($data, $statusCode, $headers);
            }
        };
    }
}

if (!function_exists('redirect')) {
    /**
     * Get a redirect instance
     * 
     * @return \Core\Redirect
     */
    function redirect()
    {
        return new Redirect();
    }
}


if (!function_exists('auth')) {
    function auth()
    {
        $auth =  new Auth();

        return $auth::user();
    }
}

if (!function_exists('view')) {
    /**
     * Render a view with the provided data.
     *
     * @param string $view The name of the view file (without extension)
     * @param array $data The data to pass to the view
     */
    function view($view, $data = [])
    {
        View::render($view, $data);
    }
}

if (!function_exists('class_uses_recursive')) {
    /**
     * Get all traits used by a class, including traits used by parent classes and other traits.
     *
     * @param  object|string  $class
     * @return array
     */
    function class_uses_recursive($class)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        $results = [];

        // Get traits of all parent classes
        do {
            $results = array_merge(class_uses($class), $results);
        } while ($class = get_parent_class($class));

        // Get traits of all traits
        foreach (array_keys($results) as $trait) {
            $usedTraits = class_uses($trait);

            if (!empty($usedTraits)) {
                $results = array_merge($results, $usedTraits);
            }
        }

        return array_unique($results);
    }
}

if (!function_exists('class_basename')) {
    /**
     * Get the class "basename" of the given object / class.
     *
     * @param  string|object  $class
     * @return string
     */
    function class_basename($class)
    {
        $class = is_object($class) ? get_class($class) : $class;

        return basename(str_replace('\\', '/', $class));
    }
}


if (!function_exists('env')) {
    /**
     * Get the value of an environment variable.
     * 
     * @param string $key The environment variable key
     * @param mixed $default Default value if the variable doesn't exist
     * @return string|mixed The value of the environment variable or default value
     */
    function env($key, $default = null)
    {
        // Load the .env file only if it's not already loaded
        if (!isset($GLOBALS['_ENV'])) {
            $envFile = PathResolver::basePath('.env');
            if (file_exists($envFile)) {
                $dotenv = parse_ini_file($envFile, true);
                $GLOBALS['_ENV'] = $dotenv;
            } else {
                $GLOBALS['_ENV'] = [];
            }
        }

        // Return the value of the environment variable, or default if not found
        return isset($GLOBALS['_ENV'][$key]) ? $GLOBALS['_ENV'][$key] : $default;
    }
}


if (!function_exists('route')) {
    function route($routeName = "", $params = [])
    {
        return Url::route($routeName, $params);
    }
}
