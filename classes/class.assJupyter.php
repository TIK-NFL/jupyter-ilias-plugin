<?php
/*
  +----------------------------------------------------------------------------+
  | ILIAS open source                                                          |
  +----------------------------------------------------------------------------+
  | Copyright (c) 1998-2001 ILIAS open source, University of Cologne           |
  |                                                                            |
  | This program is free software; you can redistribute it and/or              |
  | modify it under the terms of the GNU General Public License                |
  | as published by the Free Software Foundation; either version 2             |
  | of the License, or (at your option) any later version.                     |
  |                                                                            |
  | This program is distributed in the hope that it will be useful,            |
  | but WITHOUT ANY WARRANTY; without even the implied warranty of             |
  | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the              |
  | GNU General Public License for more details.                               |
  |                                                                            |
  | You should have received a copy of the GNU General Public License          |
  | along with this program; if not, write to the Free Software                |
  | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA. |
  +----------------------------------------------------------------------------+
*/

use exceptions\ilCurlErrorCodeException;

include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
include_once "./Modules/Test/classes/inc.AssessmentConstants.php";

/**
 * Class for Mathematik Online based questions
 *
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 * @version    $Id$
 * @ingroup ModulesTestQuestionPool
 */
class assJupyter extends assQuestion
{
    const ADDITIONAL_TBL_NAME = 'il_qpl_qst_jupyter';
    private $jupyter_user = '';
    private $jupyter_token = '';
    private $jupyter_exercise = '';
    private $jupyter_exercise_id = 0;
    private $plugin;
    private ilJupyterRESTController $rest_ctrl;
    private ilJupyterSettings $jupyter_settings;

    /**
     * jupyter lab question
     *
     * The constructor takes possible arguments an creates an instance of the assMathematikOnline object.
     *
     * @param string $title A title string to describe the question
     * @param string $comment A comment string to describe the question
     * @param string $author A string containing the name of the questions author
     * @param integer $owner A numerical ID to identify the owner/creator
     * @param string $question The question string of the single choice question
     * @access public
     * @see assQuestion:assQuestion()
     */
    public function __construct($title = "", $comment = "", $author = "", $owner = -1, $question = "")
    {
        parent::__construct($title, $comment, $author, $owner, $question);
        $this->plugin = ilassJupyterPlugin::getInstance();
        $this->rest_ctrl = new ilJupyterRESTController();
        $this->jupyter_settings = ilJupyterSettings::getInstance();
    }

    /**
     * @return ilassJupyterPlugin The plugin object
     */
    public function getPlugin()
    {
        return $this->plugin;
    }

    public function setJupyterToken($jupyter_token)
    {
        $this->jupyter_token = $jupyter_token;
    }

    public function getJupyterToken()
    {
        return $this->jupyter_token;
    }

    public function setJupyterExercise($a_exc)
    {
        $this->jupyter_exercise = $a_exc;
    }

    public function getJupyterExercise()
    {
        return $this->jupyter_exercise;
    }

    public function setJupyterExerciseId($a_exc)
    {
        $this->jupyter_exercise_id = $a_exc;
    }

    public function getJupyterExerciseId()
    {
        return $this->jupyter_exercise_id;
    }

    public function getJupyterUser(): string
    {
        return $this->jupyter_user;
    }

    public function setJupyterUser(string $jupyter_user): void
    {
        $this->jupyter_user = $jupyter_user;
    }

    // -----------------------------------------------------------------------------------------------------------------
    // -----------------------------------------------------------------------------------------------------------------
    // -----------------------------------------------------------------------------------------------------------------

    /**
     * Returns true, if a single choice question is complete for use
     *
     * @return boolean True, if the single choice question is complete for use, otherwise false
     * @access public
     */
    function isComplete(): bool
    {
        return (($this->title) and ($this->author) and ($this->question) and ($this->getMaximumPoints() > 0));
    }


