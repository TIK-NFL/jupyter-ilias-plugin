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
use exceptions\JupyterTransferException;
use ILIAS\ResourceStorage\Resource\ResourceNotFoundException;

include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
include_once "./Modules/Test/classes/inc.AssessmentConstants.php";

/**
 * Class for Jupyter based questions.
 *
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 * @version $Id$
 * @ingroup ModulesTestQuestionPool
 */
class assJupyter extends assQuestion
{
    const ADDITIONAL_TBL_NAME = 'il_qpl_qst_jupyter';
    private string $jupyter_user = '';
    private string $jupyter_token = '';
    private string $jupyter_exercise_resource_id = '';
    private int $jupyter_exercise_id = 0;
    private string $jupyter_view_mode = '';
    private string $entry_file_path = '';
    private $plugin;
    private ilJupyterRESTController $rest_ctrl;

    private ilJupyterDBController $db_ctrl;

    private ilJupyterIRSSController $resource_ctrl;

    private ilJupyterSettings $jupyter_settings;


    public function __construct(string $title = "", string $comment = "", string $author = "", int $owner = -1, string $question = "")
    {
        parent::__construct($title, $comment, $author, $owner, $question);
        $this->plugin = ilassJupyterPlugin::getInstance();
        $this->rest_ctrl = new ilJupyterRESTController();
        $this->db_ctrl = new ilJupyterDBController();
        $this->resource_ctrl = new ilJupyterIRSSController();
        $this->jupyter_settings = ilJupyterSettings::getInstance();
    }

    public function getPlugin(): ilassJupyterPlugin
    {
        return $this->plugin;
    }

    public function setJupyterToken($jupyter_token)
    {
        $this->jupyter_token = $jupyter_token;
    }

    public function getJupyterToken(): string
    {
        return $this->jupyter_token;
    }

    public function setJupyterExerciseResourceId($res_id)
    {
        $this->jupyter_exercise_resource_id = $res_id;
    }

    public function getJupyterExerciseResourceId(): string
    {
        return $this->jupyter_exercise_resource_id;
    }

    public function setJupyterExerciseId($a_exc)
    {
        $this->jupyter_exercise_id = $a_exc;
    }

    public function getJupyterExerciseId(): int
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

    public function getJupyterViewMode(): string
    {
        return $this->jupyter_view_mode;
    }

    public function setJupyterViewMode(string $jupyter_view_mode): void
    {
        $this->jupyter_view_mode = $jupyter_view_mode;
    }

    public function getEntryFilePath(): string
    {
        return $this->entry_file_path;
    }

