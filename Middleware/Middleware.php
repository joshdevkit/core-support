<?php

namespace Core\Middleware;

use Core\Request;

interface Middleware
{
    /**
     * Handle the request and call the next middleware
     * 
     * @param Request $request
     * @param callable $next
     * @return mixed
     */
    public function handle(Request $request, callable $next);
}
