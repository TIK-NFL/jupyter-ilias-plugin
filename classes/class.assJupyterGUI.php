<?php

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

use exceptions\ilCurlErrorCodeException;
use exceptions\JupyterTransferException;

include_once './Modules/TestQuestionPool/classes/class.assQuestionGUI.php';

/**
 * Question GUI for jupyter questions
 *
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 *
 * @ingroup ModulesTestQuestionPool
 * @ilctrl_iscalledby assJupyterGUI: ilObjQuestionPoolGUI, ilObjTestGUI, ilQuestionEditGUI, ilTestExpressPageObjectGUI
 *
 */
class assJupyterGUI extends assQuestionGUI
{
    public static string $DEFAULT_VIEW_MODE = 'classic';
    private ilJupyterRESTController $rest_ctrl;
    private ilJupyterIRSSController $resource_ctrl;
    private ilJupyterSettings $settings;


    public function __construct($a_id = -1)
    {
        parent::__construct($a_id);
        $this->object = new assJupyter();
        $this->newUnitId = null;
        $this->settings = ilJupyterSettings::getInstance();
        $this->rest_ctrl = new ilJupyterRESTController();
        $this->resource_ctrl = new ilJupyterIRSSController();

        if ($a_id >= 0) {
            $this->object->loadFromDb($a_id);
        }
    }

    public function setQuestionTabs(): void
    {
        global $ilAccess, $ilTabs;

        $this->ctrl->setParameterByClass("ilAssQuestionPageGUI", "q_id", $_GET["q_id"]);
        include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
        $q_type = $this->object->getQuestionType();

        if (strlen($q_type)) {
            $classname = $q_type . "GUI";
            $this->ctrl->setParameterByClass(strtolower($classname), "sel_question_types", $q_type);
            $this->ctrl->setParameterByClass(strtolower($classname), "q_id", $_GET["q_id"]);
        }

        if ($_GET["q_id"]) {
            if ($ilAccess->checkAccess('write', '', $_GET["ref_id"])) {
                // edit page
                $ilTabs->addTarget("edit_content", $this->ctrl->getLinkTargetByClass("ilAssQuestionPageGUI", "edit"), array("edit", "insert", "exec_pg"), "", "");
            }

            // preview page
            $ilTabs->addTarget("preview", $this->ctrl->getLinkTargetByClass("ilAssQuestionPageGUI", "preview"), array("preview"), "ilAssQuestionPageGUI", "");
        }

        $force_active = false;
        if ($ilAccess->checkAccess('write', '', $_GET["ref_id"])) {
            $url = "";
            if ($classname) {
                $url = $this->ctrl->getLinkTargetByClass($classname, "editQuestion");
            }
            $commands = $_POST["cmd"];
            if (is_array($commands)) {
                foreach ($commands as $key => $value) {
                    if (preg_match("/^suggestrange_.*/", $key, $matches)) {
                        $force_active = true;
                    }
                }
            }
            // edit question properties
            $ilTabs->addTarget("edit_properties", $url, array("editQuestion", "save", "cancel", "addSuggestedSolution", "cancelExplorer", "linkChilds", "removeSuggestedSolution", "parseQuestion", "saveEdit", "suggestRange"), $classname, "", $force_active);
        }

        // add tab for question feedback within common class assQuestionGUI
        $this->addTab_QuestionFeedback($ilTabs);

        // add tab for question hint within common class assQuestionGUI
        $this->addTab_QuestionHints($ilTabs);

        // Assessment of questions sub menu entry
        if ($_GET["q_id"]) {
            $ilTabs->addTarget("statistics", $this->ctrl->getLinkTargetByClass($classname, "assessment"), array("assessment"), $classname, "");
        }

        if (($_GET["calling_test"] > 0) || ($_GET["test_ref_id"] > 0)) {
            $ref_id = $_GET["calling_test"];
            if (strlen($ref_id) == 0) $ref_id = $_GET["test_ref_id"];
            $ilTabs->setBackTarget($this->lng->txt("backtocallingtest"), "ilias.php?baseClass=ilObjTestGUI&cmd=questions&ref_id=$ref_id");
        } else {
            $ilTabs->setBackTarget($this->lng->txt("qpl"), $this->ctrl->getLinkTargetByClass("ilobjquestionpoolgui", "questions"));
        }

    }