    public function setEntryFilePath(string $entry_file_path): void
    {
        $this->entry_file_path = $entry_file_path;
    }

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
					'question_fi' => array('integer', $this->getId()),
					'jupyter_user' => array('text', $this->getJupyterUser()),
					'jupyter_token'	=> array('text', $this->getJupyterToken()),
					'jupyter_exercise_res_id' => array('text', $this->getJupyterExerciseResourceId()),
					'jupyter_exercise_id' => array('integer', $this->getJupyterExerciseId()),
                    'jupyter_view_mode' => array('text', $this->getJupyterViewMode()),
                    'entry_file_path' => array('text', $this->getEntryFilePath()),
				)
		);

        $this->db_ctrl->updateTemporarySessionUpdateTimestamp($this->getJupyterUser(), time());

        parent::saveToDb();
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
            $this->setOriginalId($data["original_id"]);
            $this->setObjId($data["obj_fi"] ?? 0);
            $this->setAuthor($data["author"] ?? "");
            $this->setOwner($data["owner"] ?? -1);
            $this->setPoints($data["points"] ?? 0);

            include_once("./Services/RTE/classes/class.ilRTE.php");
            $this->setQuestion(ilRTE::_replaceMediaObjectImageSrc($data["question_text"] ?? "", 1));

            // load additional data
            $result = $ilDB->queryF("SELECT * FROM " . $this->getAdditionalTableName() . " WHERE question_fi = %s", array('integer'), array($question_id));

            if ($result->numRows() == 1) {
                $data = $ilDB->fetchAssoc($result);
                $this->setJupyterUser((string)$data['jupyter_user']);
                $this->setJupyterToken((string)$data['jupyter_token']);
                $this->setJupyterExerciseResourceId((string)$data['jupyter_exercise_res_id']);
                $this->setJupyterExerciseId((int)$data['jupyter_exercise_id']);
                $this->setJupyterViewMode((string)$data['jupyter_view_mode']);
                $this->setEntryFilePath((string)$data['entry_file_path']);
            }
        }
        parent::loadFromDb($question_id);
    }

    function updateJupyterUserToken($question_id, $jupyter_user, $jupyter_token)
    {
        global $ilDB;
        $ilDB->manipulateF("UPDATE " . $this->getAdditionalTableName() . " SET jupyter_user = %s, jupyter_token = %s WHERE question_fi = %s", array('string', 'string', 'integer'), array($jupyter_user, $jupyter_token, $question_id));
    }

    public function duplicate($for_test = true, $title = "", $author = "", $owner = "", $testObjId = null): int
    {
        if ($this->id <= 0) {
            // The question has not been saved. It cannot be duplicated
            return -1;
        }
        // duplicate the question in database
        $this_id = $this->getId();
        $clone = clone $this;
        include_once("./Modules/TestQuestionPool/classes/class.assQuestion.php");
        $original_id = $this->questioninfo->getOriginalId($this->id);
        $clone->id = -1;

        if ((int)$testObjId > 0) {
            $clone->setObjId($testObjId);
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

        // Copy the Jupyter resource for the cloned object.
        $new_res_id = $this->resource_ctrl->copyJupyterResource($this->getJupyterExerciseResourceId());
        $clone->setJupyterExerciseResourceId($new_res_id);


        if ($for_test) {
            $clone->saveToDb($original_id);
        } else {
            $clone->saveToDb();
        }

        // copy question page content
        $clone->copyPageOfQuestion($this_id);

        // copy XHTML media objects
        $clone->copyXHTMLMediaObjectsOfQuestion($this_id);

        $clone->onDuplicate((int)$testObjId, $this_id, $clone->getObjId(), $clone->getId());

        return $clone->id;
    }


    public function createNewOriginalFromThisDuplicate($targetParentId, $targetQuestionTitle = ""): int
    {
        if ($this->getId() <= 0) {
            throw new RuntimeException('The question has not been saved. It cannot be duplicated');
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

        // Copy the Jupyter resource for the cloned object.
        $new_res_id = $this->resource_ctrl->copyJupyterResource($this->getJupyterExerciseResourceId());
        $clone->setJupyterExerciseResourceId($new_res_id);

        $clone->saveToDb();
        // copy question page content
        $clone->copyPageOfQuestion($sourceQuestionId);
        // copy XHTML media objects
        $clone->copyXHTMLMediaObjectsOfQuestion($sourceQuestionId);

        $clone->onCopy($sourceParentId, $sourceQuestionId, $clone->getObjId(), $clone->getId());

        return $clone->id;
    }


    function copyObject($target_questionpool, $title = ""): int
    {
        if ($this->getId() <= 0) {
            throw new RuntimeException('The question has not been saved. It cannot be duplicated');
        }

        // duplicate the question in database
        $clone = $this;
        include_once("./Modules/TestQuestionPool/classes/class.assQuestion.php");
        $original_id = $this->questioninfo->getOriginalId($this->id);
        $clone->id = -1;
        $clone->setObjId($target_questionpool);
        if ($title) {
            $clone->setTitle($title);
        }

        // Copy the Jupyter resource for the cloned object.
        $new_res_id = $this->resource_ctrl->copyJupyterResource($this->getJupyterExerciseResourceId());
        $clone->setJupyterExerciseResourceId($new_res_id);

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
     */
    function calculateReachedPoints($active_id, $pass = null, $authorizedSolution = true, $returndetails = false): float
    {
        global $ilDB;

        if (is_null($pass)) {
            $pass = $this->getSolutionMaxPass($active_id);
        }

        $result = $ilDB->queryF(
            "SELECT * FROM tst_solutions WHERE active_fi = %s AND question_fi = %s AND pass = %s",
            array('integer', 'integer', 'integer'),
            array($active_id, $this->getId(), $pass)
        );

        $points = 0.0;
        while ($data = $ilDB->fetchAssoc($result)) {
            $points += $data["points"];
        }

        return $points;
    }

    /**
     * @param $solution
     * @return int Zero since instant feedback is not supported by Jupyter
     */
    protected function calculateReachedPointsForSolution($solution): int
    {
        return 0;
    }


    public function saveWorkingData($active_id, $pass = NULL, $authorized = true): bool
    {
        global $ilDB;

        ilLoggerFactory::getLogger('jupyter')->debug('Saving working data...');

        if (is_null($pass)) {
            include_once './Modules/Test/classes/class.ilObjTest.php';
            $pass = ilObjTest::_getPass($active_id);
        }

        try {
            $jupyter_session = new ilJupyterSession($_POST['jupyter_user']);
            $solution = $this->pullRemoteJupyterProject($jupyter_session->getUserCredentials());
        } catch (ilCurlConnectionException | ilCurlErrorCodeException | JupyterTransferException $e) {
            // If a jupyter project is deleted on jupyterhub while being edited, an ilCurlErrorCodeException (404) will be produced on save.
            // This means, that the jupyterhub session was cleaned up before the ILIAS session was closed, which should by session length definition never be the case.
            ilLoggerFactory::getLogger('jupyter')->error($e->getMessage());
            return false;
        }

        $result = $ilDB->queryF(
                'SELECT * FROM tst_solutions ' .
                'WHERE active_fi = %s ' .
                'AND question_fi = %s ' .
                'AND pass = %s ' .
                'AND value1 = %s',
            array('integer', 'integer', 'integer', 'text'),
            array($active_id, $this->getId(), $pass, 'jupyter_solution_resource_id')
        );

        // If existent, clean up previously saved jupyter resource.
        if ($result->numRows() == 1) {
            $data = $ilDB->fetchAssoc($result);
            $this->resource_ctrl->deleteJupyterResource($data['value2']);
        }

		$ilDB->manipulateF(
				'DELETE FROM tst_solutions '.
				'WHERE active_fi = %s '.
				'AND question_fi = %s '.
				'AND pass = %s '.
				'AND value1 = %s',
			array('integer', 'integer', 'integer', 'text'),
			array($active_id, $this->getId(), $pass, 'jupyter_solution_resource_id')
		);

        $next_id = $ilDB->nextId('tst_solutions');

        $res_id = $this->resource_ctrl->storeJupyterResource(
            $solution, ilJupyterIRSSController::JUPYTER_SOLUTION_RESOURCE
        );

		$ilDB->insert(
			"tst_solutions", 
			array(
				'solution_id' => array("integer", $next_id),
				"active_fi" => array("integer", $active_id),
				"question_fi" => array("integer", $this->getId()),
				"value1" => array("clob", 'jupyter_solution_resource_id'),
				"value2" => array("clob", $res_id),
				"pass" => array("integer", $pass),
				"tstamp" => array("integer", time())
			)
		);

        return true;
    }

    /**
     * Synchronizes the local Jupyter project between Jupyterhub and ILIAS.
     *
     * @throws JsonException
     * @throws ilCurlConnectionException
     * @throws ilCurlErrorCodeException
     * @throws ResourceNotFoundException
     */
    public function synchronizeJupyterSession()
    {
        $jupyter_user = $this->getJupyterUser();
        $jupyter_session_set = ilJupyterSession::isSessionSet($jupyter_user);

        if ($jupyter_user && $jupyter_session_set) {
            // A jupyter session was set before and is still active. Thus, reuse the existing jupyter project from jupyterhub.
            $jupyter_session = new ilJupyterSession($jupyter_user);
            $jupyter_user_credentials = $jupyter_session->getUserCredentials();

        } else if ($jupyter_user && !$jupyter_session_set) {
            // Jupyter session is no longer active, thus create a new session and push the project stored locally.
            $jupyter_session = new ilJupyterSession();
            $jupyter_project_json = $this->resource_ctrl->readJupyterResource($this->getJupyterExerciseResourceId());
            $jupyter_user_credentials = $jupyter_session->getUserCredentials();
            $this->rest_ctrl->pushJupyterProject($jupyter_project_json, $jupyter_user_credentials['user'], $jupyter_user_credentials['token']);
            ilLoggerFactory::getLogger('jupyter')->debug("Jupyter project for user '" . $jupyter_user . "' not available on jupyterhub. New jupyter session created.");

        } else {
            // A jupyter session was not set before, thus create a new one.
            $jupyter_session = new ilJupyterSession();
            $jupyter_user_credentials = $jupyter_session->getUserCredentials();
            $this->rest_ctrl->pushJupyterProject(
                $this->jupyter_settings->getDefaultJupyterProject(),
                $jupyter_user_credentials['user'],
                $jupyter_user_credentials['token']
            );
            ilLoggerFactory::getLogger('jupyter')->debug("New jupyter session and default jupyter project created for user '" . $jupyter_user_credentials['user'] . "'.");
        }

        $this->setJupyterUser($jupyter_user_credentials['user']);
        $this->setJupyterToken($jupyter_user_credentials['token']);

        // Update user credentials within ILIAS DB for the case, a new jupyter session has been initialized.
        $this->updateJupyterUserToken($this->getId(), $jupyter_user_credentials['user'], $jupyter_user_credentials['token']);
    }

    /**
     * @throws ilCurlConnectionException
     * @throws ResourceNotFoundException
     * @throws ilCurlErrorCodeException
     * @throws JupyterTransferException
     * @throws JsonException
     */
    public function pushLocalJupyterProject(): ilJupyterSession
    {
        $jupyter_session = new ilJupyterSession();
        $jupyter_user_credentials = $jupyter_session->getUserCredentials();
        $this->jupyter_user = $jupyter_user_credentials['user'];
        $this->jupyter_token = $jupyter_user_credentials['token'];
        $jupyter_project_json = $this->resource_ctrl->readJupyterResource($this->getJupyterExerciseResourceId());

        if (!$this->rest_ctrl->checkJupyterUserAndServer($this->jupyter_user, $this->jupyter_token)) {
            throw new JupyterTransferException("Jupyter user is unset or the corresponding single user server is not running.");
        }
        $this->rest_ctrl->pushJupyterProject($jupyter_project_json, $this->jupyter_user, $this->jupyter_token);

        return $jupyter_session;
    }

    /**
     * @throws ilCurlConnectionException
     * @throws JupyterTransferException
     * @throws ilCurlErrorCodeException
     * @throws JsonException
     */
    public function pullRemoteJupyterProject($user_credentials): string
    {
        if (!$this->rest_ctrl->checkJupyterUserAndServer($user_credentials['user'], $user_credentials['token'])) {
            throw new JupyterTransferException("Jupyter user is unset or the corresponding single user server is not running.");
        }
        return $this->rest_ctrl->pullJupyterProject($user_credentials['user'], $user_credentials['token']);
    }

    /**
     * @throws ilCurlConnectionException
     * @throws ilCurlErrorCodeException
     */
    public function deleteTemporaryJupyterProject($jupyter_user): bool
    {
        return $this->rest_ctrl->deleteJupyterUser($jupyter_user, $this->jupyter_settings->getApiToken());
    }

    /**
     * @param $jupyter_project_json
     * @return array
     */
    public function pushTemporaryJupyterProject($jupyter_project_json): array
    {
        $jupyter_session = new ilJupyterSession();
        $jupyter_user_credentials = $jupyter_session->getUserCredentials();
        $this->rest_ctrl->pushJupyterProject($jupyter_project_json, $jupyter_user_credentials['user'], $jupyter_user_credentials['token']);
        return $jupyter_user_credentials;
    }

    public function deleteAdditionalTableData(int $question_id): void
    {
        global $ilDB;
        $result = $ilDB->queryF("SELECT * FROM " . $this->getAdditionalTableName() . " WHERE question_fi = %s", array('integer'), array($question_id));

        if ($result->numRows() > 0) {
            $res_id = $ilDB->fetchAssoc($result)['jupyter_exercise_res_id'];
            parent::deleteAdditionalTableData($question_id);
            $this->resource_ctrl->deleteJupyterResource($res_id);
        }
    }

    public function cleanUpUnreferencedSolutionResources() {
        global $ilDB;

        $result = $ilDB->queryF('SELECT * FROM il_qpl_qst_jupyter_dsr', array(), array());
        while ($data = $ilDB->fetchAssoc($result)) {
            $rid = $data['resource_id'];
            try {
                ilLoggerFactory::getLogger('jupyter')->info("Cleaning up stale solution resource with ID '" . $rid . "'");
                $this->resource_ctrl->deleteJupyterResource($rid);
            } catch (ResourceNotFoundException) {
                // do nothing.
            } finally {
                // delete the resource id record, even if the resource is not present on the resource storage.
                $ilDB->manipulateF('DELETE FROM il_qpl_qst_jupyter_dsr WHERE resource_id = %s', array('text'), array($rid));
            }
        }
    }

    /**
     * @throws ilCurlConnectionException
     * @throws JsonException
     * @throws ilCurlErrorCodeException
     */
    public function cleanUpStaleJupyterProjects()
    {
        // TODO: Adjust values and extract ILIAS settings
        $max_age_sec = 60;
        $max_sync_deviation_sec = 60;

        //
        // Check the clock synchronization between localhost and jupyterhub.
        //
        $jupyter_user_credentials = $this->pushTemporaryJupyterProject($this->jupyter_settings->getDefaultJupyterProject());
        $metadata = $this->rest_ctrl->pullJupyterProjectMetaData($jupyter_user_credentials['user'], $this->jupyter_settings->getApiToken());
        $time_current = time();
        $time_created = $metadata['created'];
        $this->rest_ctrl->deleteJupyterUser($jupyter_user_credentials['user'], $this->jupyter_settings->getApiToken());
        $this->db_ctrl->deleteTemporarySessionRecord($jupyter_user_credentials['user']);

        if ($time_current > $time_created + $max_sync_deviation_sec || $time_current < $time_created - $max_sync_deviation_sec) {
            ilLoggerFactory::getLogger('jupyter')->error("Failed to clean up stale jupyter projects due to asynchronous clocks between the local system and jupyterhub.");
            return;
        }

        //
        // Cleanup of temporary jupyter users only created by this ILIAS instance.
        // Considered are only users containing a default jupyter notebook file (main.ipynb) the metadata of which match the given conditions.
        //
        $jupyter_session_records = $this->db_ctrl->getTemporarySessionRecords();
        foreach ($jupyter_session_records as $record) {
            $jupyter_user = $record['jupyter_user'];
            $metadata = $this->rest_ctrl->pullJupyterProjectMetaData($jupyter_user, $this->jupyter_settings->getApiToken());
            if (!$metadata) {
                ilLoggerFactory::getLogger('jupyter')->warning("Cleanup: Jupyter user '" . $jupyter_user . "' is present without the default jupyter notebook file. Failed to gather default notebook metadata.");
                // Delete the session DB record nevertheless, i.e., give up deleting this user on jupyterhub,
                // since no metadata is available to calculate the cleanup time of a jupyter project.
                $this->db_ctrl->deleteTemporarySessionRecord($jupyter_user);
                continue;
            }

            $time_last_modified = $metadata['last_modified'];
            $time_current = time();
            if ($time_current > $time_last_modified + $max_age_sec + $max_sync_deviation_sec) {
                ilLoggerFactory::getLogger('jupyter')->info("Cleanup: Deleting stale jupyter user '" . $jupyter_user . "'...");
                $this->rest_ctrl->deleteJupyterUser($jupyter_user, $this->jupyter_settings->getApiToken());
                $this->db_ctrl->deleteTemporarySessionRecord($jupyter_user);
            }
        }
    }


    /**
     * Returns the question type of the question
     *
     * @return string The question type of the question
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
    function getAdditionalTableName(): string
    {
        return self::ADDITIONAL_TBL_NAME;
    }

    /**
     * Returns the name of the answer table in the database
     *
     * @return string The answer table name
     * @access public
     */
    function getAnswerTableName(): string
    {
        return "";
    }

    /**
     * Deletes datasets from answers tables
     *
     * @param integer $question_id The question id which should be deleted in the answers table
     * @access public
     */
    public function deleteAnswers(int $question_id): void
    {
    }

    function getRTETextWithMediaObjects(): string
    {
        return parent::getRTETextWithMediaObjects();
    }

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
    public function getBestSolution($active_id, $pass): array
    {
        return array();
    }


    /**
     * @return array Empty array since instant feedback preview is not supported by Jupyter
     */
    public function getSolutionSubmit(): array
    {
        return array();
    }



    /**
     * Lookup if an authorized or intermediate solution exists
     * @param int $activeId
     * @param int $pass
     * @return    array        ['authorized' => bool, 'intermediate' => bool]
     */
    public function lookupForExistingSolutions(int $activeId, int $pass): array
    {
        $state = parent::lookupForExistingSolutions($activeId, $pass);
        $state['intermediate'] = true;
        return $state;
    }
}
