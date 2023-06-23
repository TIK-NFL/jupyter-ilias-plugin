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
    public function __construct($a_id = -1)
    {
        parent::__construct($a_id);
        $this->object = new assJupyter();
        $this->newUnitId = null;

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

    protected function initEditQuestionForm($a_show_editor = FALSE)
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

        #$this->addQuestionFormCommandButtons($form);
        $form->addCommandButton("save", $this->lng->txt("save"));

        $editor_form = new ilJupyterEditorFormGUI($this->getPlugin()->txt('editor'), 'editor', $this->getJupyterQuestion());
        $editor_form->showEditor($this->getJupyterQuestion()->getJupyterSubId() && $a_show_editor);

        $form->addItem($editor_form);
        return $form;
    }

    protected function initEditor()
    {
        global $DIC;

        $form = $this->initEditQuestionForm();

        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt('err_check_input'), true);
            $this->editQuestion($form);
            return TRUE;
        }

        // form valid
        $this->writeJupyterLabQuestionFromForm($form);

        $this->getJupyterQuestion()->deleteSubParticipant();
        $this->addSubParticipant();

        $this->getJupyterQuestion()->deleteExercise();
        $this->createExercise();

        // initialize form again with editor
        $form = $this->initEditQuestionForm(TRUE);

        $this->getJupyterQuestion()->saveToDb();


        return $this->editQuestion($form);
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
     * Create a new solution
     * @return int
     */
    protected function createEvaluation()
    {
        return $this->getJupyterQuestion()->createEvaluation();
    }

    /**
     * Create a new solution
     * @return int
     */
    protected function createResult($a_active_id, $a_pass)
    {
        $this->getJupyterQuestion()->createResult($a_active_id, $a_pass);
    }

    /**
     * Create exercise
     */
    protected function createExercise()
    {
        $this->getJupyterQuestion()->createExercise();
    }


    protected function addSubParticipant()
    {
        return $this->getJupyterQuestion()->addSubParticipant();
    }

    /**
     * Show edit question form
     * @param ilPropertyFormGUI $form
     */
    protected function editQuestion(ilPropertyFormGUI $form = null)
    {
        $this->getQuestionTemplate();

        if (!$form instanceof ilPropertyFormGUI) {
            $form = $this->initEditQuestionForm();

        }
        $this->tpl->setVariable("QUESTION_DATA", $form->getHTML());
    }

    /**
     * Save question
     */
    public function save(): void
    {
        $this->getJupyterQuestion()->deleteSubParticipant();
        $this->getJupyterQuestion()->deleteExercise();

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
        $this->getJupyterQuestion()->deleteSubParticipant();
        $this->getJupyterQuestion()->deleteExercise();

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
        $vibLabQuestion->setJupyterExercise($form->getInput('jupyterexercise'));

        $evaluation = ilJupyterUtil::extractJsonFromCustomZip($form->getInput('jupyterevaluation'));
        $vibLabQuestion->setJupyterEvaluation($evaluation);

        $vibLabQuestion->setJupyterResultStorage($form->getInput('result_storing'));
        $vibLabQuestion->setJupyterAutoScoring($form->getInput('auto_scoring'));

        ilLoggerFactory::getLogger('jupyter')->debug(print_r($form->getInput('jupyterexercise'), true));

        $vibLabQuestion->setEstimatedWorkingTime(
            $_POST["Estimated"]["hh"],
            $_POST["Estimated"]["mm"],
            $_POST["Estimated"]["ss"]
        );

        $vibLabQuestion->setJupyterLang($form->getInput('language'));
        return TRUE;
    }

    /**
     * Write post
     * @param boolean $always
     * @return int
     */
    public function writePostData($always = false): int
    {
        return 0;
    }

    public function getPreview($a_show_question_only = FALSE, $showInlineFeedback = FALSE)
    {
//		global $DIC;
//		$tpl = $DIC->ui()->mainTemplate();
//      $tpl->addJavaScript($this->getPlugin()->getDirectory().'/js/question_init.js');

        include_once './Services/UICore/classes/class.ilTemplate.php';
        $template = $this->getPlugin()->getTemplate('tpl.jupyter_frame.html');
//      $template->setVariable('EDITOR_START', $this->getPlugin()->txt('editor_start'));
        $template->setVariable('JUPYTER_TEST', 'test');
        $preview = $template->get();
        $preview = !$a_show_question_only ? $this->getILIASPage($preview) : $preview;
        return $preview;
    }

    public function getTestOutput($active_id, $pass, $is_question_postponed, $user_post_solutions, $show_specific_inline_feedback)
    {
//		$settings = ilJupyterSettings::getInstance();
//		ilLoggerFactory::getLogger('jupyter')->debug('JupyterCookie: '. $this->getJupyterQuestion()->getJupyterCookie());
//		$atpl->setVariable('VIP_STORED_EXERCISE', $this->getJupyterQuestion()->getJupyterExerciseId());

        $atpl = $this->getPlugin()->getTemplate('tpl.jupyter_frame.html');
        $atpl->setVariable('JUPYTER_TEST', 'test');
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