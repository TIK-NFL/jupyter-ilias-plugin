<?php
require_once ('./Services/Form/classes/class.ilSubEnabledFormPropertyGUI.php');


class ilJupyterEditorFormGUI extends ilFormPropertyGUI
{
	protected $show_editor = false;

    private $jupyter_user_credentials = array();

    /**
     * @return array
     */
    public function getJupyterUserCredentials(): array
    {
        return $this->jupyter_user_credentials;
    }

    /**
     * @param array $jupyter_user_credentials
     */
    public function setJupyterUserCredentials(array $jupyter_user_credentials): void
    {
        $this->jupyter_user_credentials = $jupyter_user_credentials;
    }
	
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
        $applet->setVariable('JUPYTER_TEST', 'test');
        $applet->setVariable(
            'IFRAME_SRC',
            'https://127.0.0.11/jupyter/user/' . $this->jupyter_user_credentials['user'] .
            '/notebooks/test.ipynb?token=' . $this->jupyter_user_credentials['token']
        );

		return $applet->get();
	}

	function insert($a_tpl)
	{
		global $DIC;
		$tpl = $DIC->ui()->mainTemplate();
		$a_tpl->setCurrentBlock("prop_custom");
		$a_tpl->setVariable("CUSTOM_CONTENT", $this->getHtml());
		$a_tpl->parseCurrentBlock();
		$tpl->addJavaScript($this->jupyterQuestion->getPlugin()->getDirectory() . '/js/jupyter_init.js');
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