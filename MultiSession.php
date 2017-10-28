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
 * MultiSession
 * 
 * Diese Klasse ermöglicht es, mehrere getrennte PHP-Sessions zu verwalten.
 * Die Session-Variablen der einzelnen Sessions sind getrennt von einander.
 * Die Funktionalität setzt auf die session_*() Funktionen von PHP auf
 * und ermöglicht über wechseln von Session-Id und Session-Name, das Auslesen
 * und Manipulieren mehrerer Sessions. Bei jeder Operation wird die gewünschte
 * Session geöffnet und danach wieder geschlossen (nicht beendet!).
 * 
 * Eine Session kann gezielt gestartet und beendet werden. Jede MultiSession
 * hat dabei ihren eigenen Cookie.
 * 
 * MultiSessions können nicht parallel mit manuellen session_start() Aufrufen verwendet werden.
 * 
 */
class MultiSession {
    
     /** @var bool Ist so lange true, bis das erste MultiSession-Objekt erzeugt wurde. Für interne Zwecke. */
    static $first = true;
    
    /** @var string Name der Session */
    private $name;
    
    /** @var bool läauft die Session? Use ->isRunning(); */
    private $running;
    
    /** @var string uid der Session - von PHP vergeben */
    private $id = null;
    
    /** @var array Eingelesene Session-Daten */
    private $data;




    /**
     * Erzeugt eine MultiSession mit einem Namen
     * 
     * @param string $name
     * @throws Exception Bei Mix von manueller Session und MultiSession hindeutet.
     */
    public function __construct($name) {
        # Wenn erkannt wurde, dass vor der ersten Objekterzeugung bereits ein session_start() erfolgt hat.
        # Das deutet auf einen Mix von manueller Session und MultiSession hin.
        if (self::$first && !empty(session_id())) {
            throw new \Exception('Do not use MultiSession and manually calls to session_start() coexisting.');
        }
        self::$first = false;
        
        $this->name = $name;
        $this->data = null;
        
        # Einlesen der Session-Variablen (wird nur gemacht wenn die Session bereits läuft)
        $this->read();
        
    }
    
    /**
     * Einlesen der Session-Variablen, falls die Session läuft.
     * Die Session läuft, wenn das Session-Cookie vorhanden ist.
     */
    private function read() {
        # Session-Cookie vorhanden?
        $this->running = isset($_COOKIE[$this->name]);
        if ($this->running) {
            # Inhalt des Cookies ist die Session-Id
            $this->id = $_COOKIE[$this->name];
            
            # Session für PHP aktivieren
            \session_name($this->name);
            \session_id($this->id);
            \session_start();
            
            # Daten aus der superglobal $_SESSION holen und damit für später verfügbar halten
            $this->data = $_SESSION;
            
            # Session wieder schließen - nicht beenden.
            \session_write_close();
        }
    }
    
    /**
     * Session-Daten zurück schreiben
     * Das wird nur gemacht, wenn die Session bereits läuft.
     */
    public function write() {
        if ($this->running) {
            //$this->id = $_COOKIE[$this->name];
            
            # Session für PHP aktivieren
            \session_name($this->name);
            \session_id($this->id);
            \session_start();
            
            # Daten in die $_SESSION-superglobal schreiben
            $_SESSION = $this->data;
            
            # Session wieder schließen - nicht beenden.
            \session_write_close();
        }
    }
    
    /**
     * Die Session starten.
     * Cookie setzen.
     * 
     */
    public function start() {
        
        if ($this->isRunning()) {
            return;
        }
        
        //session_regenerate_id();
        
        \session_name($this->name);
        \session_set_cookie_params(0, '/');
        \session_start();
        \session_regenerate_id();
        $this->id = \session_id();
        //$_SESSION['info'] = 'Hallo '.$this->name;
        # sollte eigentlich leer sein
        $this->data = $_SESSION;
        
        \session_write_close();
        $this->running = true;
    }
    
    /**
     * Session beenden.
     * Cookie löschen.
     */
    public function end() {
        if ($this->running) {
            //$this->id = $_COOKIE[$this->name];
            \session_name($this->name);
            \session_id($this->id);
            \session_start();
            \session_unset();
            \session_destroy();
            \setcookie($this->name, "", time() - 3600, '/');//, \WEPPO\System::getHost());
            //\session_write_close();
            $this->running = false;
            $this->data = null;
        }
    }
    
    /**
     * Name der Session
     * @return string
     */
    public function getName() {
        return $this->name;
    }
    
    /**
     * Session-ID
     * @return string
     */
    public function getID() {
        if ($this->isRunning()) {
            return $this->id;
        }
        return '';
    }
    
    /**
     * Läuft die Session? (ist das Cookie vorhanden?)
     * @return bool
     */
    public function isRunning() {
        return $this->running;
    }
    
    
    
    /**
     * Setzt eine Variable der Session
     * Die Session wird bei jedem Aufruf zurückgeschrieben.
     * 
     * @param string $n Name der Variable
     * @param mixed $v Wert der Variable
     */
    public function set($n, $v) {
        if (!$this->running) return;
        $this->data[$n] = $v;
        $this->write();
    }
    
    /**
     * Abfrage einer Session-Variable
     * 
     * @param string $n Name der Variable
     * @return type
     */
    public function get($n) {
        if (!$this->running) return null;
        return isset($this->data[$n]) ? $this->data[$n] : null;
    }
    
    /**
     * Prüfen, ob eine Session-Variable vorhanden / gesetzt ist.
     * 
     * @param string $n Name der Variable
     * @return boolean
     */
    public function has($n) {
        if (!$this->running) return false;
        return isset($this->data[$n]);
    }
    
    
    public function dump() {
        echo $this->name, ': ', $this->running ? 'running '.'<a href="?!'.$this->name.'">end</a>' : 'NOT running '.'<a href="?'.$this->name.'">start</a>', ' ';
        _o($this->data);
    }
}

