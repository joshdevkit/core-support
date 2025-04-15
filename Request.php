<?php

namespace Forpart\Core;

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class Request
{
    /**
     * @var SymfonyRequest
     */
    private $request;
    private $routeParams = [];
    /**
     * Constructor to initialize Symfony Request
     */
    public function __construct()
    {
        $this->request = SymfonyRequest::createFromGlobals();
    }

    /**
     * Get the request method
     * 
     * @return string
     */
    public function getMethod()
    {
        return $this->request->getMethod();
    }

    /**
     * Get the request path
     * 
     * @return string
     */
    public function getPath()
    {
        return $this->request->getPathInfo();
    }

    /**
     * Get all query parameters
     * 
     * @return array
     */
    public function getQueryParams()
    {
        return $this->request->query->all();
    }

    /**
     * Get a specific query parameter
     * 
     * @param string $name Parameter name
     * @param mixed $default Default value if parameter doesn't exist
     * @return mixed
     */
    public function getQuery($name, $default = null)
    {
        return $this->request->query->get($name, $default);
    }

    /**
     * Get all POST data
     * 
     * @return array
     */
    public function getPostData()
    {
        return $this->request->request->all();
    }

    /**
     * Get a specific POST parameter
     * 
     * @param string $name Parameter name
     * @param mixed $default Default value if parameter doesn't exist
     * @return mixed
     */
    public function getPost($name, $default = null)
    {
        return $this->request->request->get($name, $default);
    }

    /**
     * Get all request data (POST or JSON)
     * 
     * @return array
     */
    public function getBody()
    {
        if ($this->getMethod() === 'GET') {
            return [];
        }

        if ($this->isJson()) {
            return $this->request->getContent();
        }

        return $this->getPostData();
    }

    /**
     * Check if request has JSON content type
     * 
     * @return bool
     */
    public function isJson()
    {
        return $this->request->headers->get('Content-Type') === 'application/json';
    }

    /**
     * Get a specific input value (from POST, JSON or route params)
     * 
     * @param string $name Parameter name
     * @param mixed $default Default value if parameter doesn't exist
     * @return mixed
     */
    public function input($name, $default = null)
    {
        if (isset($this->routeParams[$name])) {
            return $this->routeParams[$name];
        }

        return $this->request->get($name, $default);
    }

    /**
     * Get all inputs (query + body + route params)
     * 
     * @return array
     */
    public function all()
    {
        return array_merge($this->getQueryParams(), $this->getPostData(), $this->routeParams);
    }

    /**
     * Set route parameters
     * 
     * @param array $params
     * @return void
     */
    public function setRouteParams(array $params)
    {
        $this->routeParams = $params;
    }

    /**
     * Get route parameters
     * 
     * @return array
     */
    public function getRouteParams()
    {
        return $this->routeParams;
    }

    /**
     * Get a specific route parameter
     * 
     * @param string $name Parameter name
     * @param mixed $default Default value if parameter doesn't exist
     * @return mixed
     */
    public function getRouteParam($name, $default = null)
    {
        return $this->routeParams[$name] ?? $default;
    }

    /**
     * Check if the request is an AJAX request
     * 
     * @return bool
     */
    public function isAjax()
    {
        return $this->request->isXmlHttpRequest();
    }

    /**
     * Get all files uploaded through the request
     * 
     * @return array
     */
    public function getFiles()
    {
        return $this->request->files->all();
    }

    /**
     * Get a specific file from the uploaded files
     * 
     * @param string $name File field name
     * @return Symfony\Component\HttpFoundation\File\UploadedFile|null
     */
    public function getFile($name)
    {
        return $this->request->files->get($name);
    }

    /**
     * Get the file name of a specific uploaded file
     * 
     * @param string $name File field name
     * @return string|null
     */
    public function getFileName($name)
    {
        $file = $this->getFile($name);
        return $file ? $file->getClientOriginalName() : null;
    }

    /**
     * Get the file size of a specific uploaded file
     * 
     * @param string $name File field name
     * @return int|null
     */
    public function getFileSize($name)
    {
        $file = $this->getFile($name);
        return $file ? $file->getSize() : null;
    }

    /**
     * Get the file type of a specific uploaded file
     * 
     * @param string $name File field name
     * @return string|null
     */
    public function getFileType($name)
    {
        $file = $this->getFile($name);
        return $file ? $file->getClientMimeType() : null;
    }

    /**
     * Check if a file has been uploaded for a specific field
     * 
     * @param string $name File field name
     * @return bool
     */
    public function hasFile($name)
    {
        return $this->request->files->has($name);
    }
}
