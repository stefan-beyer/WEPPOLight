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
 * Errors will be thrown as ErrorException so that they can be catched.
 * Uncatched Exceptions will be handeled by this class.
 * It can show or email the information abeout the error.
 */
class ErrorHandler {
    
    protected $plus_code_lines = 4;
    protected $html = true;
    
    protected $email = '';
    protected $debug = true;
    protected $sendErrors = true;

    public function __construct(bool $debug = false, $email = null, $sendErrors = false) {
        $this->debug = $debug;
        $this->email = $email;
        $this->sendErrors = $sendErrors;
    }

    public function start() {
        //echo 'start error handling';
        # PHP-Fehlerbehandlung wird eingestellt.
        # in der konfiguration wird eingestellt, wie Fehler abgefangen werden sollen
        ini_set('display_errors', 'on');
        ini_set('display_startup_errors', 'on');
        ini_set('html_errors', 'on');
        ini_set('log_errors', 'on');
        error_reporting(-1);
        ini_set('error_log', './log/error.log');
        
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
    }

    public function handleException($ex) {
        if ($this->debug) {
            $this->html = true;
            echo $this->getExceptionText($ex, true);
        } else if ($this->sendErrors && $this->email) {
            $this->html = false;
            $subj = '[error] '. \WEPPOLight\Url::getHost();
            $msg = date('d.m.Y H:i')."\n".$this->getExceptionText($ex, false);
            mail($this->email, Controller::mailSubjectEncode($subj), $msg);
            
            echo '<html><head></head><body style="background-color:#7ffea0;">'
            . '<center style="font-family:sans-serif; font-size:200%; color:#333;">'
            . '<br/><br/>'
                    . '<h1>Sorry</h1>'
                    . '<p>Ein Fehler ist aufgetreten.<br/>Bitte versuchen Sie es<br/>zu einem späteren Zeitpunkt erneut.</p>'
                    . '<p style="color:#777;font-size:50%; font-style:italic;">Der Zuständige wurde bereits automatisch informiert.</p>'
            . '</center></body></html>';
            
        }
    }
    
    public function handleError($errno, $errstr = '', $errfile = '', $errline = '', $errcontext = null) {
        //echo $this->getErrorText($errno, $errstr, $errfile, $errline, $errcontext);
        throw new \ErrorException($errstr, $errno, $errno, $errfile, $errline, null);
    }
    
    
    protected function getErrorText($errno, $errstr, $errfile, $errline, $errcontext) {
        
        $errorType = array(
            E_ERROR => 'ERROR',
            E_WARNING => 'WARNING',
            E_PARSE => 'PARSING ERROR',
            E_NOTICE => 'NOTICE',
            E_CORE_ERROR => 'CORE ERROR',
            E_CORE_WARNING => 'CORE WARNING',
            E_COMPILE_ERROR => 'COMPILE ERROR',
            E_COMPILE_WARNING => 'COMPILE WARNING',
            E_USER_ERROR => 'USER ERROR',
            E_USER_WARNING => 'USER WARNING',
            E_USER_NOTICE => 'USER NOTICE',
            E_STRICT => 'STRICT NOTICE',
            E_RECOVERABLE_ERROR => 'RECOVERABLE ERROR'
        );
        
        $s = '';
        
        $s .= $this->getIntroLine(
                (isset($errorType[$errno]) ? $errorType[$errno] : 'Unknown Error'),
                $errstr,
                $errfile,
                $errline
        );
        
        // array_reverse
        $trace = (debug_backtrace());
        $s .= $this->getTraceText($trace);

        if ($this->html) {
            $s .= $this->getJS();
        }
        
        return $s;
    }
    
    private function h($s, $c, $e, $nl = true, $h = true) {
        if (!$this->html) {
            if ($nl) {
                return $c . "\n";
            }
            return $c;
        }
        if ($h) {
            $c = htmlentities($c);
        }
        return $s.$c.$e;
    }
    
    protected function getIntroLine($h1, $h2, $file, $line) {
        $s = '';
        $s .= $this->h('<h1>', $h1, '</h1>', true, true);
        $s .= $this->h('<h2>', '»'.$h2.'«', '</h2>', true, true);
        
        
        $s .= $this->getLineText($file, $line);
        
        return $s;
    }
    
