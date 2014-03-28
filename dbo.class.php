<?php

// Simple DataBaseObject class. Create subclasses to implement tables.
class DBO {

	// Return an empty string for unknown fields
	public function __get($property)
	{
		if (property_exists($this, $property))
			return $this->$property;

		return '';
	}

	// Initializes this object from a result set row
	public function fromRow($row)
	{
		foreach($row as $key=>$value)
		{
			$this->$key = $value;
		}
	}

	// Save relevant object fields to DB
	public function commit($table = '')
	{
		$db = dbExt::getInstance();

		if(empty($table))
			$table = get_called_class();

		// get fields from the DB
		$q = $db->prepare("SELECT column_name from information_schema.columns where table_name = :table");
		$q->bindValue(":table", $table);
		$q->execute();

		$fields = array();

		while($field = $q->fetch())
			$fields[] = $field['column_name'];

		$columns = array();
		$dbcols = array();
		$values = array();
		$keyvalues = array();

		foreach ($this as $key=>$value)
		{
			if(!in_array($key, $fields))
				continue;

			$columns[] = "$key";
			$dbcols[] = "`$key`";
			$values[] = ':'.$key;
			$keyvalues[] = "`$key`=:$key";
		}

		$columnsStr = implode(',', $dbcols);
		$valuesStr = implode(',', $values);
		$keyvaluesStr = implode(',', $keyvalues);

		$result = $db->prepare("INSERT INTO `$table` ($columnsStr) VALUES ($valuesStr) ON DUPLICATE KEY UPDATE $keyvaluesStr");

		foreach ($columns as $key=>$value)
		{
			$result->bindValue($values[$key], $this->$columns[$key]);
		}

		$result->execute();

		$id = $db->lastInsertId();

		return $id;
	}

	// Totally remove database entry
	public function delete()
	{
		$db = dbExt::getInstance();

		$class = get_called_class();

		$pk = $class::$PK;

		$result = $db->prepare("DELETE FROM `$class` WHERE $pk = :id LIMIT 1");
		$result->bindValue(':id', $this->$pk);
		$result->execute();
	}

	// Initialize from an array (like $_POST)
	public static function fromArray($array)
	{
		$class = get_called_class();
		$obj = new $class();

		foreach ($array as $key=>$value)
		{
			$obj->$key = $value;
		}

		return $obj;
	}

	// Initialize objects from a result set, returns an array
	public static function fromResultSet($results)
	{
		$class = get_called_class();
		$ret = array();

		foreach($results as $row)
		{
			$post = new $class();
			$post->fromRow($row);
			$ret[] = $post;
		}

		return $ret;
	}

	// Returns an object from the given ID, or false
	public static function fromID($id, $field = '')
	{
		$class = get_called_class();

		$db = dbExt::getInstance();

		$field =!empty($field) ? $field : $class::$PK;

		$q = $db->prepare("SELECT * FROM $class WHERE $field = :id");
		$q->bindValue(':id', $id);
		$q->execute();

		if($q->rowCount() == 0)
			return false;

		$row = $q->fetch(PDO::FETCH_ASSOC);

		$o = $class::fromArray($row);

		return $o;
	}

	// Returns an array of objects
	public static function fetch($limit = 1, $offset = 0, $order = '')
	{
		$table = get_called_class();
		$order = !empty($order) ? $order : $table::$PK . ' DESC';

		$data = dbExt::getInstance()->run("SELECT * FROM $table ORDER BY $order LIMIT $offset, $limit");

		return $table::fromResultSet($data);
	}

	// Returns the number of pages of this object that exist if there are $limit items per page
	public static function getPageCount($limit)
	{
		$table = get_called_class();

		$data = dbExt::getInstance()->run("SELECT COUNT(*) AS count FROM $table");
		$count = $data[0]['count'];

		$pages = $count / $limit;
		$pages = $pages < 1 ? 1 : $pages;

		return ceil($pages);
	}

	// Returns an array of objects for the given $page, with $limit items per page
	public static function getPage($page, $limit)
	{
		$offset = $limit * ($page-1);

		return self::fetch($limit, $offset);
	}
}