    function saveToDb($original_id = -1): void
    {
        global $ilDB;

        $this->saveQuestionDataToDb($original_id);

        // save additional data
        $ilDB->manipulateF("DELETE FROM " . $this->getAdditionalTableName() . " WHERE question_fi = %s", array("integer"), array($this->getId()));

		$ilDB->insert(
				$this->getAdditionalTableName(),
				array(
					'question_fi'	=> array('integer',(int) $this->getId()),
					'jupyter_user'	=> array('text', (string) $this->getJupyterUser()),
					'jupyter_token'	=> array('text', (string) $this->getJupyterToken()),
					'jupyter_exercise'	=> array('clob',(string) $this->getJupyterExercise()),
					'jupyter_exercise_id'	=> array('integer',(string) $this->getJupyterExerciseId()),
				)
		);
        parent::saveToDb($original_id);
    }


    function loadFromDb($question_id): void
    {
        global $ilDB;

        $result = $ilDB->queryF("SELECT qpl_questions.* FROM qpl_questions WHERE question_id = %s", array('integer'), array($question_id));
        if ($result->numRows() == 1) {
            $data = $ilDB->fetchAssoc($result);
            $this->setId($question_id);
            $this->setTitle($data["title"] ?? "");
            $this->setComment($data["description"] ?? "");
            $this->setSuggestedSolution($data["solution_hint"] ?? "");
            $this->setOriginalId($data["original_id"]);
            $this->setObjId($data["obj_fi"] ?? 0);
            $this->setAuthor($data["author"] ?? "");
            $this->setOwner($data["owner"] ?? -1);
            $this->setPoints($data["points"] ?? 0);

            include_once("./Services/RTE/classes/class.ilRTE.php");
            $this->setQuestion(ilRTE::_replaceMediaObjectImageSrc($data["question_text"] ?? "", 1));

//			$this->setEstimatedWorkingTimeFromDurationString($data["working_time"] ?? "");

            // load additional data
            $result = $ilDB->queryF("SELECT * FROM " . $this->getAdditionalTableName() . " WHERE question_fi = %s", array('integer'), array($question_id));

            if ($result->numRows() == 1) {
                $data = $ilDB->fetchAssoc($result);
                $this->setJupyterUser((string)$data['jupyter_user']);
                $this->setJupyterToken((string)$data['jupyter_token']);
                $this->setJupyterExercise((string)$data['jupyter_exercise']);
                $this->setJupyterExerciseId((int)$data['jupyter_exercise_id']);
            }
        }
        parent::loadFromDb($question_id);
    }

    function queryGetJupyterUserToken($question_id): ?string
    {
        global $ilDB;
        $result = $ilDB->queryF("SELECT * FROM " . $this->getAdditionalTableName() . " WHERE question_fi = %s", array('integer'), array($question_id));
        return $ilDB->fetchAssoc($result)['jupyter_token'] ?: $result->numRows() == 1;
    }

    function updateJupyterUserToken($question_id, $jupyter_user, $jupyter_token)
    {
        global $ilDB;
        $ilDB->manipulateF("UPDATE " . $this->getAdditionalTableName() . " SET jupyter_user = %s, jupyter_token = %s WHERE question_fi = %s", array('string', 'string', 'integer'), array($jupyter_user, $jupyter_token, $question_id));
    }

    /**
     * Duplicates a jupyter question.
     *
     * @param $for_test
     * @param $title
     * @param $author
     * @param $owner
     * @param $a_test_obj_id
     * @return int
     * @access public
     */
    public function duplicate($for_test = true, $title = "", $author = "", $owner = "", $a_test_obj_id = null): int
    {
        if ($this->id <= 0) {
            // The question has not been saved. It cannot be duplicated
            return -1;
        }
        // duplicate the question in database
        $this_id = $this->getId();
        $clone = clone $this;
        include_once("./Modules/TestQuestionPool/classes/class.assQuestion.php");
        $original_id = assQuestion::_getOriginalId($this->id);
        $clone->id = -1;

        if ((int)$a_test_obj_id > 0) {
            $clone->setObjId($a_test_obj_id);
        }

        if ($title) {
            $clone->setTitle($title);
        }

        if ($author) {
            $clone->setAuthor($author);
        }
        if ($owner) {
            $clone->setOwner($owner);
        }

        if ($for_test) {
            $clone->saveToDb($original_id);
        } else {
            $clone->saveToDb();
        }

        // copy question page content
        $clone->copyPageOfQuestion($this_id);

        // copy XHTML media objects
        $clone->copyXHTMLMediaObjectsOfQuestion($this_id);

        $clone->onDuplicate((int)$a_test_obj_id, $this_id, $clone->getObjId(), $clone->getId());

        return $clone->id;
    }


