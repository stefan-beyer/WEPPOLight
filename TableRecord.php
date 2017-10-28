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
 * Base class for database operations and database to object transformations.
 * 
 * @TODO more documentation!
 */
class TableRecord extends \stdclass {

    public function __construct($id = 0, $cols = '*') {
        $this->id = intval($id);

        if ($id > 0 && $cols !== null) {
            if (!$this->load($cols)) {
                throw new TableRecordException('Datensatz ' . $id . ' in ' . static::getTablename() . ' nicht gefunden.');
            }
        }
    }

    static function isConnected() {
        return isset(static::$_mysqli) && !!static::$_mysqli;
    }

    public function isNew() {
        return $this->id == 0;
    }

    public function isLoaded() {
        return $this->id > 0;
    }

    public function assign(array $data) {
        foreach ($data as $k => $v) {
            $this->{$k} = $v;
        }
    }

    public function load($cols = '*') {
        if ($this->id <= 0)
            return false;

        static::createObjects(false);
        static::where(static::getTablename() . '1.id', intval($this->id));
        if (is_array($cols)) {
            $cols = implode(',', $cols);
        }
        $d = static::getOne($cols);
        if ($d) {
            $this->assign($d);
            return true;
        }
        return false;
    }

    public function save($cols = '*') {
        if (is_string($cols)) {
            if ($cols === '*') {
                $cols = static::getFields(false);
            } else {
                $cols = explode(',', $cols);
            }
        }
        if (count($cols) === 0)
            return true;



        $saveData = array();
        foreach ($cols as $c) {
            if (property_exists($this, $c)) {
                $saveData[$c] = $this->{$c};
            }
        }

        if ($this->id > 0) {
            if (isset($saveData['id']))
                unset($saveData['id']);
            static::where('id', $this->id);
            return static::update($saveData);
        } else {
            $ret = static::insert($saveData);
            if ($ret !== false) {
                $this->id = $ret;
                return true;
            }
        }
        return false;
    }

    static public function getList($cols = '*') {
        if (count(static::$_orderBy) === 0) {
            static::orderBy('id', 'ASC');
        }
        return static::get(null, $cols);
    }

    public function loadEmpty() {
        $fields = static::getFields(false);

        //_o($fields);

        $data = array();
        if ($fields && is_array($fields)) {
            foreach ($fields as $k/* =>$info */) {
                // $info['Type']
                // int(10) unsigned  varchar(200) text
                // TODO default werde nach spalten typ wählen
                $data[$k] = '';
            }
        }

        $this->assign($data);

        $this->id = 0;
    }

    ####################################################################
    # Query-Laguage

    static protected $_createObjects = true;

    /**
     * Table prefix 
     * MOD (Stefan Beyer) verschiedene DB-Verbindungen in einer Aplikation ermöglichen: static weg)
     * @var string
     */
    static protected $_prefix;

    /**
     * MySQLi instance
     *
     * @var mysqli
     */
    static protected $_mysqli;

    /**
     * The SQL query to be prepared and executed
     *
     * @var string
     */
    static protected $_query;

    /**
     * The previously executed SQL query
     *
     * @var string
     */
    static protected $_lastQuery;

    /**
     * An array that holds where joins
     *
     * @var array
     */
    static protected $_join = array();

    /**
     * An array that holds where conditions 'fieldname' => 'value'
     *
     * @var array
     */
    static protected $_where = array();

    /**
     * An array that holds where conditions 'fieldname' => 'value'
     *
     * @var array
     */
    static protected $_having = array();

    /**
     * Dynamic type list for order by condition value
     */
    static protected $_orderBy = array();

    /**
     * Dynamic type list for group by condition value
     */
    static protected $_groupBy = array();

    /**
     * Dynamic array that holds a combination of where condition/table data value types and parameter referances
     *
     * @var array
     */
    static protected $_bindParams = array(''); // Create the empty 0 index
    /**
     * Variable which holds an amount of returned rows during get/getOne/select queries
     *
     * @var string
     */
    static public $_count = 0;

    /**
     * Variable which holds last statement error
     *
     * @var string
     */
    static protected $_stmtError;

    /**
     * Database credentials
     *
     * @var string
     */
    static protected $_host;

    /**
     * @var string SQL Benutzername
     */
    static protected $_username;

    /**
     * @var string SQL Passwort
     */
    static protected $_password;

    /**
     * @var string Datenbankname
     */
    static protected $_db;

    /**
     * @var integer DB-Server-Port
     */
    static protected $_port;
    static $isSubQuery = false;

