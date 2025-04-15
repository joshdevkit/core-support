<?php

namespace Core;

class PathResolver
{
    /**
     * Base application path
     * @var string
     */
    private static $basePath;

    /**
     * Initialize the base path
     * @param string $basePath
     */
    public static function init($basePath)
    {
        self::$basePath = rtrim($basePath, '/');
    }

    /**
     * Get the base path
     * @param string $path Additional path to append
     * @return string
     */
    public static function basePath($path = '')
    {
        return self::$basePath . ($path ? '/' . ltrim($path, '/') : '');
    }

    /**
     * Get the public path
     * @param string $path Additional path to append
     * @return string
     */
    public static function publicPath($path = '')
    {
        return self::basePath('public' . ($path ? '/' . ltrim($path, '/') : ''));
    }

    /**
     * Get the source path
     * @param string $path Additional path to append
     * @return string
     */
    public static function srcPath($path = '')
    {
        return self::basePath('src' . ($path ? '/' . ltrim($path, '/') : ''));
    }

    /**
     * Get the views path
     * @param string $path Additional path to append
     * @return string
     */
    public static function viewsPath($path = '')
    {
        // Replace dots with slashes to support dot notation
        $normalizedPath = str_replace('.', '/', $path);

        // Ensure the path starts from the src/Views directory and append .php extension
        return self::basePath('ui' . ($normalizedPath ? '/' . ltrim($normalizedPath, '/') : '') . '.php');
    }

    /**
     * Get the storage path
     * @param string $path Additional path to append
     * @return string
     */
    public static function storagePath($path = '')
    {
        return self::basePath('storage' . ($path ? '/' . ltrim($path, '/') : ''));
    }

    /**
     * Get the config path
     * @param string $path Additional path to append
     * @return string
     */
    public static function configPath($path = '')
    {
        return self::srcPath('routes' . ($path ? '/' . ltrim($path, '/') : ''));
    }

    /**
     * Convert a filesystem path to a properly formatted URL path
     * @param string $path
     * @return string
     */
    public static function toUrl($path)
    {
        $publicPath = self::publicPath();
        if (strpos($path, $publicPath) === 0) {
            $path = substr($path, strlen($publicPath));
        }
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    /**
     * Resolve a class name to a file path
     * @param string $className
     * @return string|null
     */
    public static function resolveClass($className)
    {
        // Convert namespace separators to directory separators
        $className = str_replace('\\', '/', $className);

        // Remove App\ from the beginning if it exists
        if (strpos($className, 'App/') === 0) {
            $filePath = self::srcPath(substr($className, 4) . '.php');
        } else {
            $filePath = self::basePath($className . '.php');
        }

        return file_exists($filePath) ? $filePath : null;
    }
}
