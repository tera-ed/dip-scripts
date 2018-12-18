<?php
class RDSDatabase{

	private $dbconn, $logger;

	function __construct($logger){
		global $RDS_DB;
		$this->logger = $logger;
		$this->dbconn = $this->connect($RDS_DB);
	}
	/**
	 * create connection to mysql
	 * @param list $DB
	 * @return PDO
	 */
	function connect($DB){
		$servername = $DB['db_server'];
		$db = $DB['db_name'];
		try {
			$conn = new PDO("mysql:host=$servername;dbname=$db",$DB['db_username'], $DB['db_password']);
			// set the PDO error mode to exception
			$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
			$conn->setAttribute(PDO::ATTR_AUTOCOMMIT, FALSE);
			$conn->exec("SET NAMES utf8");
			$this->logger->info('Connected successfully RDS DB');
		}
		catch(Exception $e){
			$this->logger->error("Connection failed: " . $e->getMessage());
			throw $e;
		}
		return $conn;
	}

	/**
	 * Executes SELECT all data from table
	 * @param string $table
	 * @return array of data
	 */
	function getAllData($table){
		try {
			$sql = "SELECT * FROM $table";
			$query = $this->executeQuery($sql);
			$list = $query->fetchAll(PDO::FETCH_ASSOC);
		}catch(Exception $e){
			$this->logger->error($sql, $e->getMessage());
			throw $e;
		}
		return $list;
	}

	/**
	 * Executes SELECT data with condition
	 * @param string $fields - fields to select
	 * @param string $table - table to select
	 * @param string $condition - where condition
	 * @param array $params - values of the condition ?'s
	 * @return array of data
	 */
	function getData($fields, $table, $condition = null, $params = array()){
		try {
			if(!$fields || empty($fields)) {
				$fields = "*";
			}

			$sql = "SELECT $fields FROM $table";
			if($condition && !empty($condition)) {
				$sql .=" WHERE $condition";
			}
			$query = $this->executeQuery($sql, $params);
			$list = $query->fetchAll(PDO::FETCH_ASSOC);
		}catch(Exception $e){
			$this->logger->error($sql, $e->getMessage());
			throw $e;
		}
		return $list;
	}

	/**
	 * Executes SELECT data with condition
	 * @param string $fields - fields to select
	 * @param string $table - table to select
	 * @param string $condition - where condition
	 * @param array $params - values of the condition ?'s
	 * @param array $limit - limit
	 * @param array $offset - offset
	 * @return array of data
	 */
	function getLimitOffsetData($fields, $table, $condition = null, $params = array(), $limit = 500, $offset = 0){
		$list = array();
		try {
			$sql = "SELECT * FROM $table";
			if($fields && !empty($fields)) {
				$sql = "SELECT $fields FROM $from";
			}
			if($condition && !empty($condition)) {
				$sql .=" WHERE $condition";
			}
			$sql .=" limit $limit";
			$sql .=" offset $offset";
			$query = $this->executeQuery($sql, $params);
			if ($query){
				$list = $query->fetchAll(PDO::FETCH_ASSOC);
				unset($query);
			}
		}catch(Exception $e){
			$this->logger->error($sql, $e->getMessage());
			throw $e;
		}
		return $list;
	}

	/**
	 * Executes SQL statement
	 * @param string $sql
	 * @param array $params
	 * @return array of data
	 */
	function getDataSql($sql, $params = array()){
		try {
			$query = $this->executeQuery($sql, $params);
			$list = $query->fetchAll(PDO::FETCH_ASSOC);
		}catch(Exception $e){
			$this->logger->error($sql, $e->getMessage());
			throw $e;
		}
		return $list;
	}

	/**
	 * Returns data count with condition
	 * @param string $table
	 * @param string $condition
	 * @param array $params - values of the condition ?'s
	 * @return int
	 */
	function getDataCount($table, $condition, $params){
		try {
			$sql = "SELECT COUNT(*) FROM $table ";
			if($condition && !empty($condition)){
				$sql .= " WHERE $condition";
			}
			$query = $this->executeQuery($sql, $params);
			$count = intVal($query->fetchColumn());
		}catch(Exception $e){
			$this->logger->error($sql, $e->getMessage());
			throw $e;
		}
		return $count;
	}

	/**
	 * Close connection to mysql
	 */
	function disconnect(){
		$this->dbconn = null;
		$this->logger->info('RDS DB disconnected successfully');
	}

	/**
	 * Execute the given query
	 * @param string $sql
	 * @param array $params
	 * @return PDOStatement
	 */
	function executeQuery($sql, $params, $isInsert = false){
		try{
			$query = $this->dbconn->prepare($sql);
			if($isInsert){
				foreach ($params as $key => $value){
					if($value === true){
						$query->bindValue($key+1, $value, PDO::PARAM_INT);
					} else {
						$query->bindValue($key+1, $value);
					}
					unset($params[$key]);
				}
				$query->execute();
			}else {
				$query->execute($params);
			}
		}catch (Exception $e){
			throw $e;
		}
		return $query;
	}
}
?>