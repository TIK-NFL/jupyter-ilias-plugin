<?php


use exceptions\ilCurlErrorCodeException;
use exceptions\JupyterSessionException;
use exceptions\JupyterUnreachableServerException;

class ilJupyterDBController
{
    const JUPYTER_INSTANCE_TABLE = 'il_qpl_qst_jupyter_ntb';

    public function addTemporarySessionRecord($user_credentials) {
        global $ilDB;

        $ilDB->insert(
            self::JUPYTER_INSTANCE_TABLE,
            array(
                'jupyter_user'	=> array('text', (string) $user_credentials['user']),
                'jupyter_token'	=> array('text', (string) $user_credentials['token']),
                'update_timestamp'	=> array('integer', time()),
            )
        );
    }

    public function deleteTemporarySessionRecord($jupyter_user): bool
    {
        global $ilDB;
        $number_affected_rows = $ilDB->manipulateF("DELETE FROM " . self::JUPYTER_INSTANCE_TABLE . " WHERE jupyter_user = %s", array('string'), array($jupyter_user));
        return $number_affected_rows == 1;
    }

    public function updateTemporarySessionUpdateTimestamp($jupyter_user, $update_timestamp): bool
    {
        global $ilDB;
        $number_affected_rows = $ilDB->manipulateF("UPDATE " . self::JUPYTER_INSTANCE_TABLE . " SET update_timestamp = %s WHERE jupyter_user = %s", array('integer', 'string'), array($update_timestamp, $jupyter_user));
        return $number_affected_rows == 1;
    }

    public function getTemporarySessionRecords() {
        global $ilDB;

        $result = $ilDB->queryF("SELECT * FROM " . self::JUPYTER_INSTANCE_TABLE, array(), array());
        $records = array();
        while ($record = $ilDB->fetchAssoc($result)) {
            $records[] = $record;
        }
        return $records;
    }
}