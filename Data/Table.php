<?php
/**
 * @method Data_Rowset fetchAll($where = null, $order = null, $count = null, $offset = null)
 */
abstract class Data_Table extends Zend_Db_Table_Abstract  {

	const ZDB_ADAPTER_KEY = '';

	static protected $tableSchema;

	static public function setTableSchema($v) {
		static::$tableSchema = $v;
	}

	public function __construct($config = array()) {
		if (static::ZDB_ADAPTER_KEY && !@$config[static::ADAPTER]) {
			$adapter = Zend_Registry::isRegistered(static::ZDB_ADAPTER_KEY) ? Zend_Registry::get(static::ZDB_ADAPTER_KEY) : null;
			if ($adapter instanceof Zend_Db_Adapter_Abstract) {
				if (!$config) {
					$config = array();
				}
				$config[static::ADAPTER] = $adapter;
			}
		}
		if (static::$tableSchema && !@$config[static::SCHEMA]) {
			if (!$config) {
				$config = array();
			}
			$config[static::SCHEMA] = static::$tableSchema;
		}
		parent::__construct($config);
	}

	protected $_rowsetClass = 'Data_Rowset';

	/**
	 * @var array with the columns description
	 * example of each row: array('name' => 'user_id', 'type' => 'int', 'nullable' => false, 'default' => 0, );
	 */
	static protected $columnsInfo = array();

	/**
	 * load into $columnInfo the info given
	 *
	 * @static
	 * @abstract
	 * @return array
	 */
	abstract static public function loadColumnsInfoArray();

	/**
	 * @static
	 * @return array
	 * @uses loadColumnsInfoArray()
	 */
	static public function getColumnsInfoArray() {
		if (!static::$columnsInfo) {
			static::$columnsInfo = static::loadColumnsInfoArray();
		}

		return static::$columnsInfo;
	}

	static public function getColumnInfoArray($columnName) {
		$infos = static::getColumnsInfoArray();
		return @$infos[$columnName];
	}

	static public function checkColumnExists($columnName) {
		$infos = static::getColumnsInfoArray();
		return array_key_exists($columnName, $infos);
	}

	static public function checkColumnIsInt($columnName) {
		$infos = static::getColumnsInfoArray();
		return @$infos[$columnName]['type'] == 'int';
	}

	static public function checkColumnIsBool($columnName) {
		$infos = static::getColumnsInfoArray();
		return @$infos[$columnName]['type'] == 'bool';
	}


	public function getValidColumns() {
		return $this->_getCols();
	}

	public function isValidColumn($column) {
		return in_array($column, $this->getValidColumns());
	}


	/**
	 * Fetches one row in an array, not an object of type rowClass,
	 * or returns null if no row matches the specified criteria.
	 *
	 * @param string|array|Zend_Db_Table_Select $where  OPTIONAL An SQL WHERE clause or Zend_Db_Table_Select object.
	 * @param string|array                      $order  OPTIONAL An SQL ORDER clause.
	 * @param int                               $offset OPTIONAL An SQL OFFSET value.
	 *
	 * @return array|null The row results per the
	 *     Zend_Db_Adapter fetch mode, or null if no row found.
	 */
	public function fetchRowAsArray($where = null, $order = null, $offset = null) {
		if (!($where instanceof Zend_Db_Table_Select)) {
			$select = $this->select();

			if ($where !== null) {
				$this->_where($select, $where);
			}

			if ($order !== null) {
				$this->_order($select, $order);
			}

			$select->limit(1, ((is_numeric($offset)) ? (int) $offset : null));

		} else {
			$select = $where->limit(1, $where->getPart(Zend_Db_Select::LIMIT_OFFSET));
		}

		$rows = $this->_fetch($select);

		if (count($rows) == 0) {
			return null;
		}
		return current($rows);
	}


	/**
	 * Checks if the one we are looking for exists
	 *
	 * @param string|array|Zend_Db_Table_Select $where  An SQL WHERE clause or Zend_Db_Table_Select object.
	 *
	 * @return boolean if the result exists
	 */
	public function existsRow($where) {
		if (!$where) {
			throw new Data_Exception('where argument invalid (null)');
		}

		if ($where instanceof Zend_Db_Table_Select) {
			$select = $where;
		} else {
			$select = $this->select(true);

			$this->_where($select, $where);
		}

		$select->limit(1);
		/** @var $select Zend_Db_Select */
		$select->reset(Zend_Db_Select::COLUMNS);
		$select->columns(array('COUNT(1) as cnt'));

		$rows = $this->_fetch($select);

		if (!$rows) {
			return false;
		}

		$x = current($rows);
		return $x['cnt'] > 0;
	}