    protected function getPlugin()
    {
        return ilassJupyterPlugin::getInstance();
    }

    protected function getJupyterQuestion()
    {
        return $this->object;
    }

    protected function initEditQuestionForm()
    {
        global $lng;

        include_once("./Services/Form/classes/class.ilPropertyFormGUI.php");
        $form = new ilPropertyFormGUI();

        $form->setFormAction($this->ctrl->getFormAction($this));
        $form->setTitle($this->outQuestionType());
        $form->setMultipart(FALSE);
        $form->setTableWidth("100%");
        $form->setId("jupyterquestion");

        $this->addBasicQuestionFormProperties($form);

        // points
        $points = new ilNumberInputGUI($lng->txt("points"), "points");
        $points->setValue($this->object->getPoints());
        $points->setRequired(TRUE);
        $points->setSize(3);
        $points->setMinValue(0.0);
        $form->addItem($points);

        // view mode
        $viewMode = new ilRadioGroupInputGUI($this->getPlugin()->txt('jupyter_view_mode'), 'jupyter_view_mode');
        $viewModeOptionClassic = new ilRadioOption($this->getPlugin()->txt('jupyter_view_mode_option_classic'), 'classic');
        $viewModeOptionLab = new ilRadioOption($this->getPlugin()->txt('jupyter_view_mode_option_lab'), 'lab');
        $viewMode->addOption($viewModeOptionClassic);
        $viewMode->addOption($viewModeOptionLab);
        $viewMode->setValue($this->object->getJupyterViewMode() ?: self::$DEFAULT_VIEW_MODE);
        $form->addItem($viewMode);

        if ($this->object->getId()) {
            $hidden = new ilHiddenInputGUI("jupyter_question_id");
            $hidden->setValue($this->object->getId());
            $form->addItem($hidden);
        }

        // add jupyter session id
        $hidden_jupyter_session = new ilHiddenInputGUI('jupyter_session_id');
        $hidden_jupyter_session->setValue($this->getJupyterQuestion()->getJupyterUser());
        $form->addItem($hidden_jupyter_session);


        #$this->addQuestionFormCommandButtons($form);
        $form->addCommandButton("save", $this->lng->txt("save"));

        $jupyter_form_frame = new ilJupyterEditorFormGUI($this->getPlugin()->txt('editor'), 'editor', $this->getJupyterQuestion());
        $jupyter_session_id = $this->object->getJupyterUser();
        if ($jupyter_session_id) {
            $jupyter_session = new ilJupyterSession($jupyter_session_id);
            $jupyter_form_frame->setJupyterUserCredentials($jupyter_session->getUserCredentials());
        }

        $form->addItem($jupyter_form_frame);

        return $form;
    }


    /**
     * Show edit question form
     *
     * @param ilPropertyFormGUI $form
     * @throws Exception
     */
    protected function editQuestion(ilPropertyFormGUI $form = null)
    {
        $this->getQuestionTemplate();
        $this->object->synchronizeJupyterSession();

        if (!$form instanceof ilPropertyFormGUI) {
            $form = $this->initEditQuestionForm();

        }
        $this->tpl->setVariable("QUESTION_DATA", $form->getHTML());
        $this->tpl->setOnScreenMessage("info", $this->getPlugin()->txt('non_default_resources_ignored_note'));
    }

