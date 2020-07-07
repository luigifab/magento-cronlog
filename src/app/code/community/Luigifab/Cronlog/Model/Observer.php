<?php
/**
 * Created J/17/05/2012
 * Updated J/23/01/2020
 *
 * Copyright 2012-2020 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * https://www.luigifab.fr/openmage/cronlog
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

	// EVENT admin_system_config_changed_section_cronlog (adminhtml)
	public function updateConfig() {

		$config = Mage::getModel('core/config_data');
		$config->load('crontab/jobs/cronlog_send_report/schedule/cron_expr', 'path');

		if (Mage::getStoreConfigFlag('cronlog/email/enabled')) {

			// quotidien, tous les jours à 1h00 (quotidien/daily)
			// hebdomadaire, tous les lundi à 1h00 (hebdomadaire/weekly)
			// mensuel, chaque premier jour du mois à 1h00 (mensuel/monthly)
			$frequency = Mage::getStoreConfig('cronlog/email/frequency');

			// minute hour day-of-month month-of-year day-of-week (Dimanche = 0, Lundi = 1...)
			// 0      1    1            *             *           => monthly
			// 0      1    *            *             0|1         => weekly
			// 0      1    *            *             *           => daily
			if ($frequency == Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_MONTHLY)
				$config->setData('value', '0 1 1 * *');
			else if ($frequency == Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_WEEKLY)
				$config->setData('value', '0 1 * * '.Mage::getStoreConfig('general/locale/firstday'));
			else
				$config->setData('value', '0 1 * * *');

			$config->setData('path', 'crontab/jobs/cronlog_send_report/schedule/cron_expr');
			$config->save();

			// email de test
			if (!empty(Mage::app()->getRequest()->getPost('cronlog_email_test')))
				$this->sendEmailReport();
		}
		else {
			$config->delete();
		}

		Mage::getConfig()->reinit(); // très important
	}


	// CRON cronlog_send_report
	public function sendEmailReport($cron = null) {

		$oldLocale = Mage::getSingleton('core/translate')->getLocale();
		$newLocale = Mage::app()->getStore()->isAdmin() ? $oldLocale : Mage::getStoreConfig('general/locale/code');
		Mage::getSingleton('core/translate')->setLocale($newLocale)->init('adminhtml', true);

		$errors    = [];
		$frequency = Mage::getStoreConfig('cronlog/email/frequency');
		$dateStart = Mage::getSingleton('core/locale')->date()->setHour(0)->setMinute(0)->setSecond(0);
		$dateEnd   = Mage::getSingleton('core/locale')->date()->setHour(23)->setMinute(59)->setSecond(59);

		// de 1 (pour Lundi) à 7 (pour Dimanche)
		// permet d'obtenir des semaines du lundi au dimanche
		$day = $dateStart->toString(Zend_Date::WEEKDAY_8601) - 1;

		if ($frequency == Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_MONTHLY) {
			$frequency = $this->_('monthly');
			$dateEnd->subMonth(1)->setDay($dateEnd->toString(Zend_Date::MONTH_DAYS));
			$dateStart->subMonth(1)->setMonth($dateEnd->getMonth())->setDay(1);
		}
		else if ($frequency == Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_WEEKLY) {
			$frequency = $this->_('weekly');
			$dateStart->subDay($day + 7);
			$dateEnd->subDay($day + 1);
		}
		else {
			$frequency = $this->_('daily');
			$dateStart->subDay(1);
			$dateEnd->subDay(1);
		}

		// chargement des tâches cron
		$jobs = Mage::getResourceModel('cron/schedule_collection');
		$jobs->addFieldToFilter('created_at', [
			'datetime' => true,
			'from' => $dateStart->toString(Zend_Date::RFC_3339),
			'to'   => $dateEnd->toString(Zend_Date::RFC_3339)
		]);
		$jobs->setOrder('schedule_id', 'desc');

		foreach ($jobs as $job) {

			if (!in_array($job->getData('status'), ['error', 'missed']))
				continue;

			$link = '<a href="'.$this->getEmailUrl('adminhtml/cronlog_history/view', ['id' => $job->getId()]).'" style="font-weight:700; color:#E41101; text-decoration:none;">'.$this->__('Job %d: %s', $job->getId(), $job->getData('job_code')).'</a>';

			$hour = $this->_('Scheduled At: %s', $this->formatDate($job->getData('scheduled_at')));
			$state = $this->__('Status: %s (%s)', $this->__(ucfirst($job->getData('status'))), $job->getData('status'));
			$error = '<pre lang="mul" style="margin:0.5em; font-size:0.9em; color:#767676; white-space:pre-wrap;">'.$job->getMessages().'</pre>';

			$errors[] = sprintf('(%d) %s / %s / %s %s', count($errors) + 1, $link, $hour, $state, $error);
		}

		// envoi des emails
		$this->sendReportToRecipients($newLocale, [
			'frequency'        => $frequency,
			'date_period_from' => $dateStart->toString(Zend_Date::DATETIME_FULL),
			'date_period_to'   => $dateEnd->toString(Zend_Date::DATETIME_FULL),
			'total_cron'       => count($jobs),
			'total_pending'    => count($jobs->getItemsByColumnValue('status', 'pending')),
			'total_running'    => count($jobs->getItemsByColumnValue('status', 'running')),
			'total_success'    => count($jobs->getItemsByColumnValue('status', 'success')),
			'total_missed'     => count($jobs->getItemsByColumnValue('status', 'missed')),
			'total_error'      => count($jobs->getItemsByColumnValue('status', 'error')),
			'list'             => implode('</li><li style="margin:0.8em 0 0.5em;">', $errors)
		]);

		if ($newLocale != $oldLocale)
			Mage::getSingleton('core/translate')->setLocale($oldLocale)->init('adminhtml', true);
	}

	private function getEmailUrl(string $url, array $params = []) {

		if (Mage::getStoreConfigFlag(Mage_Core_Model_Store::XML_PATH_USE_REWRITES))
			return preg_replace('#/[^/]+\.php\d*/#', '/', Mage::helper('adminhtml')->getUrl($url, $params));
		else
			return preg_replace('#/[^/]+\.php(\d*)/#', '/index.php$1/', Mage::helper('adminhtml')->getUrl($url, $params));
	}

	private function sendReportToRecipients(string $locale, array $vars = []) {

		$emails = array_filter(preg_split('#\s+#', Mage::getStoreConfig('cronlog/email/recipient_email')));
		$vars['config'] = $this->getEmailUrl('adminhtml/system/config');
		$vars['config'] = mb_substr($vars['config'], 0, mb_strripos($vars['config'], '/system/config'));

		foreach ($emails as $email) {

			if (!in_array($email, ['hello@example.org', 'hello@example.com', ''])) {

				$sender   = Mage::getStoreConfig('cronlog/email/sender_email_identity');
				$template = Mage::getModel('core/email_template');

				$template->setSentSuccess(false);
				$template->setDesignConfig(['store' => null]);
				$template->loadDefault('cronlog_email_template', $locale);
				$template->setSenderName(Mage::getStoreConfig('trans_email/ident_'.$sender.'/name'));
				$template->setSenderEmail(Mage::getStoreConfig('trans_email/ident_'.$sender.'/email'));
				$template->setSentSuccess($template->send($email, null, $vars));

				if (!$template->getSentSuccess())
					Mage::throwException($this->__('Can not send the report by email to %s.', $email));

				//exit($template->getProcessedTemplate($vars));
			}
		}
	}
}