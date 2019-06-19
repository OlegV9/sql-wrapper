<?php

namespace DBWrapper;

class QueryResult implements \Iterator
{
	private $caller;
    private $conn;
    private $res;
    private $attrs;
    private $position = -1;
    private $currentRow;
	
	private static $intTypes = [
		Connection::FIELD_TINYINT,
		Connection::FIELD_SMALLINT,
		Connection::FIELD_MEDIUMINT,
		Connection::FIELD_INTEGER,
		Connection::FIELD_BIGINT
	];
	
	private static $floatTypes = [
		Connection::FIELD_FLOAT,
		Connection::FIELD_DOUBLE,
		Connection::FIELD_DECIMAL
	];

    public function __construct($conn, $res, $attrs = [], $caller = null)
	{
        $this->conn = $conn;
        $this->res = $res;
        $this->attrs = $attrs;
        $this->caller = $caller;
    }

    public function fetchColumn($colIndex = 0)
	{
        if (!$this->res || mysqli_num_rows($this->res) < 1) return null;
        return mysqli_fetch_row($this->res)[$colIndex];
    }

    public function fetch($mode = null)
	{
        if ($this->res === false) return null;

        $mode = $this->transformMode($mode);

        $this->position++;
        $row = mysqli_fetch_assoc($this->res);
        $this->currentRow = !empty($row) ? $this->transformRow($row, $mode) : false;
        return $this->currentRow;
    }

    public function fetchObject()
	{
        return $this->fetch(Connection::FETCH_OBJ);
    }

    public function fetchAssoc()
	{
        return $this->fetch(Connection::FETCH_ASSOC);
    }

    public function fetchNum()
	{
        return $this->fetch(Connection::FETCH_NUM);
    }

    public function fetchAll($mode = null)
	{
        if ($this->res === false) return null;

        $mode = $this->transformMode($mode);

        $rows = array();
        while ($row = mysqli_fetch_assoc($this->res))
		{
            $rows[] = $this->transformRow($row, $mode);
        }
        return $rows;
    }

    public function fetchAllCol($col = 0)
	{
        if ($this->res === false) return null;

        $arr = array();
        if (is_int($col)) {
            while ($row = mysqli_fetch_row($this->res)) {
                $arr[] = $row[$col];
            }
        } else {
            while ($row = mysqli_fetch_assoc($this->res)) {
                $arr[] = $row[$col];
            }
        }

        return $arr;
    }

    public function rowCount()
	{
        if ($this->res === false) return null;
        if ($this->res === true) return mysqli_affected_rows($this->conn);
        return mysqli_num_rows($this->res);
    }
	
	public function id()
	{
		return mysqli_insert_id($this->conn);
	}
	
	public function error()
	{
		return mysqli_error($this->conn);
	}

    public function rewind()
	{
        $this->position = -1;
        $this->next();
    }

    public function current()
	{
        return $this->currentRow;
    }

    public function key()
	{
        return $this->position;
    }

    public function next()
	{
        $this->currentRow = $this->fetch();
    }

    public function valid()
	{
        return !empty($this->currentRow);
    }

    public function lastQuery()
	{
		return $this->caller->getLastQuery();
	}
	
	private function camelize($raw)
	{
		return preg_replace_callback('~_([a-zA-Z])~', function($match) {
			return strtoupper($match[1]);
		}, trim($raw, '_'));
	}

    private function transformRow($row, $mode)
	{
		$fields = mysqli_fetch_fields($this->res);

		$shouldCamelize = !empty($this->attrs[Connection::ATTR_CAMEL_CASE]);

		$newRow = [];
		$i = 0;
		foreach ($row as $key => $val) {
			if ($shouldCamelize) {
				$key = $this->camelize($key);
			}

			$fieldType = $fields[$i]->type;
			if (in_array($fieldType, self::$intTypes) && $val !== null) {
				$val = intval($val);
			}
			if (in_array($fieldType, self::$floatTypes) && $val !== null) {
				$val = floatval($val);
			}

			$newRow[$key] = $val;
			$i++;
		}
		$row = $newRow;
		unset($newRow);

        switch ($mode) {
            case Connection::FETCH_ASSOC:	return $row;
            case Connection::FETCH_OBJ:		return (object)$row;
            case Connection::FETCH_NUM:		return array_values($row);
        }
        return null;
    }

    private function transformMode($mode)
	{
        if ($mode !== null) {
            return $mode;
        }
        if (isset($this->attrs[Connection::ATTR_DEFAULT_FETCH_MODE])) {
            return $this->attrs[Connection::ATTR_DEFAULT_FETCH_MODE];
        }
        return Connection::FETCH_OBJ;
    }
}