    /**
     * Erstellt eine SQLIDB-Instanz
     * 
     * @param string $host
     * @param string $username
     * @param string $password
     * @param string $db
     * @param int $port
     */
    //static $shutdown_function_registered = false;
    static function initDB($host = NULL, $username = NULL, $password = NULL, $db = NULL, $port = NULL) {
        if (static::$_mysqli)
            return;


        static::$_host = $host;
        static::$_username = $username;
        static::$_password = $password;
        static::$_db = $db;
        if ($port == NULL)
            static::$_port = \ini_get('mysqli.default_port');
        else
            static::$_port = $port;


        static::connect();
        static::setPrefix();

        //if (!static::$shutdown_function_registered) {
        \register_shutdown_function(array(static::getStaticClass(), "closeDB"));
        //static::$shutdown_function_registered = true;
        //}
    }

    /**
     * Close connection
     */
    static public function closeDB() {
        if (static::$_mysqli)
            static::$_mysqli->close();
    }

    /**
     * A method to connect to the database
     *
     */
    static public function connect() {

        static::$_mysqli = new \mysqli(static::$_host, static::$_username, static::$_password, static::$_db, static::$_port)
                or die('There was a problem connecting to the database');

        static::$_mysqli->set_charset('utf8');
    }

    static public function getFields($fullInfo = false) {
        static::createObjects(false);
        $sql = 'SHOW COLUMNS FROM ' . static::getPrefix() . static::getTablename();
        $ret = static::rawQuery($sql);
        $result = array();
        if ($ret && is_array($ret)) {
            foreach ($ret as $f) {
                if ($fullInfo) {
                    $fn = $f['Field'];
                    unset($f['Field']);
                    $result[$fn] = $f;
                } else {
                    $result[] = $f['Field'];
                }
            }
            return $result;
        }
        return false;
    }

    static public function parseFieldType(&$field) {
        $t = $field['Type'];
        //$m = array();
        // http://dev.mysql.com/doc/refman/5.7/en/create-table.html
        if (!preg_match('/^([a-zA-Z]+)(\((.*)\))?(.*)$/', $t, $m))
            return;
        $field['type'] = trim(strtolower($m[1]));
        $field['size'] = isset($m[3]) ? trim($m[3]) : '';
        $field['rest'] = isset($m[4]) ? trim($m[4]) : '';
        switch ($field['type']) {
            case 'enum':
            case 'set':
                preg_match_all('/\'([^\']*)\'/', $field['size'], $m);
                $field['possibleValues'] = isset($m[1]) ? $m[1] : array();
                unset($field['size']);
                break;
            default:
                $field['size'] = intval($field['size']);
        }
    }

    /**
     * Method to set a prefix
     * MOD (Stefan Beyer) no static
     * @param string $prefix     Contains a tableprefix
     */
    static public function setPrefix($prefix = '') {
        static::$_prefix = $prefix;
    }

    /**
     * Method to get the current prefix
     * MOD (Stefan Beyer) no static
     * @return string    Contains the tableprefix
     */
    static public function getPrefix() {
        return static::$_prefix;
    }

    /**
     * Reset states after an execution
     *
     * @return object Returns the current instance.
     */
    static protected function reset() {
        static::$_where = array();
        static::$_having = array();
        static::$_join = array();
        static::$_orderBy = array();
        static::$_groupBy = array();
        static::$_bindParams = array(''); // Create the empty 0 index
        static::$_query = null;
        static::$_count = 0;
    }

    static public function createObjects($co = null) {
        if ($co !== null)
            static::$_createObjects = $co;
        else
            return static::$_createObjects;
    }

