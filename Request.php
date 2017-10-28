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
 * Wraps a request with its request path, post and get data.
 * The getCurrentPathArray() is an array that gets consumed one element after an other
 * via consumePathItem(). This is the way the paths elements is analysed and
 * processend in a Controller. The Controller itself consumes some elements of the path array.
 */
class Request {
    private $requestPath;
    private $currentPathArray;
    
    private $post, $get;
    
    public function __construct(string $path, array &$post, array &$get) {
        
        
        $path = \explode('?', $path);
        $path = isset($path[0]) ? $path[0] : '';
        
        $this->requestPath = $path;
        $this->currentPathArray = static::parsePath($this->requestPath);
        
        $this->post = $post;
        $this->get = $get;
    }
    
    public function getRequestPath(): string {
        return $this->requestPath;
    }
    
    public function getCurrentPathArray(): array {
        return $this->currentPathArray;
    }
    
    static function renderPath(array $path): string {
        return '/'.implode('/', $path);
    }
    
    static function parsePath(string $path): array {
        if (substr($path, -1,1) === '/') {
            $path = substr($path, 0, -1);
        }
        $arr = explode('/', $path);
        array_shift($arr);
        return $arr;
    }
    
    /**
     * 
     * @param int $count
     * @return string|array
     */
    public function consumePathItem($count = 1) {
        if ($count == 1) {
            return array_shift($this->currentPathArray);
        }
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $item = array_shift($this->currentPathArray);
            if (is_null($item)) {break;}
            $result[] = $item;
        }
        return $result;
    }
    
    public function post($k, $default = null) {
        if (!isset($this->post[$k])) {
            return $default;
        }
        return $this->post[$k];
    }
    
    public function get($k, $default = null) {
        if (!isset($this->get[$k])) {
            return $default;
        }
        return $this->get[$k];
    }
}