    protected function getLineText($file, $line, $class=null, $type=null, $function=null, $args=null) {
        $s = $this->h(
                '<div style="margin-top:10px; cursor:pointer;" class="trace_step">',
                $this->h(
                        '<code style="color:blue;">',
                        $file,
                        '</code>',
                        false, true
                        ) . ' (' . 
                $this->h(
                        '<code style="color:brown;">',
                        $line,
                        '</code>',
                        false, true
                        ) . '):',
                '</div>',
                true, false
                );
         
        if ($class) {
            $s .= $this->h(
                    '<code style="color:green;">',
                    $class . $type . $this->h('<strong>', $function, '</strong>', false, false) . '('. implode(', ', $args). ')',
                    '</code>',
                    true, false
                    );
        }
        
        
        if ($this->html) {
            $lines = $this->_get_source_lines($file, $line, $this->plus_code_lines, $this->html);
            $s .= $this->h(
                    '<div class="trace_code" style="font-family:mono;font-size:70%; padding:10px;background-color:#eee;display:none;">',
                    $this->h(
                            '<small>',
                            'Code-Context',
                            '<br/></small>',
                            true, true
                            ).implode('<br/>', $lines),
                    '</div>',
                    true, false
                    );
        }
        return $s;
    }
    
    
    protected function getTraceText(&$trace) {
        $s = $this->h('<h3>', 'Trace', '</h3>', true, true);
        
        $str_trace = '';
        
        foreach ($trace as &$tr) {
            
            if (isset($tr['file']) && isset($tr['line']) && isset($tr['args'])) {
                
                $args = $tr['args'];
                $strargs = [];
                foreach ($args as &$arg) {
                    $a = gettype($arg);
                    if ($a === 'object') {
                        $a = get_class($arg);
                    } else if ($a === 'array') {
                        $a .= '(' . count($arg) . ')';
                    }
                    $strargs[] = $this->h('<em>', $a, '</em>', false, true);
                }
            
                $str_trace .= $this->h(
                        '<li style="margin-top:10px; cursor:pointer;" class="">',
                        $this->getLineText($tr['file'], $tr['line'], (isset($tr['class']) ? $tr['class'] : ''), (isset($tr['type']) ? $tr['type'] : ''), $tr['function'], $strargs),
                        '</li>',
                        true, false
                        );
            }
        }
        
        $s .= $this->h('<ol>', $str_trace, '</ol>', true, false);
        
        return $s;
    }

    


    /**
     * Helper for pretty printing an exception.
     * 
     * @param Exception $e
     * @param bool $js
     */
    protected function getExceptionText(\Throwable $e, $js = true) {
        $s = '';
        
        $s .= $this->getIntroLine(
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
        );
        
        $trace = $e->getTrace();
        $s .= $this->getTraceText($trace);
        
        if ($e->getPrevious()) {
            $s .= $this->getExceptionText($e->getPrevious(), false);
        }
        
        if ($this->html) {
            if ($js) {
                $s .= $this->getJS();
            }
        }
        
        return $s;
    }
    
    protected function getJS() {
        return '<script>'
        . 'var trs = document.getElementsByClassName("trace_step");'
        . 'for (var i=0; i < trs.length; i++) {'
        . ' (function(){'
        . '  var elem = trs.item(i);'
        . '  elem.onclick = function(e) {'
                . 'var nxt = elem;'
                . 'while(nxt = nxt.nextSibling) {'
                . ' '
        . '         if (nxt.className == "trace_code") {'
        . '         nxt.style.display= nxt.style.display=="none" ? "block" : "none";'
        . '         break;'
        . '       }'
                . '}'
        . '  }'
        . ' })();'
        . '}'
        . '</script>';
    }
    
    /**
     * Get some lines of a source file.
     * 
     * @param string $sfile The sourcefile you want
     * @param int $line The line you want
     * @param int $plus Amount of lines to get before and after the line
     * @param bool $html Create HTML Code
     * @return array
     */
    protected function _get_source_lines(string $sfile, int $line, int $plus, bool $html = true): array {
        $lines = file($sfile);
        $from = $line - 1 - $plus;
        $to = $line - 1 + 2;
        $ll = [];
        $c = count($lines);
        for ($i = $from; $i <= $to; $i++) {
            if ($i < 0 || $i >= $c)
                continue;
            if ($i + 1 == $line) {
                $color = 'color:red;';
            } else {
                $color = '';
            }
            $l = htmlspecialchars($lines[$i]);
            $l = str_replace(' ', '&nbsp;', $l);
            if ($html) {
                $ll[] = '<span style="' . $color . '"><strong>' . ($i + 1) . '</strong> ' . $l . '</span>';
            } else {
                $ll[] = $l;
            }
        }
        return $ll;
    }
    
    
    
    
}