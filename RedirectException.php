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
 * One hell of a special Exception.
 * 
 * Throw this Exception and it will be catched in the main skript.
 * This is used so that no other code of the controller will be executed but the system can
 * shut down correctly. No need to call exit() or die().
 */
class RedirectException extends \Exception {
    
    public function __construct($url, $code = 303) {
        parent::__construct($url, $code);
    }
    
    public function getUrl() {
        return $this->getMessage();
    }
}