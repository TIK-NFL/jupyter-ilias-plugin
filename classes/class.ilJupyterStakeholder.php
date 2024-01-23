<?php

use \ILIAS\ResourceStorage\Stakeholder\ResourceStakeholder;
use \ILIAS\ResourceStorage\Stakeholder\AbstractResourceStakeholder;

class ilJupyterStakeholder extends AbstractResourceStakeholder implements ResourceStakeholder
{

    private static $instance;

    private int $owner;

    public function __construct()
    {
        global $DIC;
        $this->owner = $DIC->user()->getId();
    }

    public function getId(): string
    {
        return "assjupyter";
    }

    public function getOwnerOfNewResources(): int
    {
        return $this->owner;
    }

    public static function getInstance(): ilJupyterStakeholder
    {
        if (!self::$instance) {
            self::$instance = new ilJupyterStakeholder();
        }
        return self::$instance;
    }
}