<?php
require_once ('./Services/Form/classes/class.ilSubEnabledFormPropertyGUI.php');


class ilJupyterEditorFormGUI extends ilFormPropertyGUI
{
	protected $show_editor = false;
	
	protected $jupyterQuestion;

	function __construct($a_title, $a_postvar, $a_JupyterQuestion)
	{
		parent::__construct($a_title, $a_postvar);
		$this->setType("custom");
		$this->jupyterQuestion = $a_JupyterQuestion;
	}

	public function showEditor($a_show_editor)
	{
		$this->show_editor = $a_show_editor;
	}

	function setValueByArray($a_values)
	{
	}

	function getHtml()
	{
		$settings = ilJupyterSettings::getInstance();
		$applet = $this->jupyterQuestion->getPlugin()->getTemplate('tpl.jupyter_frame.html', TRUE, TRUE);
//        $applet->setVariable('JUPYTER_TEST', $this->getJupyterQuestion()->getJupyterExerciseId());
        $applet->setVariable('JUPYTER_TEST', 'test');
		return $applet->get();
	}

	function insert($a_tpl)
	{
		global $DIC;
		$tpl = $DIC->ui()->mainTemplate();
		$a_tpl->setCurrentBlock("prop_custom");
		$a_tpl->setVariable("CUSTOM_CONTENT", $this->getHtml());
		$a_tpl->parseCurrentBlock();
		$tpl->addJavaScript($this->jupyterQuestion->getPlugin()->getDirectory() . '/js/editor_init.js');
		$tpl->addOnLoadCode("ilJupyterInitEditor();");
	}

	function checkInput(): bool
	{
		global $DIC;
		
		if ($this->getPostVar())
		{
			$postVar = ilUtil::stripSlashes($_POST[$this->getPostVar()] ?: '');
			if ($this->getRequired() && trim($postVar) == "")
			{
				$this->setAlert($DIC->language()->txt("msg_input_is_required"));
				return false;
			}
		}
		return true;
	}
}