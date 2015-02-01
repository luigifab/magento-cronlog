<?php
/**
 * Created J/17/05/2012
 * Updated S/29/11/2014
 * Version 26
 *
 * Copyright 2012-2015 | Fabrice Creuzot (luigifab) <code~luigifab~info>
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

class Luigifab_Cronlog_Model_Observer extends Luigifab_Cronlog_Helper_Data {

	public function sendMail() {

		Mage::getSingleton('core/translate')->setLocale(Mage::getStoreConfig('general/locale/code'))->init('adminhtml', true);
		$frequency = Mage::getStoreConfig('cronlog/email/frequency');

		// chargement des tâches cron de la période
		// le mois dernier (mensuel/monthly), les septs derniers jour (hebdomadaire/weekly), hier (quotidien/daily)
		$dateStart = Mage::app()->getLocale()->date();
		$dateStart->setTimezone(Mage::getStoreConfig('general/locale/timezone'));
		$dateStart->setHour(0);
		$dateStart->setMinute(0);
		$dateStart->setSecond(0);

		$dateEnd = Mage::app()->getLocale()->date();
		$dateEnd->setTimezone(Mage::getStoreConfig('general/locale/timezone'));
		$dateEnd->setHour(23);
		$dateEnd->setMinute(59);
		$dateEnd->setSecond(59);

		if ($frequency === Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_MONTHLY) {
			$frequency = $this->__('monthly');
			$dateStart->subMonth(1)->setDay(1);
			$dateEnd->subMonth(1)->setDay(1);
			$dateEnd->setDay(date('t', $dateEnd->getTimestamp()));
		}
		else if ($frequency === Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_WEEKLY) {
			$frequency = $this->__('weekly');
			$dateStart->subDay(7);
			$dateEnd->subDay(1);
		}
		else {
			$frequency = $this->__('daily');
			$dateStart->subDay(1);
			$dateEnd->subDay(1);
		}

		// chargement des tâches cron
		// génération du code HTML du détails des erreurs
		$jobs = Mage::getResourceModel('cron/schedule_collection');
		$jobs->getSelect()->order('schedule_id', 'DESC');
		$jobs->addFieldToFilter('created_at', array(
			'datetime' => true, 'from' => $dateStart->toString(Zend_Date::RFC_3339), 'to' => $dateEnd->toString(Zend_Date::RFC_3339)));

		$date = Mage::getSingleton('core/locale');
		$errors = array();

		foreach ($jobs as $job) {

			if (!in_array($job->getStatus(), array('error', 'missed')))
				continue;

			$link  = str_replace('//admin', '/admin', '<a href="'.$this->getUrl('adminhtml/cronlog_history/view', array('id' => $job->getId())).'" style="font-weight:bold; color:red; text-decoration:none;">'.$this->__('Job %d: %s', $job->getId(), $job->getJobCode()).'</a>');

			$hour  = $this->__('Scheduled At: %s', $date->date($job->getScheduledAt(), Zend_Date::ISO_8601));
			$state = $this->__('Status: %s (%s)', $this->__(ucfirst($job->getStatus())), $job->getStatus());
			$error = '<pre style="margin:0.5em; font-size:0.9em; color:gray; white-space:pre-wrap;">'.$job->getMessages().'</pre>';

			array_push($errors, sprintf('(%d) %s / %s / %s %s', count($errors) + 1, $link, $hour, $state, $error));
		}

		// envoie des emails
		// avec les variables du template
		$this->send(array(
			'frequency'        => $frequency,
			'date_period_from' => $date->date($dateStart)->toString(Zend_Date::DATETIME_FULL),
			'date_period_to'   => $date->date($dateEnd)->toString(Zend_Date::DATETIME_FULL),
			'total_cron'    => count($jobs),
			'total_pending' => count($jobs->getItemsByColumnValue('status', 'pending')),
			'total_running' => count($jobs->getItemsByColumnValue('status', 'running')),
			'total_success' => count($jobs->getItemsByColumnValue('status', 'success')),
			'total_missed'  => count($jobs->getItemsByColumnValue('status', 'missed')),
			'total_error'   => count($jobs->getItemsByColumnValue('status', 'error')),
			'list'   => (count($errors) > 0) ? implode('</li><li style="margin:0.8em 0 0.5em;">', $errors) : '',
			'config' => str_replace('//admin', '/admin', $this->getUrl('adminhtml/system_config/edit', array('section' => 'cronlog')))
		));
	}

	public function updateConfig() {

		try {
			$config = Mage::getModel('core/config_data');
			$config->load('crontab/jobs/cronlog_send_report/schedule/cron_expr', 'path');

			if (Mage::getStoreConfig('cronlog/email/enabled') === '1') {

				// quotidien, tous les jours à 1h00 (quotidien/daily)
				// hebdomadaire, tous les lundi à 1h00 (hebdomadaire/weekly)
				// mensuel, chaque premier jour du mois à 1h00 (mensuel/monthly)
				$frequency = Mage::getStoreConfig('cronlog/email/frequency');

				// minute hour day-of-month month-of-year day-of-week (Dimanche = 0, Lundi = 1...)
				// 0	     1    1            *             *           => monthly
				// 0	     1    *            *             0|1         => weekly
				// 0	     1    *            *             *           => daily
				if ($frequency === Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_MONTHLY)
					$config->setValue('0 1 1 * *');
				else if ($frequency === Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_WEEKLY)
					$config->setValue('0 1 * * '.Mage::getStoreConfig('general/locale/firstday'));
				else
					$config->setValue('0 1 * * *');

				$config->setPath('crontab/jobs/cronlog_send_report/schedule/cron_expr');
				$config->save();

				// email de test
				$this->sendMail();
			}
			else {
				$config->delete();
			}
		}
		catch (Exception $e) {
			Mage::throwException($e->getMessage());
		}
	}

	private function send($vars) {

		$emails = explode(' ', trim(Mage::getStoreConfig('cronlog/email/recipient_email')));

		foreach ($emails as $email) {

			// sendTransactional($templateId, $sender, $recipient, $name, $vars = array(), $storeId = null)
			$template = Mage::getModel('core/email_template');
			$template->sendTransactional(
				Mage::getStoreConfig('cronlog/email/template'),
				Mage::getStoreConfig('cronlog/email/sender_email_identity'),
				trim($email), null, $vars
			);

			if (!$template->getSentSuccess())
				Mage::throwException($this->__('Can not send email report to %s.', $email));

			//exit($template->getProcessedTemplate($vars));
		}
	}
}