    public function createNewOriginalFromThisDuplicate($targetParentId, $targetQuestionTitle = "")
    {
        if ($this->id <= 0) {
            // The question has not been saved. It cannot be duplicated
            return;
        }

        include_once("./Modules/TestQuestionPool/classes/class.assQuestion.php");

        $sourceQuestionId = $this->id;
        $sourceParentId = $this->getObjId();

        // duplicate the question in database
        $clone = $this;
        $clone->id = -1;

        $clone->setObjId($targetParentId);

        if ($targetQuestionTitle) {
            $clone->setTitle($targetQuestionTitle);
        }

        $clone->saveToDb();
        // copy question page content
        $clone->copyPageOfQuestion($sourceQuestionId);
        // copy XHTML media objects
        $clone->copyXHTMLMediaObjectsOfQuestion($sourceQuestionId);

        $clone->onCopy($sourceParentId, $sourceQuestionId, $clone->getObjId(), $clone->getId());

        return $clone->id;
    }


    /**
     * Copies an assMathematikOnline object
     *
     * Copies an assMathematikOnline object
     *
     * @access public
     */
    function copyObject($target_questionpool, $title = "")
    {
        if ($this->id <= 0) {
            // The question has not been saved. It cannot be duplicated
            return;
        }
        // duplicate the question in database
        $clone = $this;
        include_once("./Modules/TestQuestionPool/classes/class.assQuestion.php");
        $original_id = assQuestion::_getOriginalId($this->id);
        $clone->id = -1;
        $source_questionpool = $this->getObjId();
        $clone->setObjId($target_questionpool);
        if ($title) {
            $clone->setTitle($title);
        }
        $clone->saveToDb();

        // copy question page content
        $clone->copyPageOfQuestion($original_id);
        // copy XHTML media objects
        $clone->copyXHTMLMediaObjectsOfQuestion($original_id);
        // duplicate the generic feedback
        // TODO figure out new way for feedback copy in question pools
        //$clone->duplicateGenericFeedback($original_id);

        return $clone->id;
    }

    /**
     * Returns the maximum points, a learner can reach answering the question
     *
     * @access public
     * @see $points
     */
    function getMaximumPoints(): float
    {
        return $this->points;
    }

    /**
     * Returns the points, a learner has reached answering the question
     * The points are calculated from the given answers including checks
     * for all special scoring options in the test container.
     *
     * @param integer $user_id The database ID of the learner
     * @param integer $test_id The database Id of the test containing the question
     * @param boolean $returndetails (deprecated !!)
     * @access public
     */
    function calculateReachedPoints($active_id, $pass = NULL, $authorizedSolution = true, $returndetails = FALSE)
    {
        global $ilDB;

        $found_values = array();
        if (is_null($pass)) {
            $pass = $this->getSolutionMaxPass($active_id);
        }
        $result = $ilDB->queryF("SELECT * FROM tst_solutions WHERE active_fi = %s AND question_fi = %s AND pass = %s", array('integer', 'integer', 'integer'), array($active_id, $this->getId(), $pass));

        $points = 0;
        while ($data = $ilDB->fetchAssoc($result)) {
            $points += $data["points"];
        }

        return $points;
    }

    protected function calculateReachedPointsForSolution($solution)
    {
        return 0;  // since instant feedback is not supported by Jupyter
    }


