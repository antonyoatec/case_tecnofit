<?php

declare(strict_types=1);

if (! function_exists('env')) {
    /**
     * Gets the value of an environment variable.
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
        }

        if (($valueLength = strlen($value)) > 1 && $value[0] === '"' && $value[$valueLength - 1] === '"') {
            return substr($value, 1, -1);
        }

        return $value;
    }
}

if (! function_exists('config')) {
    /**
     * Get / set the specified configuration value.
     */
    function config(string $key = null, mixed $default = null): mixed
    {
        if (is_null($key)) {
            return \Hyperf\Utils\ApplicationContext::getContainer()->get(\Hyperf\Contract\ConfigInterface::class);
        }

        return \Hyperf\Utils\ApplicationContext::getContainer()->get(\Hyperf\Contract\ConfigInterface::class)->get($key, $default);
    }
}