	/**
	 * Fetches all rows in a form of array[FIRST_COLUMN] => SECOND_COLUMN or array[FIRST_COLUMN] => array(REST OF THE ROW)
	 *
	 * @param string|array|Zend_Db_Table_Select $where  OPTIONAL An SQL WHERE clause or Zend_Db_Table_Select object.
	 * @param string|array                      $order  OPTIONAL An SQL ORDER clause.
	 * @param int                               $count  OPTIONAL An SQL LIMIT count.
	 * @param int                               $offset OPTIONAL An SQL LIMIT offset.
	 *
	 * @return array
	 */
	public function fetchAllAsKeyValuePairs($where = null, $order = null, $count = null, $offset = null) {
		if ($where instanceof Zend_Db_Table_Select) {
			$select = $where;
		} else {
			$select = $this->select();

			if ($where !== null) {
				$this->_where($select, $where);
			}

			if ($order !== null) {
				$this->_order($select, $order);
			}

			if ($count !== null || $offset !== null) {
				$select->limit($count, $offset);
			}
		}

		$stmt = $this->_db->query($select);

		$num_fields = $stmt->columnCount();
		$type       = $num_fields > 2 ? Zend_Db::FETCH_ASSOC : Zend_Db::FETCH_NUM;

		$result_array = array();
		while ($row = $stmt->fetch($type)) {
			if ($num_fields == 2) {
				$result_array[(string) $row[0]] = $row[1];
			} elseif ($num_fields == 1) {
				$result_array[] = $row[0];
			} else {
				$result_array[(string) array_shift($row)] = $row;
			}
		}
		return $result_array;
	}


	/**
	 * Fetches all rows in a form of array[FIRST_COLUMN] => SECOND_COLUMN or array[FIRST_COLUMN] => array(REST OF THE ROW)
	 *
	 * @param string|array|Zend_Db_Table_Select $columns  array of columns to use (if only 2 it would be first_column as key and second_column as value).
	 * @param string|array                      $order  OPTIONAL An SQL ORDER clause.
	 * @param int                               $count  OPTIONAL An SQL LIMIT count.
	 * @param int                               $offset OPTIONAL An SQL LIMIT offset.
	 *
	 * @return array
	 */
	public function fetchAllAsKeyValuePairsColumns($columns = array(), $order = null, $count = null, $offset = null) {
		return $this->getAsKeyValuePairsColumns($columns, null, $order, $count, $offset);
	}

	public function getAsKeyValuePairsColumns($columns = array(), $where = null, $order = null, $count = null, $offset = null) {
		if ($where instanceof Zend_Db_Select) {
			/** @var $where Zend_Db_Select */
			if ($columns) {
				$where->reset(Zend_Db_Select::COLUMNS);
				$where->columns($columns);
			}
			return $this->fetchAllAsKeyValuePairs($where, $order, $count, $offset);
		} else {
			$s = null;
			if ($columns || $where) {
				$s = $this->select(true);

				if ($columns) {
					$s->reset(Zend_Db_Select::COLUMNS);
					$s->columns($columns);
				}

				if ($where) {
					$this->_where($s, $where);
				}
			}

			return $this->fetchAllAsKeyValuePairs($s, $order, $count, $offset);
		}

	}

	public function getCol($column_name, $where = null, $order = null, $count = null, $offset = null) {
		if ($where instanceof Zend_Db_Select) {
			$s = $where;
		} else {
			$s = $this->select(true);
			if ($where) {
				$this->_where($s, $where);
			}

			if ($order) {
				$this->_order($s, $order);
			}

			if ($count !== null || $offset !== null) {
				$s->limit($count, $offset);
			}
		}
		/** @var $s Zend_Db_Select */
		$s->reset(Zend_Db_Select::COLUMNS);
		$s->columns(array($column_name));

		$q = $s->query();
		/** @var $q Zend_Db_Statement */
		return $q->fetchAll(Zend_Db::FETCH_COLUMN);
	}

	/**
	 * por defecto QUITAMOS EL INTEGRITY CHECK
	 *
	 * @param bool $withFromPart
	 * @return Zend_Db_Table_Select
	 */
	public function select($withFromPart = self::SELECT_WITHOUT_FROM_PART) {
		$s = parent::select($withFromPart);
		if ($s) {
			$s->setIntegrityCheck(false);
		}
		return $s;
	}
}