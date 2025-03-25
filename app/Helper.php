<?php

namespace App;

class Helper
{
    /**
     * Encode only the filename part of a URL, keeping the path structure intact
     * This ensures special characters in filenames are properly encoded for browser compatibility
     *
     * @param string $url
     * @return string
     */
    public static function encodeUrlFilename(string $url): string
    {
        // Check if URL is already encoded to prevent double-encoding
        if (strpos($url, '%') !== false) {
            // URL appears to be already encoded, return as is
            return $url;
        }
        
        $urlParts = parse_url($url);
        if (!isset($urlParts['path'])) {
            return $url;
        }
        
        // Get the path and split it to encode only the filename
        $pathParts = explode('/', $urlParts['path']);
        $filename = end($pathParts);
        
        // Only encode if the filename contains characters that need encoding
        if ($filename === rawurlencode($filename)) {
            // No encoding needed
            return $url;
        }
        
        // Encode only specific problematic characters instead of the entire filename
        // This is more targeted than rawurlencode which encodes almost everything
        $encodedFilename = str_replace(
            ['+', '=', ' ', '&', '#', '?', '%'],
            ['%2B', '%3D', '%20', '%26', '%23', '%3F', '%25'],
            $filename
        );
        
        // Replace the original filename with the encoded one
        $pathParts[count($pathParts) - 1] = $encodedFilename;
        $urlParts['path'] = implode('/', $pathParts);
        
        // Rebuild the URL
        $scheme = isset($urlParts['scheme']) ? $urlParts['scheme'] . '://' : '';
        $host = isset($urlParts['host']) ? $urlParts['host'] : '';
        $port = isset($urlParts['port']) ? ':' . $urlParts['port'] : '';
        $path = isset($urlParts['path']) ? $urlParts['path'] : '';
        $query = isset($urlParts['query']) ? '?' . $urlParts['query'] : '';
        $fragment = isset($urlParts['fragment']) ? '#' . $urlParts['fragment'] : '';
        
        return $scheme . $host . $port . $path . $query . $fragment;
    }
}
