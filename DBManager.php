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
 * Init of the DB and Version Control.
 */
class DBManager {
    
    private $updateCallback = null;
    private $dbversionFilename;
    
    private $error = null;
    
    /**
     * You privide a callback, that gets called if a db update is needed.
     * 
     * @param Callable $updateCallback
     */
    public function __construct(Callable $updateCallback) {
        $this->updateCallback = $updateCallback;
        $this->dbversionFilename = APP_ROOT.'.dbversion';
    }
    
    public function init($host = NULL, $username = NULL, $password = NULL, $db = NULL, $port = NULL, $prefix = '') {
        TableRecord::initDB($host, $username, $password, $db, $port);
        TableRecord::setPrefix($prefix);
        
        $this->update($this->getVersion(), APP_DB_VERSION);
    }

    public function getError() {
        return $this->error;
    }

    protected function update(int $old, int $new) {
        if ($new === $old) {
            return;
        }
        if (!$this->updateCallback) {
            return;
        }
        
        for ($_new = $old+1; $_new <= $new; $_new++) {
            $result = $this->updateCallback->call($this, $_new);
            if ($result) {
                $this->setVersion($_new);
            } else {
                throw new \Exception('DB Update '.($_new-1).' → '.$_new.' failed: '.$this->getError());
            }
        }
    }
    
    protected function getVersion(): int {
        if (!is_readable($this->dbversionFilename)) {
            return 0;
        }
        return intval(file_get_contents($this->dbversionFilename));
    }
    
    protected function setVersion(int $v) {
        if (!file_exists($this->dbversionFilename)) {
            if (!is_writable(dirname($this->dbversionFilename))) {
                throw new \Exception($this->dbversionFilename . ' must be writable.');
            }
        } else if (!is_writable($this->dbversionFilename)) {
            throw new \Exception($this->dbversionFilename . ' must be writable.');
        }
        file_put_contents($this->dbversionFilename, ''.$v);
    }
    
    
    public function createTabele(string $tableName, array $cols, $extra = 'ENGINE=InnoDB DEFAULT CHARSET=utf8') {
        $pfxTableName = TableRecord::getPrefix() . $tableName;
        
 #       //SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

        $sql = 'CREATE TABLE IF NOT EXISTS `'.$pfxTableName . "` (\n".implode(",\n",$cols)."\n) ".$extra;
        //$sql = 'SET foreign_key_checks = 0;'."\n" . $sql . ';'."\n";
        try {
            $res = TableRecord::rawQuery($sql);
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
        
        return true;
    }
    
    public function query($sql) {
        $type = str_replace('%%', TableRecord::getPrefix(), $type);
        try {
            $res = TableRecord::rawQuery($sql);
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $res = false;
        }
        
        return $res;
    }
    
    /**
     * Beispiele:
            WEPPOLight\DBManager::col('id', 'int', 10, 'unsigned NOT NULL AUTO_INCREMENT'),
            WEPPOLight\DBManager::col('name', 'varchar', 200, 'NOT NULL', ''),
            WEPPOLight\DBManager::col('passhash', 'varchar', 255, 'NULL', 'NULL'),
            WEPPOLight\DBManager::col('PRIMARY KEY', 'id'),
            WEPPOLight\DBManager::col('KEY', ['name'=>'bill_id', 'col'=>'bill_id']),
            WEPPOLight\DBManager::col('CONSTRAINT', ['col'=>'bill_id', 'othertable'=>'%bill', 'othercol'=>'id', 'options'=>'ON DELETE SET NULL ON UPDATE NO ACTION']),
     * 
     * @param string $name
     * @param type $type
     * @param type $size
     * @param type $options
     * @param string $default
     * @return type
     */
    static public function col(string $name, $type, $size=null, $options=null, $default=null) {
        
        $coller = function($c){return '`'.$c.'`';};
        
        if ($size === null && $options === null) {
            switch ($name) {
                case 'PRIMARY KEY':
                case 'KEY':
                case 'UNIQUE KEY':
                    if (is_array($type)) {
                        if (isset($type['name'])) {
                            if (is_array($type['col'])) {
                                $type['col'] = implode(',', array_map($coller, $type['col']));
                            } else {
                                $type['col'] = $coller($type['col']);
                            }
                            $type = $coller($type['name']).' ('.$type['col'].')';
                        } else {
                            $type = '('.implode(',', array_map($coller, $type)).')';
                        }
                    } else {
                        $type = '(`'.$type.'`)';
                    }
                    break;
                case 'CONSTRAINT':
                    
                    $col = $type['col'];
                    if (!is_array($col)) {
                        $col= [$col];
                    }
                    $col = implode(',', array_map($coller, $col));
                    
                    $othercol = $type['othercol'];
                    if (!is_array($othercol)) {
                        $othercol = [$othercol];
                    }
                    $othercol = implode(',', array_map($coller, $othercol));
                    
                    $othertable = $coller($type['othertable']);
                    
                    $options = isset($type['options']) ? $type['options'] : '';
                    if ($options) $options = ' '.$options;
                    
                    $type = 'FOREIGN KEY ('.$col.') REFERENCES '.$othertable.' ('.$othercol.')'.$options;
                    break;
            }
            $type = str_replace('%%', TableRecord::getPrefix(), $type);
            return $name.' '.$type;
        }
        
        # Normale Spalten definition
        if (is_array($size)) {
            $size = '('.implode(',', $size).')';
        } else if (!is_null($size)) {
            $size = '('.$size.')';
        } else {
            $size = '';
        }
        
        if (is_null($options)) {
            $options = '';
        } else {
            $options = ' '.$options;
        }
        
        if (is_null($default)) {
            $default = '';
        } else {
            $default = ' DEFAULT \''.$default.'\'';
        }
        
        return '`'.$name . '` ' . $type.$size . $options . $default;
    }
}