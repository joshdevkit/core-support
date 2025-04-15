<?php

namespace Core;

/**
 * Redirect class for handling HTTP redirects
 */
class Redirect
{
    /**
     * The URL to redirect to
     * 
     * @var string
     */
    protected $url;

    /**
     * HTTP status code for the redirect
     * 
     * @var int
     */
    protected $statusCode = 302;

    /**
     * Additional headers for the redirect
     * 
     * @var array
     */
    protected $headers = [];

    /**
     * Flash data to be stored in session
     * 
     * @var array
     */
    protected $with = [];

    /**
     * Router instance
     * 
     * @var Router|null
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
     * Constructor
     * 
     * @param string|null $url Optional URL to redirect to
     */
    public function __construct($url = null)
    {
        if ($url) {
            $this->url = $url;
        }
    }

    /**
     * Redirect to a specific URL
     * 
     * @param string $url The URL to redirect to
     * @return $this
     */
    public function to($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * Redirect to a named route
     * 
     * @param string $name Route name
     * @param array $params Route parameters
     * @return $this
     */
    public function route($name, $params = [])
    {
        if (!self::$router) {
            throw new \Exception("Router has not been set in the Redirect class");
        }

        $this->url = self::$router->route($name, $params);
        return $this;
    }

    /**
     * Redirect to the previous URL
     * 
     * @param string $fallback Fallback URL if HTTP_REFERER is not set
     * @return $this
     */
    public function back($fallback = '/')
    {
        $this->url = $_SERVER['HTTP_REFERER'] ?? $fallback;
        return $this;
    }

    /**
     * Set the HTTP status code for the redirect
     * 
     * @param int $code HTTP status code
     * @return $this
     */
    public function withStatus($code)
    {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * Add a header to the redirect
     * 
     * @param string $name Header name
     * @param string $value Header value
     * @return $this
     */
    public function withHeader($name, $value)
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Flash data to the session for the next request
     * 
     * @param string $key Session key
     * @param mixed $value Session value
     * @return $this
     */
    public function with($key, $value)
    {
        $this->with[$key] = $value;
        return $this;
    }

    /**
     * Flash input data to the session for the next request
     * 
     * @param array $input Input data
     * @return $this
     */
    public function withInput(?array $input = null)
    {
        if ($input === null) {
            $input = $_POST;
        }

        $_SESSION['old_input'] = $input;
        return $this;
    }

    /**
     * Flash errors to the session for the next request
     * 
     * @param array $errors Error messages
     * @return $this
     */
    public function withErrors(array $errors)
    {
        $_SESSION['errors'] = $errors;
        $_SESSION['errors_flash'] = true;
        return $this;
    }

    /**
     * Execute the redirect
     * 
     * @return void
     */
    public function execute()
    {
        if (!$this->url) {
            throw new \Exception('Redirect URL not specified');
        }

        // Store flash data in session
        foreach ($this->with as $key => $value) {
            $_SESSION[$key] = $value;
            $_SESSION[$key . '_flash'] = true;
        }

        // Set status code
        http_response_code($this->statusCode);

        // Set headers
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        // Redirect
        header("Location: {$this->url}");
        exit;
    }

    /**
     * Magic method to execute the redirect when object is treated as a string
     * 
     * @return string
     */
    public function __toString()
    {
        $this->execute();
        return '';
    }

    /**
     * Magic method to execute the redirect when the script execution ends
     */
    public function __destruct()
    {
        if ($this->url) {
            $this->execute();
        }
    }
}
