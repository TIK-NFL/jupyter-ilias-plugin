<?php

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Wrapper for single ecs ressource objects
 *
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 */
class ilECSJupyterRessources
{
	// Remove old resources after 48h
	const MAX_AGE_SECONDS = 48*3600;
        // Number of old resources to delete in each cron run
	const REMOVE_IN_EACH_CRONEXECUTION = 5000;

	/**
	 * Get ressources
	 * @return ilECSJupyterRessource[]
	 */
	public static function getRessources($a_age = null)
	{
		global $ilDB;
		
		$query = 'SELECT id from il_qpl_qst_jupyter_res ' .
				'WHERE create_dt < ' . $ilDB->quote(time() - self::MAX_AGE_SECONDS, 'integer');
		$res = $ilDB->query($query);
		
		$ressources = array();
		while($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT))
		{
			$ressources[] = new ilECSJupyterRessource($row->id);
		}
		return $ressources;
	}

	public static function deleteDeprecated()
	{
		global $ilDB;
		
		$query = 'SELECT id from il_qpl_qst_jupyter_res ' .
				'WHERE create_dt < ' . $ilDB->quote(time() - self::MAX_AGE_SECONDS, 'integer') .
                                ' limit ' .$ilDB->quote(self::REMOVE_IN_EACH_CRONEXECUTION, 'integer');
		$res = $ilDB->query($query);
		while($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT))
		{
			$ressource = new ilECSJupyterRessource($row->id);
			try {
				self::doDeleteRessource($ressource);
				$ressource->delete();
			} 
			catch (Exception $ex) {
				;
			}
		}
	}
	
	/**
	 * Delete ressource
	 * @param ilECSJupyterRessource $ressource
	 * @throws Exception
	 */
	protected static function doDeleteRessource(ilECSJupyterRessource $ressource)
	{
		switch($ressource->getRessourceType())
		{
			case ilECSJupyterRessource::RES_SUBPARTICIPANT:
				try 
				{
					$connector = new ilECSSubParticipantConnector(
						ilJupyterSettings::getInstance()->getECSServer()
					);
					$connector->deleteSubParticipant($ressource->getRessourceId());
				} 
				catch (Exception $ex) 
				{
					ilLoggerFactory::getLogger('assjupyter')->warning('Deleting subparticipant failed with message: ' . $ex->getMessage());
					throw $ex;
				}
				break;

			case ilECSJupyterRessource::RES_EXERCISE:
				try 
				{
					$connector = new ilECSExerciseConnector(
						ilJupyterSettings::getInstance()->getECSServer()
					);
					$connector->deleteExercise($ressource->getRessourceId());
				} 
				catch (Exception $ex) 
				{
					ilLoggerFactory::getLogger('assjupyter')->warning('Deleting exercise failed with message: ' . $ex->getMessage());
					throw $ex;
				}
				break;

			case ilECSJupyterRessource::RES_EVALUATION:
				try 
				{
					$connector = new ilECSEvaluationConnector(
						ilJupyterSettings::getInstance()->getECSServer()
					);
					$connector->deleteEvaluation($ressource->getRessourceId());
				} 
				catch (Exception $ex) 
				{
					ilLoggerFactory::getLogger('assjupyter')->warning('Deleting evaluation failed with message: ' . $ex->getMessage());
					throw $ex;
				}
				break;

			case ilECSJupyterRessource::RES_SOLUTION:
				try 
				{
					$connector = new ilECSSolutionConnector(
						ilJupyterSettings::getInstance()->getECSServer()
					);
					$connector->deleteSolution($ressource->getRessourceId());
				} 
				catch (Exception $ex) 
				{
					ilLoggerFactory::getLogger('assjupyter')->warning('Deleting solution failed with message: ' . $ex->getMessage());
					throw $ex;
				}
				break;
		}
	}
}
?>
