<?php
/**
 * Created S/27/06/2015
 * Updated M/28/02/2017
 *
 * Copyright 2012-2017 | Fabrice Creuzot (luigifab) <code~luigifab~info>
 * https://www.luigifab.info/magento/cronlog
 *
 * This program is free software, you can redistribute it or modify
 * it under the terms of the GNU General Public License (GPL) as published
 * by the free software foundation, either version 2 of the license, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but without any warranty, without even the implied warranty of
 * merchantability or fitness for a particular purpose. See the
 * GNU General Public License (GPL) for more details.
 */

class Luigifab_Cronlog_Block_Adminhtml_Config_Size extends Mage_Adminhtml_Block_System_Config_Form_Field {

	protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element) {

		$resource = Mage::getSingleton('core/resource');
		$read = $resource->getConnection('cronlog_read');

		$select = $read->select()
			->from('information_schema.TABLES', '(data_length + index_length) AS size_bytes')
			->where('table_schema = ?', Mage::getConfig()->getNode('global/resources/default_setup/connection/dbname'))
			->where('table_name = ?', $resource->getTableName('cron_schedule'));

		$element->setValue(floatval($read->fetchOne($select)));

		return sprintf('<span id="%s">%s</span>', $element->getHtmlId(), $this->helper('cronlog')->getNumberToHumanSize($element->getValue()));
	}
}