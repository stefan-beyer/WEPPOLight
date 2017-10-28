<?php

/**
 * WEPPOLight 1.0
 * @package weppolight
 * @author Stefan Beyer<info@wapplications.net>
 * @see http://weppolight.wapplications.net/
 * @license MIT
 */

namespace WEPPOLight;

/**
 * Base for a controller.
 * 
 * The default dispacher consumes an path array element
 * and tries to run a method called action_[element]().
 * If the element is empty it calls action_index().
 * If you need some other behaviour just overwrite the dispatch() method
 */
class Controller {

    protected $request, $matches, $route;

    public function __construct(Request &$request, Route $route, array $matches) {
        $this->request = $request;
        $this->matches = $matches;
        $this->route = $route;
    }

    public function dispatch() {
        $action = $this->getRequest()->consumePathItem();
        if ($action) {
            call_user_func([$this, 'action_' . $action]);
        } else {
            $this->action_index();
        }
    }

    public function &getRequest(): Request {
        return $this->request;
    }

    public function &getRoute(): Route {
        return $this->route;
    }

    /**************************************************************************/
    // Helper Functions
    
    /**
     * Mail-Adresse überprüfen
     * 
     * @param string $mail Mailadresse
     * @return boolean
     */
    static function isValidEmail($mail) {
        //$mailRegEx = '/^([a-z0-9])(([-a-z0-9._])*([a-z0-9]))*\@([a-z0-9])' .
        //'(([a-z0-9-])*([a-z0-9]))+' . '(\.([a-z0-9])([-a-z0-9_-])?([a-z0-9])+)+$/i';
        //return preg_match ($mailRegEx, $mail);
        return \filter_var($mail, \FILTER_VALIDATE_EMAIL);
    }

    /**
     * Dateiendung ermitteln
     * 
     * @param string $file Dateiname
     * @return string Dateiendung
     */
    static function getExtention($file) {
        return \strtolower(\array_pop(\explode(".", $file)));
    }

    /**
     * Mail-Betreff richtig codieren
     * 
     * @param string $s Betreff
     * @return string
     */
    static function mailSubjectEncode($s) {
        //$s = utf8_decode($s);
        //return "=?ISO-8859-1?B?" . base64_encode($s) . "?=";
        return "=?UTF-8?B?" . \base64_encode($s) . "?=";
    }

}