    public function saveWorkingData($active_id, $pass = NULL, $authorized = true): bool
    {
        global $ilDB;

        ilLoggerFactory::getLogger('jupyter')->debug('Saving working data...');

        if (is_null($pass)) {
            include_once './Modules/Test/classes/class.ilObjTest.php';
            $pass = ilObjTest::_getPass($active_id);
        }

        // Probably consider the case when the notebook is deleted on jupyterhub while editing. => Produces ilCurlErrorCodeException (404).
        // This means, that the jupyterhub session was cleaned up before the ILIAS session was closed, which should by session length definition never be the case.
        $jupyter_session = new ilJupyterSession($_POST['jupyter_user']);
        $user_credentials = $jupyter_session->getUserCredentials();
        $solution = $this->rest_ctrl->pullJupyterNotebook($user_credentials['user'], $user_credentials['token']);

        //TODO: clean jupyter outputs

		$ilDB->manipulateF(
				'DELETE FROM tst_solutions '.
				'WHERE active_fi = %s '.
				'AND question_fi = %s '.
				'AND pass = %s '.
				'AND value1 != %s',
			array('integer', 'integer', 'integer', 'text'),
			array($active_id, $this->getId(), $pass, "0")
		);

        $next_id = $ilDB->nextId('tst_solutions');
		$ilDB->insert(
			"tst_solutions", 
			array(
				'solution_id' => array("integer", $next_id),
				"active_fi" => array("integer", $active_id),
				"question_fi" => array("integer", $this->getId()),
				"value1" => array("clob", 'jupytersolution'),
				"value2" => array("clob", $solution),
				"pass" => array("integer", $pass),
				"tstamp" => array("integer", time())
			)
		);

        include_once("./Modules/Test/classes/class.ilObjAssessmentFolder.php");
        if (strlen($solution)) {
            if (ilObjAssessmentFolder::_enabledAssessmentLogging()) {
                $this->logAction($this->lng->txtlng("assessment", "log_user_entered_values", ilObjAssessmentFolder::_getLogLanguage()), $active_id, $this->getId());
            }
        } else {
            if (ilObjAssessmentFolder::_enabledAssessmentLogging()) {
                $this->logAction($this->lng->txtlng("assessment", "log_user_not_entered_values", ilObjAssessmentFolder::_getLogLanguage()), $active_id, $this->getId());
            }
        }

        return true;
    }

