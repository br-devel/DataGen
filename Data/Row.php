<?php

class Data_Row extends Zend_Db_Table_Row_Abstract {

	/**
	 * to be extended, returns an array with the default data for a new entity
	 *
	 * @return array
	 */
	public function _getDefaultData() {
		return array();
	}


}