    /**
     * Pass in a raw query and an array containing the parameters to bind to the prepaird statement.
     *
     * @param string $query      Contains a user-provided query.
     * @param array  $bindParams All variables to bind to the SQL statment.
     * @param bool   $sanitize   If query should be filtered before execution
     *
     * @return array Contains the returned rows from the query.
     */
    static public function rawQuery($query, $bindParams = null, $sanitize = true) {
        static::$_query = $query;
        if ($sanitize)
            static::$_query = \filter_var($query, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
        $stmt = static::_prepareQuery();

        if (\is_array($bindParams) === true) {
            $params = array(''); // Create the empty 0 index
            foreach ($bindParams as $prop => $val) {
                $params[0] .= static::_determineType($val);
                \array_push($params, $bindParams[$prop]);
            }

            \call_user_func_array(array($stmt, 'bind_param'), static::refValues($params));
        }

        $stmt->execute();
        static::$_stmtError = $stmt->error;
        static::reset();

        return static::_dynamicBindResults($stmt);
    }

    /**
     * Raw-SQL-Query ausführen 
     * 
     * @param string $query   Contains a user-provided select query.
     * @param int    $numRows The number of rows total to return.
     *
     * @return array Contains the returned rows from the query.
     */
    static public function query($query, $numRows = null) {
        static::$_query = \filter_var($query, FILTER_SANITIZE_STRING);
        $stmt = static::_buildQuery($numRows);
        $stmt->execute();
        static::$_stmtError = $stmt->error;
        static::reset();

        return static::_dynamicBindResults($stmt);
    }

    /**
     * A convenient SELECT * function.
     *
     * @param integer $numRows   The number of rows total to return.
     * @param string|array $columns   Welche Spalten?
     *
     * @return array Contains the returned rows from the select query.
     */
    static public function get($numRows = null, $columns = '*') {
        if (empty($columns))
            $columns = '*';

        $tableName = static::getTablename();

        $column = \is_array($columns) ? \implode(', ', $columns) : $columns;
        static::$_query = "SELECT $column FROM " . static::$_prefix . $tableName . ' ' . $tableName . '1';
        $stmt = static::_buildQuery($numRows);

        if (static::$isSubQuery)
            return; // wenn in subquery mode: nicht ausführen

        $stmt->execute();
        static::$_stmtError = $stmt->error;
        static::reset();

        return static::_dynamicBindResults($stmt);
    }

    public static function getWhere($whereProp, $whereValue = null, $operator = null) {
        static::where($whereProp, $whereValue, $operator);
        return static::get();
    }

    /**
     * A convenient SELECT * function to get one record.
     *
     * @param string|array $columns   Welche Spalten?
     *
     * @return array Contains the returned rows from the select query.
     */
    static public function getOne($columns = '*') {
        $res = static::get(1, $columns);

        if (\is_object($res))
            return $res;

        if (isset($res[0]))
            return $res[0];

        return null;
    }

    static function prepareData(&$data) {
        #foreach ($data as $k => &$v) {
        #}
    }

    /**
     * SQL INSERT
     *
     * @param array $insertData Data containing information for inserting into the DB.
     *
     * @return boolean Boolean indicating whether the insert query was completed succesfully.
     */
    static public function insert($insertData) {
        if (static::$isSubQuery)
            return;

        $tableName = static::getTablename();
        static::prepareData($insertData);


        static::$_query = "INSERT into " . static::$_prefix . $tableName;
        $stmt = static::_buildQuery(null, $insertData);

        $stmt->execute();
        static::$_stmtError = $stmt->error;
        static::reset();

        return ($stmt->affected_rows > 0 ? $stmt->insert_id : false);
    }

    /**
     * Update query. Be sure to first call the "where" method.
     *
     * @param array  $tableData Array of data to update the desired row.
     *
     * @return boolean
     */
    static public function update($tableData) {
        if (static::$isSubQuery)
            return;

        $tableName = static::getTablename();
        static::prepareData($tableData);

        static::$_query = "UPDATE " . static::$_prefix . $tableName . " SET ";

        $stmt = static::_buildQuery(null, $tableData);
        $status = $stmt->execute();
        static::reset();
        static::$_stmtError = $stmt->error;
        static::$_count = $stmt->affected_rows;

        return $status;
    }

    /**
     * Delete query. Call the "where" method first.
     *
     * @param integer $numRows   The number of rows to delete.
     *
     * @return boolean Indicates success. 0 or 1.
     */
    static public function delete($numRows = null) {
        if (count(self::$_where) == 0) {
            trigger_error('::where() aufrufen, bevor ::delete() aufgerufen wird. Wurde delete() evtl. über Objekt aufgerufen? hierzu kann ->remove() verwendet werden.', E_USER_ERROR);
            return false;
        }

        if (static::$isSubQuery)
            return;

        $tableName = static::getTablename();

        static::$_query = "DELETE FROM " . static::$_prefix . $tableName;

        $stmt = static::_buildQuery($numRows);
        $stmt->execute();
        static::$_stmtError = $stmt->error;
        static::reset();

        return ($stmt->affected_rows > 0);
    }

    // where vorher setzen
    public function remove() {
        if (!$this->id)
            return false;
        static::where('id', $this->id);
        return static::delete();
    }

    /*
      // to prevent non static call to _delete()
      public static function __callStatic($name, $arguments) {
      //echo '__callStatic: ', $name;
      if ($name === 'delete') {
      //return call_user_func_array(array(__CLASS__, '_delete'), $arguments);
      return static::_delete();
      }
      }
     */

    /**
     * This method allows you to specify multiple AND WHERE statements for SQL queries.
     *
     * $MySqliDb->where('id', 7)->where('title', 'MyTitle');
     *
     * @param string $whereProp  The name of the database field.
     * @param mixed  $whereValue The value of the database field.
     * @param $operator
     *
     * @return MysqliDb
     */
    static public function where($whereProp, $whereValue = null, $operator = null) {
        if ($operator)
            $whereValue = Array($operator => $whereValue);

        static::$_where[] = Array("AND", $whereValue, $whereProp);
    }

    /**
     * This method allows you to specify multiple (method chaining optional) AND HAVING statements for SQL queries.
     *
     * $MySqliDb->having('id', 7)->having('title', 'MyTitle');
     * 
     * @author Stefan Beyer
     *
     * @param string $whereProp  The name of the database field.
     * @param mixed  $whereValue The value of the database field.
     * @param $operator
     *
     * @return MysqliDb
     */
    static public function having($whereProp, $whereValue = null, $operator = null) {
        if ($operator)
            $whereValue = Array($operator => $whereValue);

        static::$_having[] = Array("AND", $whereValue, $whereProp);
    }

    /**
     * This method allows you to specify multiple (method chaining optional) OR WHERE statements for SQL queries.
     *
     * @uses $MySqliDb->orWhere('id', 7)->orWhere('title', 'MyTitle');
     *
     * @param string $whereProp  The name of the database field.
     * @param mixed  $whereValue The value of the database field.
     * @param $operator
     *
     * @return MysqliDb
     */
    static public function orWhere($whereProp, $whereValue = null, $operator = null) {
        if ($operator)
            $whereValue = Array($operator => $whereValue);

        static::$_where[] = Array("OR", $whereValue, $whereProp);
    }

    /**
     * This method allows you to specify multiple (method chaining optional) OR HAVING statements for SQL queries.
     *
     * $MySqliDb->orHaving('id', 7)->orHaving('title', 'MyTitle');
     * 
     * @author Stefan Beyer
     *
     * @param string $whereProp  The name of the database field.
     * @param mixed  $whereValue The value of the database field.
     * @param $operator
     *
     * @return MysqliDb
     */
    static public function orHaving($whereProp, $whereValue = null, $operator = null) {
        if ($operator)
            $whereValue = Array($operator => $whereValue);

        static::$_having[] = Array("OR", $whereValue, $whereProp);
    }

    /**
     * This method allows you to concatenate joins for the final SQL statement.
     *
     * $MySqliDb->join('table1', 'field1 <> field2', 'LEFT')
     *
     * @param string $joinTable The name of the table.
     * @param string $joinCondition the condition.
     * @param string $joinType 'LEFT', 'INNER' etc.
     *
     * @return MysqliDb
     */
    static public function join($joinTable, $joinCondition, $joinType = '') {
        $allowedTypes = array('LEFT', 'RIGHT', 'OUTER', 'INNER', 'LEFT OUTER', 'RIGHT OUTER');
        $joinType = \strtoupper(trim($joinType));
        $joinTable = \filter_var($joinTable, FILTER_SANITIZE_STRING);

        if ($joinType && !in_array($joinType, $allowedTypes))
            die('Wrong JOIN type: ' . $joinType);

        static::$_join[$joinType . " JOIN " . static::$_prefix . $joinTable] = $joinCondition;
    }

    /**
     * This method allows you to specify multiple (method chaining optional) ORDER BY statements for SQL queries.
     *
     * $MySqliDb->orderBy('id', 'desc')->orderBy('name', 'desc');
     *
     * @param string $orderByField The name of the database field.
     * @param string $orderByDirection Order direction.
     *
     * @return MysqliDb
     */
    static public function orderBy($orderByField, $orderbyDirection = "DESC") {
        $allowedDirection = Array("ASC", "DESC");
        $orderbyDirection = \strtoupper(trim($orderbyDirection));
        $orderByField = \preg_replace("/[^-a-z0-9\.\(\),_]+/i", '', $orderByField);

        if (empty($orderbyDirection) || !\in_array($orderbyDirection, $allowedDirection))
            die('Wrong order direction: ' . $orderbyDirection);

        static::$_orderBy[$orderByField] = $orderbyDirection;
    }

    /**
     * This method allows you to specify multiple (method chaining optional) GROUP BY statements for SQL queries.
     *
     * $MySqliDb->groupBy('name');
     *
     * @param string $groupByField The name of the database field.
     *
     * @return MysqliDb
     */
    static public function groupBy($groupByField) {
        $groupByField = \preg_replace("/[^-a-z0-9\.\(\),_]+/i", '', $groupByField);

        static::$_groupBy[] = $groupByField;
    }

    /**
     * This methods returns the ID of the last inserted item
     *
     * @return integer The last inserted item ID.
     */
    static public function getInsertId() {
        return static::$_mysqli->insert_id;
    }

    /**
     * Escape harmful characters which might affect a query.
     *
     * @param string $str The string to escape.
     *
     * @return string The escaped string.
     */
    static public function escape($str) {
        return static::$_mysqli->real_escape_string($str);
    }

    /**
     * Method to call mysqli->ping() to keep unused connections open on
     * long-running scripts, or to reconnect timed out connections (if php.ini has
     * global mysqli.reconnect set to true). Can't do this directly using object
     * since _mysqli is protected.
     *
     * @return bool True if connection is up
     */
    static public function ping() {
        return static::$_mysqli->ping();
    }

    /**
     * This method is needed for prepared statements. They require
     * the data type of the field to be bound with "i" s", etc.
     * This function takes the input, determines what type it is,
     * and then updates the param_type.
     *
     * @param mixed $item Input to determine the type.
     *
     * @return string The joined parameter types.
     */
    static protected function _determineType($item) {
        switch (\gettype($item)) {
            case 'NULL':
            case 'string':
                return 's';
                break;

            case 'boolean':
            case 'integer':
                return 'i';
                break;

            case 'blob':
                return 'b';
                break;

            case 'double':
                return 'd';
                break;
        }
        return '';
    }

    /**
     * Helper function to add variables into bind parameters array
     *
     * @param string Variable value
     */
    static protected function _bindParam($value) {
        static::$_bindParams[0] .= static::_determineType($value);
        \array_push(static::$_bindParams, $value);
    }

    /**
     * Helper function to add variables into bind parameters array in bulk
     *
     * @param Array Variable with values
     */
    static protected function _bindParams($values) {
        foreach ($values as $value)
            static::_bindParam($value);
    }

    /**
     * Helper function to add variables into bind parameters array and will return
     * its SQL part of the query according to operator in ' $operator ?' or
     * ' $operator ($subquery) ' formats
     *
     * @param Array Variable with values
     * @param $value
     */
    static protected function _buildPair($operator, $value) {
        if (!\is_object($value)) {
            static::_bindParam($value);
            return ' ' . $operator . ' ? ';
        }

        // Wir gehen hier davon aus, dass value ein subquery objekt ist
        static::_bindParams($value->params);

        return " " . $operator . " (" . $value->query . ")";
    }

    /**
     * Abstraction method that will compile the WHERE statement,
     * any passed update data, and the desired rows.
     * It then builds the SQL query.
     *
     * @param int   $numRows   The number of rows total to return.
     * @param array $tableData Should contain an array of data for updating the database.
     *
     * @return mysqli_stmt Returns the $stmt object.
     */
    static protected function _buildQuery($numRows = null, $tableData = null) {
        static::_buildJoin();
        static::_buildTableData($tableData);
        static::_buildWhere();
        static::_buildHaving();
        static::_buildGroupBy();
        static::_buildOrderBy();
        static::_buildLimit($numRows);

        static::$_lastQuery = static::replacePlaceHolders(static::$_query, static::$_bindParams);

        if (static::$isSubQuery)
            return; // für subqueries nicht weiter machen

            
        // Prepare query
        $stmt = static::_prepareQuery();

        // Bind parameters to statement if any
        if (\count(static::$_bindParams) > 1)
            \call_user_func_array(array($stmt, 'bind_param'), static::refValues(static::$_bindParams));

        return $stmt;
    }

    /**
     * This helper method takes care of prepared statements' "bind_result method
     * , when the number of variables to pass is unknown.
     *
     * @param mysqli_stmt $stmt Equal to the prepared statement object.
     *
     * @return array The results of the SQL fetch.
     */
    static protected function _dynamicBindResults(\mysqli_stmt $stmt, $createObjects = true) {
        $parameters = array();
        $results = array();

        $meta = $stmt->result_metadata();

        // if $meta is false yet sqlstate is true, there's no sql error but the query is
        // most likely an update/insert/delete which doesn't produce any results
        if (!$meta && $stmt->sqlstate) {
            return array();
        }
        // das hier bereitet ein result array vor in das bei statmt->fetch die werte geschrieben werden
        $row = array();
        while ($field = $meta->fetch_field()) {
            $row[$field->name] = null;
            $parameters[] = & $row[$field->name];
        }


        // avoid out of memory bug in php 5.2 and 5.3
        // https://github.com/joshcam/PHP-MySQLi-Database-Class/pull/119
        if (\version_compare(\phpversion(), '5.4', '<'))
            $stmt->store_result();

        \call_user_func_array(array($stmt, 'bind_result'), $parameters);



        if (static::createObjects()) {
            $classname = static::getStaticClass();
            while ($stmt->fetch()) {
                $x = new $classname();
                $x->assign($row);
                static::$_count += count($row);
                \array_push($results, $x);
            }

            /*if (static::$doResolve) {
                foreach ($results as &$r) {
                    $r->resolveForeinFields();
                }
            }*/
        } else {
            while ($stmt->fetch()) {
                $x = array();
                foreach ($row as $key => $val) {
                    $x[$key] = $val;
                }
                static::$_count++;
                \array_push($results, $x);
            }
        }

        static::createObjects(true);

        return $results;
    }

    /**
     * Abstraction method that will build an JOIN part of the query
     */
    static protected function _buildJoin() {
        if (empty(static::$_join))
            return;

        foreach (static::$_join as $prop => $value)
            static::$_query .= " " . $prop . " ON " . $value;
    }

    /**
     * Abstraction method that will build an INSERT or UPDATE part of the query
     * 
     * @param $tableData
     */
    static protected function _buildTableData($tableData) {
        if (!\is_array($tableData))
            return;

        $isInsert = \strpos(static::$_query, 'INSERT');
        $isUpdate = \strpos(static::$_query, 'UPDATE');

        if ($isInsert !== false) {
            static::$_query .= '(`' . \implode(\array_keys($tableData), '`, `') . '`)';
            static::$_query .= ' VALUES(';
        }

        foreach ($tableData as $column => $value) {
            if ($isUpdate !== false)
                static::$_query .= "`" . $column . "` = ";

            // Subquery value
            if (\is_object($value)) {
                static::$_query .= static::_buildPair("", $value) . ", ";
                continue;
            }

            // Simple value
            if (!\is_array($value)) {
                static::_bindParam($value);
                static::$_query .= '?, ';
                continue;
            }

            // Function value
            $key = key($value);
            $val = $value[$key];
            switch ($key) {
                case '[I]':
                    static::$_query .= $column . $val . ", ";
                    break;
                case '[F]':
                    static::$_query .= $val[0] . ", ";
                    if (!empty($val[1]))
                        static::_bindParams($val[1]);
                    break;
                case '[N]':
                    if ($val == null)
                        static::$_query .= "!" . $column . ", ";
                    else
                        static::$_query .= "!" . $val . ", ";
                    break;
                default:
                    die("Wrong operation");
            }
        }
        static::$_query = \rtrim(static::$_query, ', ');
        if ($isInsert !== false)
            static::$_query .= ')';
    }

    /**
     * Abstraction method that will build the part of the WHERE conditions
     */
    static protected function _buildWhere() {
        if (empty(static::$_where))
            return;

        //Prepair the where portion of the query
        static::$_query .= ' WHERE ';

        // Remove first AND/OR concatenator
        static::$_where[0][0] = '';
        foreach (static::$_where as $cond) {
            list ($concat, $wValue, $wKey) = $cond;

            static::$_query .= " " . $concat . " " . $wKey;

            // Empty value (raw where condition in wKey)
            if ($wValue === null)
                continue;

            // Simple = comparison
            if (!\is_array($wValue))
                $wValue = Array('=' => $wValue);

            $key = key($wValue);
            $val = $wValue[$key];
            switch (\strtolower($key)) {
                case '0':
                    static::_bindParams($wValue);
                    break;
                case 'not in':
                case 'in':
                    $comparison = ' ' . $key . ' (';
                    if (\is_object($val)) {
                        $comparison .= static::_buildPair("", $val);
                    } else {
                        foreach ($val as $v) {
                            $comparison .= ' ?,';
                            static::_bindParam($v);
                        }
                    }
                    static::$_query .= \rtrim($comparison, ',') . ' ) ';
                    break;
                case 'not between':
                case 'between':
                    static::$_query .= " $key ? AND ? ";
                    static::_bindParams($val);
                    break;
                case 'not exists':
                case 'exists':
                    static::$_query .= $key . static::_buildPair("", $val);
                    break;
                default:
                    static::$_query .= static::_buildPair($key, $val);
            }
        }
    }

    /**
     * Abstraction method that will build the part of the HAVING conditions
     */
    static protected function _buildHaving() {
        if (empty(static::$_having))
            return;

        //Prepair the where portion of the query
        static::$_query .= ' HAVING ';

        // Remove first AND/OR concatenator
        static::$_having[0][0] = '';
        foreach (static::$_having as $cond) {
            list ($concat, $wValue, $wKey) = $cond;

            static::$_query .= " " . $concat . " " . $wKey;

            // Empty value (raw where condition in wKey)
            if ($wValue === null)
                continue;

            // Simple = comparison
            if (!\is_array($wValue))
                $wValue = Array('=' => $wValue);

            $key = key($wValue);
            $val = $wValue[$key];
            switch (\strtolower($key)) {
                case '0':
                    static::_bindParams($wValue);
                    break;
                case 'not in':
                case 'in':
                    $comparison = ' ' . $key . ' (';
                    if (\is_object($val)) {
                        $comparison .= static::_buildPair("", $val);
                    } else {
                        foreach ($val as $v) {
                            $comparison .= ' ?,';
                            static::_bindParam($v);
                        }
                    }
                    static::$_query .= \rtrim($comparison, ',') . ' ) ';
                    break;
                case 'not between':
                case 'between':
                    static::$_query .= " $key ? AND ? ";
                    static::_bindParams($val);
                    break;
                case 'not exists':
                case 'exists':
                    static::$_query .= $key . static::_buildPair("", $val);
                    break;
                default:
                    static::$_query .= static::_buildPair($key, $val);
            }
        }
    }

    /**
     * Abstraction method that will build the GROUP BY part of the WHERE statement
     *
     */
    static protected function _buildGroupBy() {
        if (empty(static::$_groupBy))
            return;

        static::$_query .= " GROUP BY ";
        foreach (static::$_groupBy as $key => $value)
            static::$_query .= $value . ", ";

        static::$_query = \rtrim(static::$_query, ', ') . " ";
    }

    /**
     * Abstraction method that will build the ORDER BY part of the WHERE statement
     *
     * @param int   $numRows   The number of rows total to return.
     */
    static protected function _buildOrderBy() {
        if (empty(static::$_orderBy))
            return;

        static::$_query .= " ORDER BY ";
        foreach (static::$_orderBy as $prop => $value)
            static::$_query .= $prop . " " . $value . ", ";

        static::$_query = \rtrim(static::$_query, ', ') . " ";
    }

    /**
     * Abstraction method that will build the LIMIT part of the WHERE statement
     *
     * @param int   $numRows   The number of rows total to return.
     */
    static protected function _buildLimit($numRows) {
        if (!isset($numRows))
            return;

        if (\is_array($numRows))
            static::$_query .= ' LIMIT ' . (int) $numRows[0] . ', ' . (int) $numRows[1];
        else
            static::$_query .= ' LIMIT ' . (int) $numRows;
    }

    /**
     * Method attempts to prepare the SQL query
     * and throws an error if there was a problem.
     *
     * @return mysqli_stmt
     */
    static protected function _prepareQuery() {
        if (!$stmt = static::$_mysqli->prepare(static::$_query)) {
            \trigger_error("Problem preparing query (static::_query) " . static::$_mysqli->error, \E_USER_ERROR);
        }
        return $stmt;
    }

    /**
     * Keine ahunugg
     * 
     * @param array $arr
     *
     * @return array
     */
    static protected function refValues($arr) {
        //Reference is required for PHP 5.3+
        if (\strnatcmp(\phpversion(), '5.3') >= 0) {
            $refs = array();
            foreach ($arr as $key => $value) {
                $refs[$key] = & $arr[$key];
            }
            return $refs;
        }
        return $arr;
    }

    /**
     * Function to replace ? with variables from bind variable
     * @param string $str
     * @param Array $vals
     *
     * @return string
     */
    static protected function replacePlaceHolders($str, $vals) {
        $i = 1;
        $newStr = "";

        while ($pos = strpos($str, "?")) {
            $val = $vals[$i++];
            if (\is_object($val))
                $val = '[object]';
            $newStr .= \substr($str, 0, $pos) . $val;
            $str = \substr($str, $pos + 1);
        }
        $newStr .= $str;
        return $newStr;
    }

    /**
     * Method returns last executed query
     *
     * @return string
     */
    static public function getLastQuery() {
        return static::$_lastQuery;
    }

    /**
     * Method returns mysql error
     * 
     * @return string
     */
    static public function getLastError() {
        return \trim(static::$_stmtError . " " . static::$_mysqli->error);
    }

    /* Helper functions */

    /**
     * Method returns generated interval function as a string
     *
     * @param string interval in the formats:
     *        "1", "-1d" or "- 1 day" -- For interval - 1 day
     *        Supported intervals [s]econd, [m]inute, [h]hour, [d]day, [M]onth, [Y]ear
     *        Default null;
     * @param string Initial date
     *
     * @return string
     */
    static public function interval($diff, $func = "NOW()") {
        $types = Array("s" => "second", "m" => "minute", "h" => "hour", "d" => "day", "M" => "month", "Y" => "year");
        $incr = '+';
        $items = '';
        $type = 'd';

        if ($diff && \preg_match('/([+-]?) ?([0-9]+) ?([a-zA-Z]?)/', $diff, $matches)) {
            if (!empty($matches[1]))
                $incr = $matches[1];
            if (!empty($matches[2]))
                $items = $matches[2];
            if (!empty($matches[3]))
                $type = $matches[3];
            if (!\in_array($type, \array_keys($types)))
                \trigger_error("invalid interval type in '{$diff}'");
            $func .= " " . $incr . " interval " . $items . " " . $types[$type] . " ";
        }
        return $func;
    }

    /**
     * Method returns generated interval function as an insert/update function
     *
     * @param string interval in the formats:
     *        "1", "-1d" or "- 1 day" -- For interval - 1 day
     *        Supported intervals [s]econd, [m]inute, [h]hour, [d]day, [M]onth, [Y]ear
     *        Default null;
     * @param string Initial date
     *
     * @return array
     */
    static public function now($diff = null, $func = "NOW()") {
        return Array("[F]" => Array(static::interval($diff, $func)));
    }

    /**
     * Method generates incremental function call
     * @param int increment amount. 1 by default
     */
    static public function inc($num = 1) {
        return Array("[I]" => "+" . (int) $num);
    }

    /**
     * Method generates decrimental function call
     * @param int increment amount. 1 by default
     */
    static public function dec($num = 1) {
        return Array("[I]" => "-" . (int) $num);
    }

    /**
     * Method generates change boolean function call
     * @param string column name. null by default
     */
    static public function not($col = null) {
        return Array("[N]" => (string) $col);
    }

    /**
     * Method generates user defined function call
     * @param string user function body
     * @param $bindParams
     */
    static public function func($expr, $bindParams = null) {
        return Array("[F]" => Array($expr, $bindParams));
    }

    static protected $stack = array();

    /**
     * Mostly internal method to get query and its params out of subquery object
     * after get() and getAll()
     * 
     * @return array
     */
    static public function endSubQuery() {
        if (!static::$isSubQuery)
            return null;

        $res = new \stdclass();

        \array_shift(static::$_bindParams);

        $res->query = static::$_query;
        $res->params = static::$_bindParams;

        static::reset();

        static::$_count = array_pop(static::$stack);
        static::$_query = array_pop(static::$stack);
        static::$_bindParams = array_pop(static::$stack);
        static::$_groupBy = array_pop(static::$stack); //!
        static::$_orderBy = array_pop(static::$stack); //!
        static::$_join = array_pop(static::$stack); //!
        static::$_having = array_pop(static::$stack); //!
        static::$_where = array_pop(static::$stack); //!

        static::$isSubQuery = false;

        return $res;
    }

    /**
     * Method creates new mysqlidb object for a subquery generation
      MOD Stefan Beyer no statics
     */
    public static function startSubQuery() { // TODO FROM andere Tabelle ??
        array_push(static::$stack, static::$_where); //!
        array_push(static::$stack, static::$_having); //!
        array_push(static::$stack, static::$_join); //!
        array_push(static::$stack, static::$_orderBy); //!
        array_push(static::$stack, static::$_groupBy); //!
        array_push(static::$stack, static::$_bindParams); // die nächsten braucht man vermutlich nicht
        array_push(static::$stack, static::$_query);
        array_push(static::$stack, static::$_count);
        static::reset();
        static::$isSubQuery = true;
    }

    /**
     * Method returns a copy of a mysqlidb subquery object
     *
     * @param object new mysqlidb object

      static public function copy ()
      {
      return clone $this;
      } */

    /**
     * Begin a transaction
     *
     * @uses mysqli->autocommit(false)
     * @uses register_shutdown_function(array($this, "_transaction_shutdown_check"))
     */
    static public function startTransaction() {
        static::$_mysqli->autocommit(false);
        static::$_transaction_in_progress = true;
        \register_shutdown_function(array(static::getStaticClass(), "_transaction_status_check"));
    }

    /**
     * Transaction commit
     *
     * @uses mysqli->commit();
     * @uses mysqli->autocommit(true);
     */
    static public function commit() {
        static::$_mysqli->commit();
        static::$_transaction_in_progress = false;
        static::$_mysqli->autocommit(true);
    }

    /**
     * Transaction rollback function
     *
     * @uses mysqli->rollback();
     * @uses mysqli->autocommit(true);
     */
    static public function rollback() {
        static::$_mysqli->rollback();
        static::$_transaction_in_progress = false;
        static::$_mysqli->autocommit(true);
    }

    /**
     * Shutdown handler to rollback uncommited operations in order to keep
     * atomic operations sane.
     *
     * @uses mysqli->rollback();
     */
    static public function _transaction_status_check() {
        if (!static::$_transaction_in_progress)
            return;
        static::rollback();
    }

    ####################################################################
    # Klassen-Name <--> Tabellenname
    /**
     * Klassenname der aufrufenen Klasse ermitteln
     */

    static function getStaticClass() {
        return \get_called_class();
        // return static::$className // fallback PHP < 5.3
    }

    /**
     * Tabellenname erzeugen (Mehrzahl)
     */
    static function getTablename() {
        return static::getTablenameSingular() . 's';
    }

    /**
     * Tabellenname erzeugen (Einzahl)
     */
    static function getTablenameSingular() {
        return \str_replace('\\', '_', \strtolower(static::getStaticClass()));
    }

    /**
     * Klassenname des aufrufenen Objektes ermitteln
     */
    function getClass() {
        return \get_class($this);
    }

}