    /**
     * Synchronizes the Jupyter (open notebooks) between the Jupyterhub and ILIAS.
     *
     * TODO: Consider expiration of jupyter notebooks.
     * TODO: Refine ilCurlErrorCodeException handling. For now, assume that the jupyter notebook has been cleaned up on jupyterhub (not during an ILIAS session).
     * TODO: Jupyter notebook version comparison. Mismatch when saved on jupyterhub (via browser) but not in ILIAS...
     *
     *
     * @throws JsonException
     * @throws ilCurlConnectionException
     */
    public function synchronizeJupyterSession()
    {
        $jupyter_user = $this->getJupyterUser();
        $jupyter_token = $this->getJupyterToken();

        if ($jupyter_user && ilJupyterSession::isSessionSet($jupyter_user)) {
            // A jupyter session was set before and is still active. Thus, use the existing jupyter-notebook from jupyterhub.
            $jupyter_session = new ilJupyterSession($jupyter_user);
            $jupyter_user_credentials = $jupyter_session->getUserCredentials();

        } else if ($jupyter_user && !ilJupyterSession::isSessionSet($jupyter_user)) {
            // A jupyter session was set before and is no longer active.

            try {
                // Create session from local (ILIAS DB saved) credentials and pull the jupyter-notebook from jupyterhub.
                // Jupyterhub sessions should not be shorter than ILIAS sessions.

                $jupyter_session = ilJupyterSession::fromCredentials(array('user' => $jupyter_user, 'token' => $jupyter_token));
                $jupyter_user_credentials = $jupyter_session->getUserCredentials();
                $jupyter_notebook_json = $this->rest_ctrl->pullJupyterNotebook($jupyter_user_credentials['user'], $jupyter_user_credentials['token']);
                $this->setJupyterExercise($jupyter_notebook_json);
                ilLoggerFactory::getLogger('jupyter')->debug("Jupyter session for user '" . $jupyter_user_credentials['user'] . "' successfully established from local credentials.");

            } catch (ilCurlErrorCodeException $exception) {
                // If the jupyter-notebook is not available on jupyterhub, push it from local database.

                $jupyter_session = new ilJupyterSession();
                $jupyter_notebook_json = $this->getJupyterExercise();
                $jupyter_user_credentials = $jupyter_session->getUserCredentials();
                $this->rest_ctrl->pushJupyterNotebook($jupyter_notebook_json, $jupyter_user_credentials['user'], $jupyter_user_credentials['token']);
                ilLoggerFactory::getLogger('jupyter')->debug("Jupyter notebook for user '" . $jupyter_user . "' not available on jupyterhub. New jupyter session created.");
            }

        } else {
            $jupyter_session = new ilJupyterSession();
            $jupyter_notebook_json = '{"content":{ "cells": [  ], "metadata": { "kernelspec": { "display_name": "Bash", "language": "bash", "name": "bash" }, "language_info": { "codemirror_mode": "shell", "file_extension": ".sh", "mimetype": "text/x-sh", "name": "bash" } }, "nbformat": 4, "nbformat_minor": 5}, "format":"json", "type":"notebook"}';
            $jupyter_user_credentials = $jupyter_session->getUserCredentials();
            $this->rest_ctrl->pushJupyterNotebook(
                $this->jupyter_settings->getDefaultJupyterNotebook(),
                $jupyter_user_credentials['user'],
                $jupyter_user_credentials['token']
            );
            ilLoggerFactory::getLogger('jupyter')->debug("New jupyter session and a default jupyter notebook created for user '" . $jupyter_user_credentials['user'] . "'.");
        }
        $this->setJupyterUser($jupyter_user_credentials['user']);
        $this->setJupyterToken($jupyter_user_credentials['token']);

        // Update user credentials within ILIAS DB for the case, a new jupyter session has been initialized.
        $this->updateJupyterUserToken($this->getId(), $jupyter_user_credentials['user'], $jupyter_user_credentials['token']);
    }

    public function pushLocalJupyterNotebook()
    {
        $jupyter_session = new ilJupyterSession();
        $jupyter_notebook_json = $this->getJupyterExercise();
        $jupyter_user_credentials = $jupyter_session->getUserCredentials();
        $this->jupyter_user = $jupyter_user_credentials['user'];
        $this->jupyter_token = $jupyter_user_credentials['token'];
        $this->rest_ctrl->pushJupyterNotebook($jupyter_notebook_json, $this->jupyter_user, $this->jupyter_token);
    }

    /**
     * E.g., used for readonly sessions.
     *
     * @param $jupyter_notebook_json The notebook file as a JSON string
     * @return array Jupyter user credentials
     */
    public function pushTemporaryJupyterNotebook($jupyter_notebook_json)
    {
        $jupyter_session = new ilJupyterSession();
        $jupyter_user_credentials = $jupyter_session->getUserCredentials();
        $this->rest_ctrl->pushJupyterNotebook($jupyter_notebook_json, $jupyter_user_credentials['user'], $jupyter_user_credentials['token']);
        return $jupyter_user_credentials;
    }


    /**
     *
     * @param type $active_id
     * @param type $pass
     * @param type $obligationsAnswered
     * @param type $authorized
     */
    public function reworkWorkingData($active_id, $pass, $obligationsAnswered, $authorized)
    {
    }

    /**
     * Returns the question type of the question
     *
     * Returns the question type of the question
     *
     * @return integer The question type of the question
     * @access public
     */
    function getQuestionType(): string
    {
        return $this->getPlugin()->getQuestionType();
    }

    /**
     * Returns the name of the additional question data table in the database
     *
     * Returns the name of the additional question data table in the database
     *
     * @return string The additional table name
     * @access public
     */
    function getAdditionalTableName()
    {
        return self::ADDITIONAL_TBL_NAME;
    }

