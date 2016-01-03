<?php
/**
 * Created D/31/08/2014
 * Updated S/06/09/2014
 * Version 3
 *
 * Copyright 2012-2016 | Fabrice Creuzot (luigifab) <code~luigifab~info>
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

class Luigifab_Cronlog_Model_Source_Lifetime extends Luigifab_Cronlog_Helper_Data {

	public function toOptionArray() {

		return array(
			array('value' => 0, 'label' => '--'),
			array('value' => 5 * 24 * 60,  'label' => $this->__('%d days', 5)),
			array('value' => 7 * 24 * 60,  'label' => $this->__('%d days', 7)),
			array('value' => 14 * 24 * 60, 'label' => $this->__('%d days', 14)),
			array('value' => 28 * 24 * 60, 'label' => $this->__('%d days', 28)),
			array('value' => 31 * 24 * 60, 'label' => $this->__('%d days', 31))
		);
	}
}