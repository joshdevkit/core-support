<?php

namespace Core;

use Whoops\Run;
use Whoops\Handler\PrettyPageHandler;

class Application
{
    /**
     * Run the application.
     */
    public static function run()
    {

        // Start the session
        Session::start();

        // Initialize the path resolver
        PathResolver::init(dirname(__DIR__));

        // Load environment variables
        self::loadEnv();


        self::registerErrorHandler();

        // Initialize the router
        $route = new Router();

        // Load the route configuration file
        require PathResolver::basePath('routes/web.php');

        // Initialize the request object
        $request = new Request();

        \Core\Url::setRouter($route);
        \Core\Redirect::setRouter($route);
        // Dispatch the request to the router
        $route->dispatch($request);
    }

    /**
     * Load the .env file and set environment variables.
     */
    private static function loadEnv()
    {
        $envFile = PathResolver::basePath('.env');
        if (file_exists($envFile)) {
            $dotenv = parse_ini_file($envFile, true);
            foreach ($dotenv as $key => $value) {
                $_ENV[$key] = $value;
            }
        }
    }

    /**
     * Register Whoops as the error handler.
     */
    private static function registerErrorHandler()
    {
        $whoops = new Run();
        $whoops->pushHandler(new PrettyPageHandler());

        $env = env('APP_ENV', 'production');

        if ($env === 'development') {
            $whoops->register();
        } else {
            $whoops->pushHandler(function ($e) {
                ob_start();

                http_response_code(500);

                echo <<<HTML
                <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <title>Something went wrong</title>
                    <style>
                        body {
                            background: #f8f9fa;
                            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                            display: flex;
                            justify-content: center;
                            align-items: center;
                            height: 100vh;
                            margin: 0;
                        }
                        .error-container {
                            background: #fff;
                            padding: 40px;
                            border-radius: 12px;
                            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
                            text-align: center;
                        }
                        h1 {
                            font-size: 2rem;
                            color: #dc3545;
                        }
                        p {
                            font-size: 1rem;
                            color: #6c757d;
                        }
                        a {
                            display: inline-block;
                            margin-top: 20px;
                            color: #007bff;
                            text-decoration: none;
                        }
                        a:hover {
                            text-decoration: underline;
                        }
                    </style>
                </head>
                <body>
                    <div class="error-container">
                        <h1>Oops! Something went wrong.</h1>
                        <p>We're working to fix it. Please try again later.</p>
                    </div>
                </body>
                </html>
                HTML;
                ob_end_flush();
                die();
            });

            $whoops->register();
        }
    }
}