    /**
     * Returns the name of the answer table in the database
     *
     * Returns the name of the answer table in the database
     *
     * @return string The answer table name
     * @access public
     */
    function getAnswerTableName()
    {
        return "";
    }

    /**
     * Deletes datasets from answers tables
     *
     * @param integer $question_id The question id which should be deleted in the answers table
     * @access public
     */
    public function deleteAnswers($question_id): void
    {
    }

    /**
     * Collects all text in the question which could contain media objects
     * which were created with the Rich Text Editor
     */
    function getRTETextWithMediaObjects(): string
    {
        $text = parent::getRTETextWithMediaObjects();
        return $text;
    }

    /**
     * required method stub
     *
     * @param object $worksheet Reference to the parent excel worksheet
     * @param object $startrow Startrow of the output in the excel worksheet
     * @param object $active_id Active id of the participant
     * @param object $pass Test pass
     */
    public function setExportDetailsXLS($worksheet, $startrow, $active_id, $pass): int
    {
        return parent::setExportDetailsXLS($worksheet, $startrow, $active_id, $pass);
    }

    /**
     * Creates a question from a QTI file
     *
     * Receives parameters from a QTI parser and creates a valid ILIAS question object
     *
     * @param object $item The QTI item object
     * @param integer $questionpool_id The id of the parent questionpool
     * @param integer $tst_id The id of the parent test if the question is part of a test
     * @param object $tst_object A reference to the parent test object
     * @param integer $question_counter A reference to a question counter to count the questions of an imported question pool
     * @param array $import_mapping An array containing references to included ILIAS objects
     * @access public
     */
    public function fromXML($item, int $questionpool_id, ?int $tst_id, &$tst_object, int &$question_counter, array $import_mapping, array &$solutionhints = []): array
    {
        $this->getPlugin()->includeClass("./import/qti12/class." . $this->getQuestionType() . "Import.php");
        $classname = $this->getQuestionType() . "Import";
        $import = new $classname($this);
        $import_mapping = $import->fromXML($item, $questionpool_id, $tst_id, $tst_object, $question_counter, $import_mapping);

        foreach ($solutionhints as $hint) {
            $h = new ilAssQuestionHint();
            $h->setQuestionId($import->getQuestionId());
            $h->setIndex($hint['index']);
            $h->setPoints($hint['points']);
            $h->setText($hint['txt']);
            $h->save();
        }
        return $import_mapping;
    }

    /**
     * Returns a QTI xml representation of the question and sets the internal
     * domxml variable with the DOM XML representation of the QTI xml representation
     *
     * @return string The QTI xml representation of the question
     * @access public
     */
    function toXML($a_include_header = true, $a_include_binary = true, $a_shuffle = false, $test_output = false, $force_image_references = false): string
    {
        $this->getPlugin()->includeClass("./export/qti12/class." . $this->getQuestionType() . "Export.php");
        $classname = $this->getQuestionType() . "Export";
        $export = new $classname($this);
        return $export->toXML($a_include_header, $a_include_binary, $a_shuffle, $test_output, $force_image_references);
    }

    /**
     * Returns the best solution for a given pass of a participant
     *
     * @return array An associated array containing the best solution
     * @access public
     */
    public function getBestSolution($active_id, $pass)
    {
        $user_solution = array();
        return $user_solution;
    }


    public function getSolutionSubmit()
    {
        return array();  // instant feedback preview is not supported by Jupyter
    }

    public function deleteServerSideJupyterNotebook($a_sent_id = 0)
    {
        $exc_id = $a_sent_id ? $a_sent_id : $this->getJupyterExerciseId();
        if ($exc_id) {
            return;
        }
    }


    /**
     * Lookup if an authorized or intermediate solution exists
     * @param int $activeId
     * @param int $pass
     * @return    array        ['authorized' => bool, 'intermediate' => bool]
     */
    public function lookupForExistingSolutions($activeId, $pass): array
    {
        global $ilDB;

        $state = parent::lookupForExistingSolutions($activeId, $pass);
        $state['intermediate'] = true;
        return $state;
    }
}
