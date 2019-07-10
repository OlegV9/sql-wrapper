<?php

namespace DBWrapper;

class Connection
{
	const FETCH_ASSOC = 'FETCH_ASSOC';
	const FETCH_NUM = 'FETCH_NUM';
	const FETCH_OBJ = 'FETCH_OBJ';
	const ATTR_DEFAULT_FETCH_MODE = 'ATTR_DEFAULT_FETCH_MODE';
	const ATTR_CAMEL_CASE = 'ATTR_CAMEL_CASE';

	const FIELD_TINYINT = 1;
	const FIELD_SMALLINT = 2;
	const FIELD_MEDIUMINT = 9;
	const FIELD_INTEGER = 3;
	const FIELD_BIGINT = 8;
	const FIELD_FLOAT = 4;
	const FIELD_DOUBLE = 5;
	const FIELD_DECIMAL = 246;

	private $conn;
	private $connectError;
	private $attrs = array();
	private $lastQuery;
	private $reseller = null;
	private $host = null;

	private $beforeQueryCallback = null;
	private $afterQueryCallback = null;

	private static $conns = [];

	public function __construct($host, $user, $pass, $db = null, $opts = [])
	{
		$this->host = $host;
		$connKey = $db.'*'.$host;
		if (isset(self::$conns[$connKey]) && empty($opts['forceNew'])) {
			$this->conn = self::$conns[$connKey];
		} else {
			$this->conn = mysqli_connect($host, $user, $pass, $db);
			$this->connectError = mysqli_connect_error();
			if ($this->connectError) return;

			self::$conns[$connKey] = $this->conn;
			mysqli_query($this->conn, 'SET NAMES utf8');
		}
	}

	public function query($query)
	{
		$args = func_get_args();
		if (count($args) > 1) {
			$query = call_user_func_array(array($this, 'prepareQuery'), $args);
		}

		if (is_callable($this->beforeQueryCallback)) {
			$fn = $this->beforeQueryCallback;
			$newQuery = $fn($query);
			if ($newQuery) {
				$query = $newQuery;
			}
		}

		$this->lastQuery = $query;

		$start = microtime(true);
		$res = mysqli_query($this->conn, $query);
		$end = microtime(true);

		$time = $end - $start;

		if (is_callable($this->afterQueryCallback)) {
			$fn = $this->afterQueryCallback;
			$fn($this, $res, $time);
		}

		return new QueryResult($this->conn, $res, $this->attrs);
	}

	public function onBeforeQuery($fn)
	{
		$this->beforeQueryCallback = $fn;
	}

	public function onAfterQuery($fn)
	{
		$this->afterQueryCallback = $fn;
	}

	public function exec($query)
	{
		return $this->query($query);
	}

	public function quote($raw)
	{
		return "'".mysqli_real_escape_string($this->conn, $raw)."'";
	}

	public function quoteArray($array)
	{
		$newArray = [];
		foreach ($array as $elem) {
			$newArray[] = $this->quote($elem);
		}
		return $newArray;
	}

	public function escape($raw)
	{
		return mysqli_real_escape_string($this->conn, $raw);
	}

	public function escapeWord($raw)
	{
		$param = preg_replace('~[^a-zA-Z0-9_.-]~', '', $raw);
		$param = '`'.$param.'`';
		return $param;
	}

	public function affectedRows()
	{
		return mysqli_affected_rows($this->conn);
	}

	public function escapeLike($raw)
	{
		$param = mysqli_real_escape_string($this->conn, $raw);
		$param = str_replace(array('_', '%'), array('\_', '\%'), $param);
		return $param;
	}

	/* alias to lastInsertId */
	public function insertId()
	{
		return $this->lastInsertId();
	}

	public function lastInsertId()
	{
		return mysqli_insert_id($this->conn);
	}

	public function startTransaction()
	{
		$this->query('START TRANSACTION');
	}

	public function commit()
	{
		$this->query('COMMIT');
	}

	public function rollback()
	{
		$this->query('ROLLBACK');
	}

	public function getConnectError()
	{
		return $this->connectError;
	}

	public function ping()
	{
		return mysqli_ping($this->conn);
	}

	public function setAttribute($key, $value)
	{
		$this->attrs[$key] = $value;
	}

	public function getAttribute($key)
	{
		if (!isset($this->attrs[$key])) return null;
		return $this->attrs[$key];
	}

	public function getLastError()
	{
		return mysqli_error($this->conn);
	}

	/* alias to getLastError */
	public function error()
	{
		return $this->getLastError();
	}

	public function getLastQuery()
	{
		return $this->lastQuery;
	}

	/* alias to getLastQuery */
	public function lastQuery()
	{
		return $this->getLastQuery();
	}

	public function close()
	{
		return mysqli_close($this->conn);
	}

	public function prepareQuery()
	{
		$args = func_get_args();
		$rawQuery = $args[0];

		if (count($args) <= 1) return $rawQuery;

		if (is_array($args[1])) {
			$params = $args[1];
		} else {
			$params = array_slice($args, 1);
		}
		$curIndex = 0;
		$query = preg_replace_callback('/:[ifslw]\??/', function($m) use($params, &$curIndex) {
			$placeholder = $m[0];
			if (strpos($placeholder, '?') !== false) {
				$placeholder = rtrim($placeholder, '?');
				$nullable = true;
			} else {
				$nullable = false;
			}

			if (!isset($params[$curIndex])) {
				$curIndex++;
				if ($nullable) return 'NULL';
				return $placeholder;
			}

			$param = $params[$curIndex++];
			switch ($placeholder) {
				case ':i': return intval($param);
				case ':f': return floatval($param);
				case ':s': return $this->quote($param);
				case ':l': return $this->escapeLike($param);
				case ':w': return $this->escapeWord($param);
			}
		}, $rawQuery);

		return $query;
	}

	public function checkTableExists($tbl, $database = null)
	{
		if ($database) {
			$query = $this->prepareQuery('SELECT 1 FROM :w.:w LIMIT 1', $database, $tbl);
		} else {
			$query = $this->prepareQuery('SELECT 1 FROM :w LIMIT 1', $tbl);
		}
		@$this->query($query);
		if ($this->error()) return false;
		return true;
	}

	/* alias to checkTableExists */
	public function tableExists($tbl, $database = null)
	{
		return $this->checkTableExists($tbl, $database);
	}

	public function getHost()
	{
		return $this->host;
	}
}
