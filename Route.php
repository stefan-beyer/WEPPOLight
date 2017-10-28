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
 * Path pattern and coresponding controller class name.
 * Some configuration data can be provided so that one controller can be used
 * multiple times with different configurations.
 */
class Route {
    private $pattern = '', $className = '', $config = [];
    
    public function __construct($pattern, $className, $config = []) {
        $this->pattern = $pattern;
        $this->className = $className;
        $this->config = $config;
    }
    
    public function getPattern(): string {
        return $this->pattern;
    }
    
    public function getClassName(): string {
        return $this->className;
    }
    
    /**
     * If $key is null, all config will be returned.
     * 
     * @param type $key
     * @return type
     */
    public function getConfig($key = null) {
        if (is_null($key)) {
            return $this->config;
        }
        if (!isset($this->config[$key])) {
            return null;
        }
        return $this->config[$key];
    }
}