    public function save(): void
    {
        $form = $this->initEditQuestionForm();
        if ($form->checkInput()) {
            $this->writeJupyterQuestionFromForm($form);
            parent::save();
        } else {
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt('err_check_input'));
            $form->setValuesByPost();
            $this->editQuestion($form);
        }
    }

    public function saveReturn(): void
    {
        $form = $this->initEditQuestionForm();
        if ($form->checkInput()) {
            $this->writeJupyterQuestionFromForm($form);
            parent::saveReturn();
        } else {
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt('err_check_input'));
            $form->setValuesByPost();
            $this->editQuestion($form);
        }
    }

    /**
     * Set the jupyter question attributes to the input of the form.
     *
     * @param ilPropertyFormGUI $form
     * @return true
     * @throws JsonException
     * @throws ilCurlErrorCodeException
     * @throws ilCurlConnectionException
     */
    public function writeJupyterQuestionFromForm(ilPropertyFormGUI $form)
    {
        $jupyterQuestion = $this->getJupyterQuestion();
        $jupyterQuestion->setTitle($form->getInput('title'));
        $jupyterQuestion->setComment($form->getInput('comment'));
        $jupyterQuestion->setAuthor($form->getInput('author'));
        $jupyterQuestion->setQuestion($form->getInput('question'));
        $jupyterQuestion->setPoints($form->getInput('points'));
        $jupyterQuestion->setId($form->getInput('jupyter_question_id'));
        $jupyterQuestion->setJupyterViewMode($form->getInput('jupyter_view_mode'));

        $jupyter_session_id = $form->getInput('jupyter_session_id');
        $jupyterQuestion->setJupyterUser($jupyter_session_id);

        $jupyter_session = new ilJupyterSession($jupyter_session_id);
        $user_credentials = $jupyter_session->getUserCredentials();
        $jupyterQuestion->setJupyterToken($user_credentials['token']);

        // If existent, clean up previously saved jupyter resource.
        $old_res_id = $jupyterQuestion->getJupyterExerciseResourceId();
        if ($old_res_id) {
            $this->resource_ctrl->deleteJupyterResource($old_res_id);
        }

        // When the notebook is deleted on jupyterhub while editing, ilCurlErrorCodeException (404) will be thrown.
        // This means, that the jupyterhub session was cleaned up before the ILIAS session was closed,
        // which should by session length definition never be the case.
        $jupyter_notebook_json = $this->rest_ctrl->pullJupyterNotebook($user_credentials['user'], $user_credentials['token']);
        $res_id = $this->resource_ctrl->storeJupyterResource(
            $jupyter_notebook_json,
            ilJupyterIRSSController::JUPYTER_QUESTION_RESOURCE
        );
        $jupyterQuestion->setJupyterExerciseResourceId($res_id);

        return true;
    }

    public function writePostData($always = false): int
    {
        return 0;
    }

    public function getPreview($a_show_question_only = FALSE, $showInlineFeedback = FALSE)
    {
        global $DIC;
        $tpl = $DIC->ui()->mainTemplate();
        $this->object->pushLocalJupyterNotebook();

        include_once './Services/UICore/classes/class.ilTemplate.php';
        $template = $this->getPlugin()->getTemplate('tpl.jupyter_frame.html');
        $template->setVariable('QUESTION_TEXT', $this->object->getQuestion());
        $template->setVariable('IFRAME_SRC', $this->settings->getProxyUrl() . '/user/' . $this->object->getJupyterUser() .
            $this->getViewModeDependentPathSegment() . '/default.ipynb?token=' . $this->object->getJupyterToken());
        $preview = $template->get();
        $preview = !$a_show_question_only ? $this->getILIASPage($preview) : $preview;
        return $preview;
    }

    public function getTestOutput($active_id, $pass, $is_question_postponed, $user_post_solutions, $show_specific_inline_feedback)
    {
//		$settings = ilJupyterSettings::getInstance();
//		ilLoggerFactory::getLogger('jupyter')->debug('JupyterCookie: '. $this->getJupyterQuestion()->getJupyterCookie());
//		$atpl->setVariable('VIP_STORED_EXERCISE', $this->getJupyterQuestion()->getJupyterExerciseId());

        if ($active_id) {
            $solutions = $this->object->getTestOutputSolutions($active_id, $pass);
            foreach ($solutions as $idx => $solution_value) {
                $solution_res_id = $solution_value["value2"];
                $this->object->setJupyterExerciseResourceId($solution_res_id);
            }
        }

        $atpl = $this->getPlugin()->getTemplate('tpl.jupyter_frame_form.html');
        $atpl->setVariable('QUESTION_TEXT', $this->object->getQuestion());

        try {
            global $DIC;
            $this->object->pushLocalJupyterNotebook();
            $atpl->setVariable('JUPYTER_USER', $this->object->getJupyterUser());
            $atpl->setVariable('IFRAME_SRC', $this->settings->getProxyUrl() . '/user/' . $this->object->getJupyterUser() .
                $this->getViewModeDependentPathSegment() . '/default.ipynb?token=' . $this->object->getJupyterToken());
            $DIC->ui()->mainTemplate()->addJavaScript($this->object->getPlugin()->getDirectory() . '/js/jupyter_init.js');
        } catch (JupyterTransferException | ilCurlErrorCodeException | ilCurlConnectionException $e) {
            ilLoggerFactory::getLogger('jupyter')->error($e->getMessage());
            $this->tpl->setOnScreenMessage('failure', $this->getPlugin()->txt('local_data_push_failed'));
        }

        return $this->outQuestionPage("", $is_question_postponed, $active_id, $atpl->get());
    }


    public function getSolutionOutput($active_id, $pass = NULL, $graphicalOutput = FALSE, $result_output = FALSE, $show_question_only = TRUE, $show_feedback = FALSE, $show_correct_solution = FALSE, $show_manual_scoring = FALSE, $show_question_text = TRUE): string
    {
        if ($show_correct_solution) {
            return $this->getGenericFeedbackOutputForCorrectSolution();
        }

        // In case that the active_id is an empty string...
        // E.g., passed by Modules/Test/classes/class.ilObjTestGUI.php:printobject() for print views.
        $active_id = $active_id ?: 0;

        // In case of feedbacks, post reviews, etc. show the readonly editor to the student.
        // Otherwise, use the solution output for teachers containing more options.
        switch ($this->ctrl->getCmd()) {
            case 'show':
            case 'outCorrectSolution':
            case 'outUserPassDetails':
            case 'outUserListOfAnswerPasses':
                // student template
                $soltpl = $this->getPlugin()->getTemplate('tpl.jupyter_frame.html');
                break;
            default:
                // teacher template
                $soltpl = $this->getPlugin()->getTemplate('tpl.jupyter_frame.html');
        }

        if ($active_id > 0) {
            $solutions = $this->object->getSolutionValues($active_id, $pass);
            foreach ($solutions as $idx => $solution_value) {
                $solution_res_id = $solution_value["value2"];
                $this->object->setJupyterExerciseResourceId($solution_res_id);
                // TODO: A jupyter session might be reused to improve efficiency, since larger notebooks are repeatedly pushed to jupyterhub on every solution review.
            }
        }

        $this->object->pushLocalJupyterNotebook();
        $soltpl->setVariable('IFRAME_SRC', $this->settings->getProxyUrl() . '/user/' . $this->object->getJupyterUser() .
            $this->getViewModeDependentPathSegment() . '/default.ipynb?token=' . $this->object->getJupyterToken());

        $qst_txt = $soltpl->get();
        $solutiontemplate = new ilTemplate("tpl.il_as_tst_solution_output.html", TRUE, TRUE, "Modules/TestQuestionPool");
        $solutiontemplate->setVariable("SOLUTION_OUTPUT", $qst_txt);
        $solutionoutput = $solutiontemplate->get();
        if (!$show_question_only) {
            // get page object output
            $solutionoutput = $this->getILIASPage($solutionoutput);
        }

        return $solutionoutput;
    }

    private function getViewModeDependentPathSegment(): string
    {
        return $this->object->getJupyterViewMode() == 'lab' ? '/lab/tree' : '/notebooks';
    }

    public function getSpecificFeedbackOutput($userSolution): string
    {
        return "";
    }

}