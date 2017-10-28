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
 * A Templating engine that uses simple PHP-Scripts.
 * 
 * Thy will be included in the Templates getOutput() method,
 * so in the template skript $this will be the template instance.
 */
class PHPFileTemplate {
    protected $controller = null;
    protected $params = [];
    protected $context = null;
    
    static protected $templateRoot = null;
    
    protected $name;
    
    protected $filename = null;
    
    
    public function __construct(string $name, &$controller) {
        $this->controller = $controller;
        $this->resetParams();
        $this->setName($name);
    }
    
    public function getOutput(bool $trim = true): string {
        if ($this->isExisting()) {
            \ob_start();
            include $this->getFilename();
            $ret = \ob_get_contents();
            \ob_end_clean();
            
            if ($trim) {
                $ret = trim($ret);
            }
            return $ret;
        }
        throw new TemplateException('Template "' . $this->name . '" existiert nicht. gesuchte Template-Datei: ' . $this->getFilename());
    }

    public function getFilename() {
        if ($this->filename === null) {
            $this->filename = $this->name;
            if (strpos($this->filename, '.php') !== (strlen($this->filename)-4)) {
                $this->filename .= '.php';
            }
            $this->filename = APP_TEMPLATE_ROOT . $this->filename;
        }
        return $this->filename;
    }
    
    public function setName($name) {
        $this->name = $name;
        $this->filename = null;
    }
    
    public function isExisting(): bool {
        return file_exists($this->getFilename());
    }
    
    public function hasController(): bool {
        return !is_null($this->controller);
    }
    
    public function &getController() : \WEPPOLight\Controller {
        if (!$this->controller) {
            throw new \Exception("Template has no Controller");
        }
        return $this->controller;
    }
    
    public function setController(\WEPPOLight\Controller &$c) {
        $this->controller = $c;
    }
    
    
    /**
     * Setzt das gesamte Parameter-Array
     * 
     * @param array $params Parameter
     */
    public function setParams(array $params) {
            $this->params = $params;
    }
    /**
     * Die Template-Parameter zurücksetzen
     */
    public function resetParams() {
        $this->params = array();
    }

    /**
     * Template-Parameter abfragen
     * 
     * Wenn der Parameter nicht gesetzt ist und kein $default gesetzt ist, so wird '' zurück gegeben.
     * 
     * @param string $n Schlüssel
     * @param mixed $default Wert, der zurück kommt, wenn Parameter nicht gesetzt
     * @return string
     */
    function get($n, $default = null) {
        // TODO wenn nicht vorhanden, parent fragen!
        if (isset($this->params[$n])) {
            return $this->params[$n];
        }
        if ($default !== null) {
            return $default;
        }
        return '';
    }
    
    /**
     * Template-Parameter definiert?
     * 
     * @param string $n Schlüssel
     * @return boolean
     */
    function hasParam($n) {
        return isset($this->params[$n]);
    }
    
    /**
     * Einen Template-Parameter setzen
     * 
     * @param string $n Schlüssel
     * @param mixed $v Wert
     */
    function set($n, $v) {
        $this->params[$n] = &$v;
    }

    /**
     * Daten einem Template-Parameter hinzufügen
     * 
     * Dafür wird ggf. aus dem bisherigen Wert ein Array gemacht und der neue Wert wird 
     * dem Array hinzugefügt.
     * @param string $n Schlüssel des Template-Parameters
     * @param mixed $v Wert, der hinzugefügt wird
     * @param mixed $k Schlüssel für den Wert im (neuen) Array
     */
    function add($n, $v, $k = null) {
        if (isset($this->params[$n])) {
            if (!\is_array($this->params[$n])) {
                $this->params[$n] = array($this->params[$n]);
            }
        } else {
            $this->params[$n] = array();
        }
        if ($k !== null) {
            $this->params[$n][$k] = &$v;
        } else {
            $this->params[$n][] = &$v;
        }
    }
    
    /**
     * Startet das Aufzeichnen eines Parameters für dieses Template
     * 
     * Der Ausgabe-Buffer wird gestartet.
     * 
     * @param string $name Parameter-Name, wird hier nicht verwendet, ist nur zur Übersicht beim Aufruf 
     */
    public function startParam($name) {
        \ob_start();
    }

    /**
     * Beendet das Aufzeichnen eines Parameters und setzt den Parameter
     * 
     * Der Ausgabe-Buffer wird beendet.
     * 
     * @param string $name Parameter-Name
     * @param callable $callback Callback-Funktion, die vor dem setzen auf den Parameter-Wert angewendet wird. Signatur: string cb(string)
     */
    public function endParam($name, $callback = null) {
        $param = \ob_get_contents();
        \ob_end_clean();
        if ($callback !== null) {
            $param = call_user_func($callback, $param);
        }
        //return $param;
        $this->set($name, $param);
    }

    /**
     * Holt die im Array angegebenen Parameter aus einem anderen Template
     */
    public function setParamsFromTemplate(array $paramNames, TemplateBase &$template) {
        foreach ($paramNames as $k) {
            if ($template->hasParam($k)) {
                $this->set($k, $template->get($k));
            }
        }
    }
        
}








