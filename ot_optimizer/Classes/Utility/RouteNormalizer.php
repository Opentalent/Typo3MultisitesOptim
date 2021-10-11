<?php

namespace Opentalent\OtOptimizer\Utility;

class RouteNormalizer
{
    public static function normalizePath(string $path) {
        return '/' . trim($path, '/');
    }

    public static function normalizeDomain(string $domain) {
        return preg_replace('/https?:\/\/([\w\.]+)(?:\/.*)?/', '$1', $domain);
    }
}
