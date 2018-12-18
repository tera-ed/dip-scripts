<?php
class RecolinDatabase{

	private $dbconn, $logger;

	function __construct($logger){
		global $RECOLIN_DB;
		$this->logger = $logger;
		$this->dbconn = $this->connect($RECOLIN_DB);
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
			$this->logger->info('Connected successfully BATCH DB');
		}
		catch(Exception $e){
			$this->logger->error("Connection failed: " . $e->getMessage());
			throw $e;
		}
		return $conn;
	}

	/**
	 * Begin a transaction, turning off autocommit
	 */
	function beginTransaction(){
		try {
			$this->dbconn->beginTransaction();
		}catch(Exception $e){
			$this->logger->error($e->getMessage());
			throw $e;
		}
	}

	/**
	 * Commit changes
	 */
	function commit(){
		try {
			$this->dbconn->commit();
		}catch(Exception $e){
			$this->logger->error($e->getMessage());
			throw $e;
		}
	}

	/**
	 * Roll back changes
	 * Database connection back to autocommit
	 */
	function rollback(){
		try {
			$this->dbconn->rollBack();
		}catch(Exception $e){
			$this->logger->error($e->getMessage());
			throw $e;
		}
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
	function getData($fields, $table, $condition = null, $params = array(), $statement = null){
		try {
			if(!$fields || empty($fields)) {
				$fields = "*";
			}
			
			$sql = "SELECT $fields FROM $table";
			if($condition && !empty($condition)) {
				$sql .=" WHERE $condition";
			}

			if($statement && !empty($statement)) {
				$sql .= $statement;
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
	 * Executes insert query
	 * @param string $table
	 * @param array $params
	 * @return int
	 */
	function insertData($table, $params){
		try {
			$this->logger->info("Register data to $table table");
			$insertData = $this->getInsertFields($params, $table);
			$columns = implode(',', array_keys($insertData));
			$values = implode(',', array_fill(0, count($insertData), '?'));
			
			$sql = "INSERT INTO $table ({$columns}) VALUES ({$values})";
			$query = $this->executeQuery($sql, array_values($insertData), true);
			$count = $query->rowCount();
		}catch(Exception $e){
			$this->logger->error($sql, $e->getMessage());
			throw $e;
		}
		return $count;
	}
	
	/**
	 * 
	 * Execute insert query. No Logs when error
	 * @param String $table
	 * @param Array $params
	 */
	function insertDataWithoutCommon($table, $params){
		try {
			$columns = implode(',', array_keys($params));
			$values = implode(',', array_fill(0, count($params), '?'));
			
			// added date
			$columns .= ', create_date, update_date';
			$values .= ', NOW(), NOW()';
			
			$sql = "INSERT INTO $table ({$columns}) VALUES ({$values})";
			$query = $this->executeQuery($sql, array_values($params), true);
			$count = $query->rowCount();
			$this->logger->info("Register data to $table table");
		}catch(Exception $e){
			$this->logger->error("Failed to Register data to $table table");
			throw $e;
		}
		return $count;
	}

	/**
	 * Insert record when data is not existing
	 * Update record if exists
	 * @param string $table
	 * @param array $params - array of columns and values to be inserted
	 * @param id - primary key
	 * @return int
	 */
	function insertUpdateData($table, $params, $id){
//		$result = 0;
		try {
			$dataCount = $this->getDataCount($table, $id."=?", array($params[$id]));
			if($dataCount <= 0 ){ // Data not found, insert
				$result = $this->insertData($table, $params);
			} else { // Data found, update
				$updateFields = $params;
				unset($updateFields[$id]);
				$result = $this->updateData($table, $updateFields, $id."=?", array($params[$id]));
			}
		}catch(Exception $e){
			$this->logger->error($e->getMessage());
			throw $e;
		}
		return $result;
	}

	/**
	 * Executes update query
	 * @param string $table
	 * @param array $updateFields
	 * @param string $condition
	 * @param array $params
	 * @return int
	 */
	function updateData($table, $updateFields, $condition = null, $params = array()){
		try{
			$this->logger->info("Update data to $table table");
			$fields = $this->getUpdateFields($updateFields, $table);
			
			$sql = "UPDATE $table SET $fields";
			if($condition && !empty($condition)){
				$sql .= " WHERE $condition";
			}
			$query = $this->executeQuery($sql, $params);
			$count = $query->rowCount();
		}catch (Exception $e){
			$this->logger->error($sql, $e->getMessage());
			throw $e;
		}
		return $count;
	}

	/**
	 * Executes delete query
	 * @param string $table
	 * @param string $condition
	 * @param array $params
	 * @return int
	 */
	function deleteData($table, $condition = null, $params = array()){
		try{
			$sql = "DELETE FROM $table";
			if($condition && !empty($condition)){
				$sql .= " WHERE $condition";
			}
			$query = $this->executeQuery($sql, $params);
			$count = $query->rowCount();
		}catch (Exception $e){
			$this->logger->error($sql, $e->getMessage());
			throw $e;
		}
		return $count;
	}
	
	
	/**
	 * Executes truncate query
	 * @param string $table
	 */
	function truncateData($table){
		$params = array();
		$bool = false;
		try{
			$sql = "TRUNCATE $table";
			$query = $this->executeQuery($sql, $params);
			$bool = true;
		}catch (Exception $e){
			$this->logger->error($sql, $e->getMessage());
			$bool = false;
			throw $e;
		}
		return $bool;
	}

	/**
	 * Close connection to mysql
	 */
	function disconnect(){
		$this->dbconn = null;
		$this->logger->info('Recolin DB disconnected successfully');
	}

	/**
	 * Get insert fields
	 * @param array $params
	 * @param string $table
	 */
	function getInsertFields($params, $table){
		global $SYSTEM_USER;
		$addedFields = array();
		$currentDate = date("Y/m/d H:i:s");
		//if table starts with 'm_'
		if(strpos($table, 'm_') === 0){
			$addedFields = array(
				"create_date" => $currentDate,
				"create_user_code" => $SYSTEM_USER,
				"update_date" => $currentDate,
				"update_user_code" => $SYSTEM_USER
			);
		}
		$finalArray = array_merge($params, $addedFields);
		return $finalArray;
	}

	/**
	 * Get update fields for update query
	 * @param array $params
	 * @return string
	 */
	function getUpdateFields($params, $table){
		global $SYSTEM_USER;
		$set = '';
		foreach($params as $key => $value){
			if(is_null($value)){
				$set .= "`$key`=null, ";
			} elseif(is_bool($value) === true){
				$value = $value?1:0;
				$set .= "`$key`=$value , ";
			} elseif (is_numeric($value)) {
				$set .= "`$key`='$value', ";
			} else {
				$data = $this->dbconn->quote($value);
				$set .= "`$key`=$data, ";
			}
		}
		//if table starts with 'm_'
		if(strpos($table, 'm_') === 0){
			$currentDate = date("Y/m/d H:i:s");
			$set .= "`update_date`='$currentDate', ";
			$set .= "`update_user_code`='$SYSTEM_USER', ";
		}
		return rtrim($set, ", ");
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


	/**
	 * Returns the next value of the sequence
	 * @param string $seqName
	 * @param string $prefix
	 * @param boolean $hasLpad - if needs LPAD
	 * @param int $len - length of leading $pad
	 * @param string $pad - string to be used in padding
	 * @return string
	 */
	function getNextVal($seqName){
		global $SEQUENCE;

		try{
			$nextVal = "NEXTVAL('$seqName')";
			$stmt = $nextVal;

			if($SEQUENCE[$seqName]['lpad']) {
				// append string
				$stmt = "LPAD($nextVal,".$SEQUENCE[$seqName]['len'].",". $SEQUENCE[$seqName]['pad'].")";
			}

			$sql = "SELECT $stmt";
			$q = $this->dbconn->prepare($sql);
			$q->execute();
			$result = $q->fetchAll();
			$result = $SEQUENCE[$seqName]['prefix'].$result[0][0];
		} catch(Exception $e){
			throw $e;
		}
		return $result;
	}

	function getBatchSyncNo($seqName){

		try{
			$nextVal = "NEXTVAL('$seqName')";
			$stmt = $nextVal;
			
			$sql = "SELECT $stmt";
			$q = $this->dbconn->prepare($sql);
			$q->execute();
			$result = $q->fetchAll();
			$result = $result[0][0];
		} catch(Exception $e){
			throw $e;
		}
		return $result;
	}
}
?>