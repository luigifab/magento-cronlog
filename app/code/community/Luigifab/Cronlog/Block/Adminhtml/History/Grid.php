<?php
/**
 * Created W/29/02/2012
 * Updated D/06/12/2015
 * Version 27
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

class Luigifab_Cronlog_Block_Adminhtml_History_Grid extends Mage_Adminhtml_Block_Widget_Grid {

	public function __construct() {

		parent::__construct();

		$this->setId('cronlog_grid');
		$this->setDefaultSort('schedule_id');
		$this->setDefaultDir('DESC');

		$this->setUseAjax(true);
		$this->setSaveParametersInSession(true);
		$this->setPagerVisibility(true);
		$this->setFilterVisibility(true);
		$this->setDefaultLimit(max($this->_defaultLimit, intval(Mage::getStoreConfig('cronlog/general/number'))));
	}

	protected function _prepareCollection() {

		$this->setCollection(Mage::getResourceModel('cron/schedule_collection'));
		return parent::_prepareCollection();
	}

	protected function _prepareColumns() {

		$this->addColumn('schedule_id', array(
			'header'    => $this->helper('adminhtml')->__('Id'),
			'index'     => 'schedule_id',
			'align'     => 'center',
			'width'     => '80px'
		));

		$this->addColumn('job_code', array(
			'header'    => $this->__('Job'),
			'index'     => 'job_code',
			'type'      => 'options',
			'align'     => 'center',
			'frame_callback' => array($this, 'decorateCode')
		));

		$this->addColumn('created_at', array(
			'header'    => $this->helper('cronlog')->_('Created At'),
			'index'     => 'created_at',
			'type'      => 'datetime',
			'align'     => 'center',
			'width'     => '180px',
			'frame_callback' => array($this, 'decorateDate')
		));

		$this->addColumn('scheduled_at', array(
			'header'    => $this->helper('cronlog')->_('Scheduled At'),
			'index'     => 'scheduled_at',
			'type'      => 'datetime',
			'align'     => 'center',
			'width'     => '180px',
			'frame_callback' => array($this, 'decorateDate')
		));

		$this->addColumn('executed_at', array(
			'header'    => $this->helper('cronlog')->_('Executed At'),
			'index'     => 'executed_at',
			'type'      => 'datetime',
			'align'     => 'center',
			'width'     => '180px',
			'frame_callback' => array($this, 'decorateDate')
		));

		$this->addColumn('finished_at', array(
			'header'    => $this->helper('cronlog')->_('Finished At'),
			'index'     => 'finished_at',
			'type'      => 'datetime',
			'align'     => 'center',
			'width'     => '180px',
			'frame_callback' => array($this, 'decorateDate')
		));

		$this->addColumn('duration', array(
			'header'    => $this->__('Duration'),
			'index'     => 'duration',
			'align'     => 'center',
			'width'     => '60px',
			'filter'    => false,
			'sortable'  => false,
			'frame_callback' => array($this, 'decorateDuration')
		));

		$this->addColumn('status', array(
			'header'    => $this->helper('adminhtml')->__('Status'),
			'index'     => 'status',
			'type'      => 'options',
			'options'   => array(
				'pending' => $this->__('Pending'),
				'running' => $this->__('Running'),
				'success' => $this->helper('cronlog')->_('Success'),
				'missed'  => $this->__('Missed'),
				'error'   => $this->helper('cronlog')->_('Error')
			),
			'align'     => 'status',
			'width'     => '125px',
			'frame_callback' => array($this, 'decorateStatus')
		));

		$this->addColumn('action', array(
			'type'      => 'action',
			'getter'    => 'getId',
			'actions'   => array(
				array(
					'caption' => $this->helper('adminhtml')->__('View'),
					'url'     => array('base' => '*/*/view'),
					'field'   => 'id'
				)
			),
			'align'     => 'center',
			'width'     => '55px',
			'filter'    => false,
			'sortable'  => false,
			'is_system' => true
		));

		// recherche des codes
		// efficacité maximale avec la PROCEDURE ANALYSE de MySQL
		$resource = Mage::getSingleton('core/resource');
		$read = $resource->getConnection('core_read');

		$codes = $read->fetchAll('SELECT job_code FROM '.$resource->getTableName('cron_schedule').' PROCEDURE ANALYSE();');
		$codes = (isset($codes[0]['Optimal_fieldtype'])) ?
			explode(',', str_replace(array('ENUM(', '\'', ') NOT NULL'), '', $codes[0]['Optimal_fieldtype'])) : array();

		$codes = array_combine($codes, $codes);
		ksort($codes);

		// mode texte ou mode liste déroulante
		// mode texte si configuré ou si la recherche n'est pas dans la liste déroulante, sinon mode liste
		$filter = $this->getParam($this->getVarNameFilter(), null);
		if (is_string($filter) || !empty($this->_defaultFilter))
			$filter = array_merge($this->_defaultFilter, $this->helper('adminhtml')->prepareFilterString($filter));

		if ((Mage::getStoreConfig('cronlog/general/textmode') === '1') || (isset($filter['job_code']) && !in_array($filter['job_code'], $codes))) {

			$this->addColumnAfter('job_code', array(
				'header'    => $this->__('Job'),
				'index'     => 'job_code',
				//'type'    => 'options',
				'align'     => 'center',
				'frame_callback' => array($this, 'decorateCode')
			), 'schedule_id');
		}
		else {
			$this->getColumn('job_code')->setData('options', $codes);
		}

		return parent::_prepareColumns();
	}


	public function getRowClass($row) {
		return '';
	}

	public function getRowUrl($row) {
		return $this->getUrl('*/*/view', array('id' => $row->getId()));
	}

	public function decorateStatus($value, $row, $column, $isExport) {
		return '<span class="grid-'.$row->getData('status').'">'.$value.'</span>';
	}

	public function decorateDuration($value, $row, $column, $isExport) {
		return $this->helper('cronlog')->getHumanDuration($row);
	}

	public function decorateDate($value, $row, $column, $isExport) {
		return (!in_array($row->getData($column->getIndex()), array('', '0000-00-00 00:00:00', null))) ? $value : '';
	}

	public function decorateCode($value, $row, $column, $isExport) {
		return $row->getData('job_code');
	}
}