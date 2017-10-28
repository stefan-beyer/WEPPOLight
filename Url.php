<?php
/**
 * WEPPOLight 1.0
 * @package weppolight
 * @author Stefan Beyer<info@wapplications.net>
 * @see http://weppolight.wapplications.net/
 * @license MIT
 * 
 */


namespace WEPPOLight;

/**
 * URL Helpers
 */
class Url {
    
    static public function &getHost() {
        static $host = null;
        if ($host === null) {
            if (isset($_SERVER['HTTP_HOST']) && is_string($_SERVER['HTTP_HOST'])) {
                $host = $_SERVER['HTTP_HOST'];
                $h = strstr($host, ':', true);
                $host = $h!==false ? $h : $host;
            } else {
                $host = $_SERVER['SERVER_NAME'];
            }
        }
        return $host;
    }
    
    /**
     * Erzeugt eine absolute URL aus einem Pfad
     * 
     * @param	string	$path Pfad
     * @return	string	Absolute URL
     */
    static public function getAbsUrl($path) {
        if (strpos($path, 'https://')===0) {
            return $path;
        }
        if (strpos($path, 'http://')===0) {
            return $path;
        }
        
        if (!$path) {
            $path = '/';
        } else if ($path[0] != '/') {
            $path = '/' . $path;
        }
        $https = isset($_SERVER['HTTPS']) ? !!$_SERVER['HTTPS'] : false;
        $port = isset($_SERVER['SERVER_PORT']) ? intval($_SERVER['SERVER_PORT']) : '';
        
        if (!$port) $port = '';
        if ($https && $port == '443') $port = '';
        if (!$https && $port == '80') $port = '';
        
        if (!empty($port)) $port = ':'.$port;
        
        $host = self::getHost();
        
        return 'http' . ($https ? 's' : '') . '://' . $host . $port . $path;
    }
    
}
