<?php
/**
 * Created D/10/02/2013
 * Updated S/02/03/2013
 * Version 2
 *
 * Copyright 2013 | Fabrice Creuzot (luigifab) <code~luigifab~info>
 * https://redmine.luigifab.info/projects/magento/wiki/cronlog
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

class Luigifab_Cronlog_Block_Adminhtml_History_Edit extends Mage_Adminhtml_Block_Widget_Form_Container {

	public function __construct() {

		parent::__construct();

		$this->_controller = 'adminhtml_history';
		$this->_blockGroup = 'cronlog';

		$this->_removeButton('reset');
		$this->_removeButton('delete');
		$this->_updateButton('save', 'label', $this->helper('adminhtml')->__('Add'));
	}

	public function getHeaderText() {
		return $this->__('New cron job');
	}
}