<?php

namespace Forpart\Core;

class Response
{
    protected $statusCode = 200;
    protected $headers = [];

    public static function json($data = [], $statusCode = 200, $headers = [])
    {
        $instance = new self();
        return $instance->sendJson($data, $statusCode, $headers);
    }

    protected function sendJson($data, $statusCode, $headers)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');

        foreach ($headers as $key => $value) {
            header("{$key}: {$value}");
        }

        echo json_encode($data);
        exit;
    }
}
