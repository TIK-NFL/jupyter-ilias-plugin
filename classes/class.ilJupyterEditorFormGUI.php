<?php
require_once('./Services/Form/classes/class.ilSubEnabledFormPropertyGUI.php');


class ilJupyterEditorFormGUI extends ilFormPropertyGUI
{
    protected bool $show_editor = false;
    protected $jupyterQuestion;
    private array $jupyter_user_credentials = array();

    function __construct($a_title, $a_postvar, $a_JupyterQuestion)
    {
        parent::__construct($a_title, $a_postvar);
        $this->setType("custom");
        $this->jupyterQuestion = $a_JupyterQuestion;
    }

    public function getJupyterUserCredentials(): array
    {
        return $this->jupyter_user_credentials;
    }

    public function setJupyterUserCredentials(array $jupyter_user_credentials): void
    {
        $this->jupyter_user_credentials = $jupyter_user_credentials;
    }

    public function showEditor($a_show_editor)
    {
        $this->show_editor = $a_show_editor;
    }

    function setValueByArray($a_values)
    {
    }

    function insert($a_tpl)
    {
        global $DIC;
        $tpl = $DIC->ui()->mainTemplate();
        $a_tpl->setCurrentBlock("prop_custom");
        $a_tpl->setVariable("CUSTOM_CONTENT", $this->getHtml());
        $a_tpl->parseCurrentBlock();
        $tpl->addJavaScript($this->jupyterQuestion->getPlugin()->getDirectory() . '/js/jupyter_init.js');
    }

    function getHtml()
    {
        $settings = ilJupyterSettings::getInstance();
        $applet = $this->jupyterQuestion->getPlugin()->getTemplate('tpl.jupyter_edit_frame.html', true, true);
        $applet->setVariable('IFRAME_SRC', $settings->getProxyUrl() . '/user/' . $this->jupyter_user_credentials['user'] . '/lab?token=' . $this->jupyter_user_credentials['token']);
        $applet->setVariable('PROXY_URL', $settings->getProxyUrl());
        $applet->setVariable("INFO", $this->jupyterQuestion->getPlugin()->txt('loading_time_warning'));
        return $applet->get();
    }

    function checkInput(): bool
    {
        global $DIC;

        if ($this->getPostVar()) {
            $postVar = ilUtil::stripSlashes($_POST[$this->getPostVar()] ?: '');
            if ($this->getRequired() && trim($postVar) == "") {
                $this->setAlert($DIC->language()->txt("msg_input_is_required"));
                return false;
            }
        }
        return true;
    }
}