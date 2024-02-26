<?php

use ILIAS\Filesystem\Stream\Streams;
use ILIAS\ResourceStorage\Resource\ResourceNotFoundException;
use ILIAS\ResourceStorage\Services;

class ilJupyterIRSSController
{
    public const JUPYTER_GENERAL_RESOURCE = 'jupyterGeneralResource';
    public const JUPYTER_QUESTION_RESOURCE = 'jupyterQuestionResource';
    public const JUPYTER_SOLUTION_RESOURCE = 'jupyterSolutionResource';

    private Services $resourceStorage;

    public function __construct()
    {
        global $DIC;
        $this->resourceStorage = $DIC->resourceStorage();
    }

    /**
     * @throws Exception
     */
    public function storeJupyterResource(string $resource_content,
                                         string $title = ilJupyterIRSSController::JUPYTER_GENERAL_RESOURCE): string
    {
        $fs = Streams::ofString($resource_content);
        $res_ident = $this->resourceStorage->manage()->stream($fs, ilJupyterStakeholder::getInstance(), $title);
        return $res_ident->serialize();
    }

    /**
     * @throws ResourceNotFoundException
     */
    public function storeJupyterResourceRevision(
        string $resource_content,
        string $resource_id = null,
        string $title = ilJupyterIRSSController::JUPYTER_GENERAL_RESOURCE): string
    {
        if ($resource_id) {
            $res_ident  = $this->resourceStorage->manage()->find($resource_id);
            if ($res_ident !== null) {
                $fs = Streams::ofString($resource_content);
                $this->resourceStorage->manage()->appendNewRevisionFromStream($res_ident, $fs, ilJupyterStakeholder::getInstance(), $title);
                return $resource_id;
            }
            throw new ResourceNotFoundException("Could not find the resource with the id '" . $resource_id . "'." );
        } else {
            return $this->storeJupyterResource($resource_content, $title);
        }
    }

    /**
     * @throws ResourceNotFoundException
     */
    public function readJupyterResource(string $resource_id): string
    {
        $res_ident  = $this->resourceStorage->manage()->find($resource_id);
        if ($res_ident !== null) {
            return $this->resourceStorage->consume()->stream($res_ident)->getStream()->getContents();
        }
        throw new ResourceNotFoundException("Could not find the resource with the id '" . $resource_id . "'." );
    }

    /**
     * @throws ResourceNotFoundException
     */
    public function deleteJupyterResource(string $resource_id): void
    {
        $res_ident  = $this->resourceStorage->manage()->find($resource_id);
        if ($res_ident !== null) {
            $this->resourceStorage->manage()->remove($res_ident, ilJupyterStakeholder::getInstance());
            return;
        }
        throw new ResourceNotFoundException("Could not find the resource with the id '" . $resource_id . "'." );
    }

    public function jupyterResourceExists(string $resource_id): bool
    {
        return $this->resourceStorage->manage()->find($resource_id) !== null;
    }

}