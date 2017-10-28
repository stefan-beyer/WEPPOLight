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
 * Organize simple routes and findes the route to a given request.
 */
class Router {
    private $routes;
    
    public function __construct() {
        $this->routes = [];
    }
    
    /**
     * Add route to config
     * 
     * @param \WEPPOLight\Route $rt
     */
    public function addRoute(Route $rt) {
        $this->routes[] = $rt;
    }
    
    /**
     * Find route for request
     * 
     * @param \WEPPOLight\Request $r
     * @return \WEPPOLight\Controller
     * @throws \Exception Route Not Found
     */
    public function findRoute(Request &$r): Controller {
        $path = $r->getRequestPath();
        foreach ($this->routes as &$rt) {
            $pattern = $rt->getPattern();
            
            $isExact = true;
            if (substr($pattern, -3) == '...') {
                $pattern = substr($pattern, 0, -3);
                $isExact = false;
            }
            if (static::isMatch($pattern, $path, $isExact)) {
                $patternLenth = count(Request::parsePath($pattern));
                $r->consumePathItem($patternLenth);
                return $this->createController($rt, $r, []);
            }
        }
        throw new \Exception('Route Not Found');
    }
    
    /**
     * 
     * @param string $pattern
     * @param string $path
     * @param bool $isExact
     * @return bool
     */
    static protected function isMatch(string $pattern, string $path, bool $isExact): bool {
        #echo 'patt=',$pattern,' path=',$path, $isExact ? 'exact ':' ', '<br/>';
        if ($isExact) {
            return $path === $pattern;
        }
        
        return (strpos($path, $pattern) === 0);
    }
    
    /**
     * Creates the controller for the route.
     * 
     * @param \WEPPOLight\Route $rt
     * @param \WEPPOLight\Request $r
     * @param type $matches
     * @return \WEPPOLight\className
     * @throws \Exception Controller Not Found
     */
    private function createController(Route $rt, Request &$r, $matches) {
        $className = $rt->getClassName();
        if (!class_exists($className)) {
            throw new \Exception('Controller Not Found');
        }
        $controller = new $className($r, $rt, $matches);
        return $controller;
    }
}

