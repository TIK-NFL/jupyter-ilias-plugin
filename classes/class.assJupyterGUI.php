<?php

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

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
    private ilJupyterRESTController $rest_ctrl;


    public function __construct($a_id = -1)
    {
        parent::__construct($a_id);
        $this->object = new assJupyter();
        $this->newUnitId = null;

        $this->rest_ctrl = new ilJupyterRESTController();

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
                $ilTabs->addTarget("edit_content",
                    $this->ctrl->getLinkTargetByClass("ilAssQuestionPageGUI", "edit"),
                    array("edit", "insert", "exec_pg"),
                    "", "");
            }

            // preview page
            $ilTabs->addTarget("preview",
                $this->ctrl->getLinkTargetByClass("ilAssQuestionPageGUI", "preview"),
                array("preview"),
                "ilAssQuestionPageGUI", "");
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
            $ilTabs->addTarget("edit_properties",
                $url,
                array("editQuestion", "save", "cancel", "addSuggestedSolution",
                    "cancelExplorer", "linkChilds", "removeSuggestedSolution",
                    "parseQuestion", "saveEdit", "suggestRange"),
                $classname, "", $force_active);
        }

        // add tab for question feedback within common class assQuestionGUI
        $this->addTab_QuestionFeedback($ilTabs);

        // add tab for question hint within common class assQuestionGUI
        $this->addTab_QuestionHints($ilTabs);

        // Assessment of questions sub menu entry
        if ($_GET["q_id"]) {
            $ilTabs->addTarget("statistics",
                $this->ctrl->getLinkTargetByClass($classname, "assessment"),
                array("assessment"),
                $classname, "");
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

        $lang = new ilSelectInputGUI($this->getPlugin()->txt('editor_lang'), 'language');
        $lang->setInfo($this->getPlugin()->txt('prog_lang_info'));
        $lang->setValue($this->object->getJupyterLang());
        $options[''] = $this->lng->txt('select_one');
        $lang->setOptions($options);
        $lang->setRequired(false);
        $lang->setDisabled($this->getJupyterQuestion()->getJupyterSubId());
        $form->addItem($lang);

        // points
        $points = new ilNumberInputGUI($lng->txt("points"), "points");
        $p = $this->object->getPoints();
        $points->setValue($this->object->getPoints());
        $points->setRequired(TRUE);
        $points->setSize(3);
        $points->setMinValue(0.0);
        $form->addItem($points);

        // results
        $results = new ilCheckboxInputGUI($this->getPlugin()->txt('store_results'), 'result_storing');
        $results->setInfo($this->getPlugin()->txt('store_results_info'));
        $results->setValue(1);
        $results->setChecked($this->getJupyterQuestion()->getJupyterResultStorage());
        $form->addItem($results);

        $scoring = new ilCheckboxInputGUI($this->getPlugin()->txt('auto_scoring'), 'auto_scoring');
        $scoring->setInfo($this->getPlugin()->txt('auto_scoring_info'));
        $scoring->setValue(1);
        $scoring->setChecked($this->getJupyterQuestion()->getJupyterAutoScoring());
        $form->addItem($scoring);


        if ($this->object->getId()) {
            $hidden = new ilHiddenInputGUI("", "ID");
            $hidden->setValue($this->object->getId());
            $form->addItem($hidden);
        }

        // add hidden exercise
        $hidden_exc = new ilHiddenInputGUI('jupyterexercise');
        $hidden_exc->setValue($this->getJupyterQuestion()->getJupyterExercise());
        $form->addItem($hidden_exc);

        // add evaluation
        $hidden_eval = new ilHiddenInputGUI('jupyterevaluation');
        $hidden_eval->setValue($this->getJupyterQuestion()->getJupyterEvaluation());
        $form->addItem($hidden_eval);

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
     * Create a new solution on ecs for the client, using data from ilias database.
     *
     * @param
     *            int active_id the id of the test
     * @param
     *            int pass
     * @param
     *            bool force solution generation even for empty solutions
     * @return int
     */
    protected function createSolution($a_active_id, $a_pass = null, $a_force_empty_solution = true)
    {
        $sol_arr = $this->getJupyterQuestion()->getUserSolutionPreferingIntermediate($a_active_id, $a_pass);

        include_once "./Modules/Test/classes/class.ilObjTest.php";

        // Replaces the former static call ilObjTest::_getUsePreviousAnswers.
        $tmpObjTest = new ilObjTest();

        if ($tmpObjTest->isPreviousSolutionReuseEnabled($a_active_id) && count($sol_arr) == 0) {
            $a_pass = $a_pass ? $a_pass - 1 : $a_pass;
            $sol_arr = $this->getJupyterQuestion()->getSolutionValues($a_active_id, $a_pass, true);
        }

        ilLoggerFactory::getLogger('jupyter')->debug(print_r($sol_arr, true));

        $sol = (string)$sol_arr[0]['value2'];

        if (strlen($sol) || $a_force_empty_solution) {
            // create the solution on ecs
            return $this->getJupyterQuestion()->createSolution($sol);
        }
        return 0;
    }





    /**
     * Create exercise
     */
    protected function createExercise()
    {
        $this->getJupyterQuestion()->createExercise();
    }


    /**
     * Show edit question form
     * @param ilPropertyFormGUI $form
     * @throws Exception
     */
    protected function editQuestion(ilPropertyFormGUI $form = null)
    {
        $this->getQuestionTemplate();

        $jupyter_notebook_json = $this->object->getJupyterExercise();
        $jupyter_user = $this->object->getJupyterUser();
        $jupyter_token = $this->object->getJupyterToken();


        // Cases: (1) Initialization (2) jupyter_user from DB
        // Question to be created
        // TODO: Obtain a default Jupyter notebook
        if ($jupyter_user && ilJupyterSession::isSessionSet($jupyter_user)) {
            // A jupyter session was set before and is still active. Thus, use the existing jupyter-notebook from jupyterhub.
            // TODO: Use existing jupyter-notebook from jupyterhub session OR push the notebook read from ILIAS DB.
            // TODO: consider expiration
            // TODO: If the jupyter-notebook is not available on jupyterhub, push from local database
            $jupyter_session = new ilJupyterSession($jupyter_user);
            $jupyter_user_credentials = $jupyter_session->getUserCredentials();

        } else if ($jupyter_user && !ilJupyterSession::isSessionSet($jupyter_user)) {
            // A jupyter session was set before and is no longer active.

            // Create session from local (ILIAS DB saved) credentials and pull the jupyter-notebook from jupyterhub.
            $jupyter_session = ilJupyterSession::fromCredentials(array('user' => $jupyter_user, 'token' => $jupyter_token));
            $jupyter_user_credentials = $jupyter_session->getUserCredentials();


            // TODO: Test the jupyter-notebook presence on jupyterhub!
            // On failure:
            // The notebook is not present on Jupyterhub. Create a new session and push the local (ILIAS DB saved) notebook.
            // TODO: Unify with the code in 'else'.
            // TODO: Consider '$jupyter_notebook_json = $this->rest_ctrl->getJupyterNotebook' in writeJupyterLabQuestionFromForm (?) --> push before


        } else {
            $jupyter_session = new ilJupyterSession();
            $jupyter_notebook_json = '{"content":{ "cells": [ { "cell_type": "code", "execution_count": 1, "id": "ae279420", "metadata": {}, "outputs": [ { "name": "stdout", "output_type": "stream", "text": [ "hello world!\n" ] } ], "source": [ "echo \"hello world!\"" ] }, { "cell_type": "code", "execution_count": 0, "id": "c5775578", "metadata": {}, "outputs": [], "source": [] } ], "metadata": { "kernelspec": { "display_name": "Bash", "language": "bash", "name": "bash" }, "language_info": { "codemirror_mode": "shell", "file_extension": ".sh", "mimetype": "text/x-sh", "name": "bash" } }, "nbformat": 4, "nbformat_minor": 5}, "format":"json", "type":"notebook"}';
            $jupyter_user_credentials = $jupyter_session->getUserCredentials();
            $this->rest_ctrl->pushJupyterNotebook($jupyter_notebook_json, $jupyter_user_credentials['user'], $jupyter_user_credentials['token']);
        }
        $this->object->setJupyterUser($jupyter_user_credentials['user']);
        $this->object->setJupyterToken($jupyter_user_credentials['token']);


        if (!$form instanceof ilPropertyFormGUI) {
            $form = $this->initEditQuestionForm();

        }
        $this->tpl->setVariable("QUESTION_DATA", $form->getHTML());
    }

    public function save(): void
    {
        //TODO: $this->getJupyterQuestion()->deleteServerSideJupyterNotebook();

        $form = $this->initEditQuestionForm();
        if ($form->checkInput()) {
            $this->writeJupyterLabQuestionFromForm($form);

            parent::save();
        } else {
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt('err_check_input'));
            $form->setValuesByPost();
            $this->editQuestion($form);
        }
    }

    /**
     * Save and return
     */
    public function saveReturn(): void
    {
        //TODO: $this->getJupyterQuestion()->deleteServerSideJupyterNotebook();
        $this->getJupyterQuestion()->deleteServerSideJupyterNotebook();

        $form = $this->initEditQuestionForm();
        if ($form->checkInput()) {
            $this->writeJupyterLabQuestionFromForm($form);
            parent::saveReturn();
        } else {
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt('err_check_input'));
            $form->setValuesByPost();
            $this->editQuestion($form);
        }
    }

    /**
     * Set the JupyterLab Question attributes to the Input of the form.
     */
    public function writeJupyterLabQuestionFromForm(ilPropertyFormGUI $form)
    {
        $vibLabQuestion = $this->getJupyterQuestion();
        $vibLabQuestion->setTitle($form->getInput('title'));
        $vibLabQuestion->setComment($form->getInput('comment'));
        $vibLabQuestion->setAuthor($form->getInput('author'));
        $vibLabQuestion->setQuestion($form->getInput('question'));
        $vibLabQuestion->setPoints($form->getInput('points'));

        $evaluation = ilJupyterUtil::extractJsonFromCustomZip($form->getInput('jupyterevaluation'));
        $vibLabQuestion->setJupyterEvaluation($evaluation);

        $jupyter_session_id = $form->getInput('jupyter_session_id');
        $vibLabQuestion->setJupyterUser($form->getInput('jupyter_session_id'));

        $jupyter_session = new ilJupyterSession($jupyter_session_id);
        $user_credentials = $jupyter_session->getUserCredentials();
        $vibLabQuestion->setJupyterToken($user_credentials['token']);

        $jupyter_notebook_json = $this->rest_ctrl->getJupyterNotebook($user_credentials['user'], $user_credentials['token']);
        $vibLabQuestion->setJupyterExercise($jupyter_notebook_json);

        $vibLabQuestion->setJupyterResultStorage($form->getInput('result_storing'));
        $vibLabQuestion->setJupyterAutoScoring($form->getInput('auto_scoring'));

        ilLoggerFactory::getLogger('jupyter')->debug(print_r($form->getInput('jupyterexercise'), true));

        $vibLabQuestion->setEstimatedWorkingTime(
            $_POST["Estimated"]["hh"],
            $_POST["Estimated"]["mm"],
            $_POST["Estimated"]["ss"]
        );

        $vibLabQuestion->setJupyterLang($form->getInput('language'));
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
        $tpl->addJavaScript($this->getPlugin()->getDirectory() . '/js/question_init.js');
//        $jupyter_metadata = $this->rest_ctrl->initJupyterNotebook();

        include_once './Services/UICore/classes/class.ilTemplate.php';
        $template = $this->getPlugin()->getTemplate('tpl.jupyter_frame.html');
        $template->setVariable('JUPYTER_TEST', 'test');
//        $template->setVariable('IFRAME_SRC', 'https://127.0.0.11/jupyter/user/' . $jupyter_metadata['user'] . '/notebooks/test.ipynb?token=' . $jupyter_metadata['token']);
        $preview = $template->get();
        $preview = !$a_show_question_only ? $this->getILIASPage($preview) : $preview;
        return $preview;
    }

    public function getTestOutput($active_id, $pass, $is_question_postponed, $user_post_solutions, $show_specific_inline_feedback)
    {
//		$settings = ilJupyterSettings::getInstance();
//		ilLoggerFactory::getLogger('jupyter')->debug('JupyterCookie: '. $this->getJupyterQuestion()->getJupyterCookie());
//		$atpl->setVariable('VIP_STORED_EXERCISE', $this->getJupyterQuestion()->getJupyterExerciseId());
        $jupyter_metadata = $this->rest_ctrl->initJupyterNotebook();

        $atpl = $this->getPlugin()->getTemplate('tpl.jupyter_frame.html');
        $atpl->setVariable('JUPYTER_TEST', 'test');
        $atpl->setVariable('IFRAME_SRC', 'https://127.0.0.11/jupyter/user/' . $jupyter_metadata['user'] . '/notebooks/test.ipynb?token=' . $jupyter_metadata['token']);
        $pageoutput = $this->outQuestionPage("", $is_question_postponed, $active_id, $atpl->get());
        return $pageoutput;
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

//		$soltpl->setVariable('SOLUTION_TXT', '', TRUE);
        $soltpl->setVariable('JUPYTER_TEST', 'test');
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

    public function getSpecificFeedbackOutput($userSolution): string
    {
        return "";
    }

}