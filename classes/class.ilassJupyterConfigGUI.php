<?php

include_once './Services/Component/classes/class.ilPluginConfigGUI.php';

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Jupyter config GUI
 *
 * @ilctrl_iscalledby ilassJupyterConfigGUI: ilObjComponentSettingsGUI
 */
class ilassJupyterConfigGUI extends ilPluginConfigGUI
{

	public ilGlobalPageTemplate $tpl;

	public function __construct()
	{
		global $DIC;
		$this->tpl = $DIC['tpl'];
	}

	/**
	 * Handles all commmands, default is "configure"
	 */
	public function performCommand($cmd): void
	{
		global $ilTabs;
		
		$ilTabs->addTab('tab_settings', ilassJupyterPlugin::getInstance()->txt('tab_settings'), $GLOBALS['ilCtrl']->getLinkTarget($this, 'configure'));
		
		switch ($cmd)
		{
			case 'configure':
			case 'save':
				$this->$cmd();
				break;
		
		}
	}

	/**
	 * Configure plugin
	 */
	protected function configure(ilPropertyFormGUI $form = null)
	{
		$GLOBALS['ilTabs']->activateTab('tab_settings');
		
		if (!$form instanceof ilPropertyFormGUI)
		{
			$form = $this->initConfigurationForm();
		}
		$GLOBALS['tpl']->setContent($form->getHTML());
	}

	/**
	 * Init configuration form
	 *
	 * @return ilPropertyFormGUI
	 */
	protected function initConfigurationForm()
	{
		$this->getPluginObject()->includeClass('class.ilJupyterSettings.php');
		$settings = ilJupyterSettings::getInstance();
		
		include_once './Services/Form/classes/class.ilPropertyFormGUI.php';
		$form = new ilPropertyFormGUI();
		$form->setFormAction($GLOBALS['ilCtrl']->getFormAction($this));
		$form->setTitle($this->getPluginObject()->txt('form_tab_settings'));
		
		// log level
		$GLOBALS['lng']->loadLanguageModule('log');
		$level = new ilSelectInputGUI($this->getPluginObject()->txt('form_tab_settings_loglevel'), 'log_level');
		$level->setOptions(ilLogLevel::getLevelOptions());
		$level->setValue($settings->getLogLevel());
		$form->addItem($level);
		
		$form->addCommandButton('save', $GLOBALS['lng']->txt('save'));
		return $form;
	}

	/**
	 * Save settings
	 */
	protected function save()
	{
		$form = $this->initConfigurationForm();
		if ($form->checkInput() or 1)
		{
			$this->getPluginObject()->includeClass('class.ilJupyterSettings.php');
			$settings = ilJupyterSettings::getInstance();
			
			$settings->setLogLevel($form->getInput('log_level'));
			$settings->update();

			$this->tpl->setOnScreenMessage('success', $GLOBALS['lng']->txt('settings_saved'), true);
			$GLOBALS['ilCtrl']->redirect($this, 'configure');
			return TRUE;
		}
		
		$this->tpl->setOnScreenMessage('failure', $GLOBALS['lng']->txt('err_check_input'), true);
		$GLOBALS['ilCtrl']->redirect($this, 'configure');
		return TRUE;
	}
}