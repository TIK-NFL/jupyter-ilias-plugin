<?php

use exceptions\ilCurlErrorCodeException;

include_once "./Modules/TestQuestionPool/classes/class.ilQuestionsPlugin.php";

class ilassJupyterPlugin extends ilQuestionsPlugin
{
    const CNAME = 'TestQuestionPool';
    const PNAME = 'assJupyter';

    const QUESTION_TYPE = "assJupyter";

    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance) {
            return self::$instance;
        }

        global $DIC;

        $component_factory = $DIC['component.factory'];
        $instance = $component_factory->getPlugin('assjupyter');

        return self::$instance = $instance;
    }

    public function getPluginName(): string
    {
        return self::PNAME;
    }

    public function getQuestionType()
    {
        return ilassJupyterPlugin::QUESTION_TYPE;
    }

    public function getQuestionTypeTranslation(): string
    {
        return $this->txt('jupyter_qst_type');
    }

    protected function init(): void
    {
        $this->initAutoLoad();
    }

    protected function initAutoLoad()
    {
        spl_autoload_register(array($this, 'autoLoad'));
    }

    private final function autoLoad($a_classname)
    {
        $class_file = $this->getClassesDirectory() . '/class.' . $a_classname . '.php';
        if (@include_once($class_file)) {
            return;
        }
    }

    protected function getClassesDirectory(): string
    {
        return $this->getDirectory() . "/classes";
    }

    public function includeClass($a_class_file_name)
    {
        include_once($this->getClassesDirectory() . "/" . $a_class_file_name);
    }

    public function handleCronJob() {
        $jupyter = new assJupyter();
        $jupyter->cleanUpUnreferencedSolutionResources();
    }
}