<#1>
<?php
	return true;
?>
<#2>
<?php
    $res = $ilDB->queryF("SELECT * FROM qpl_qst_type WHERE type_tag = %s",
        array('text'),
        array('assJupyter')
    );
    if ($res->numRows() == 0) {
        $res = $ilDB->query("SELECT MAX(question_type_id) maxid FROM qpl_qst_type");
        $data = $ilDB->fetchAssoc($res);
        $max = $data["maxid"] + 1;

        $affectedRows = $ilDB->manipulateF("INSERT INTO qpl_qst_type (question_type_id, type_tag, plugin) VALUES (%s, %s, %s)",
            array("integer", "text", "integer"),
            array($max, 'assJupyter', 1)
        );
    }
?>
<#3>
<?php
if (!$ilDB->tableExists('il_qpl_qst_jupyter')) {
	$ilDB->createTable('il_qpl_qst_jupyter',
		array(
			'question_fi'	=>	
				array(
					'type'		=> 'integer',
					'length'	=> 4,
					'default'	=> 0,
					'notnull'	=> true
				),
			'jupyter_user'	=>
				array(
					'type'		=> 'text',
					'length'	=> 32,
					'default'	=> null,
					'notnull'	=> false
				),
			'jupyter_token'	=>
				array(
					'type'		=> 'text',
					'length'	=> 128,
					'default'	=> null,
					'notnull'	=> false
				),
			'jupyter_lang'	=>
				array(
					'type'		=> 'text',
					'length'	=> 16,
					'default'	=> '',
					'notnull'	=> false
				),
			'jupyter_exercise'	=>
				array(
					'type'		=> 'clob',
					'default'	=> '',
					'notnull'	=> false
				),
            'jupyter_exercise_id' =>
                array(
                    'type'		=> 'integer',
                    'length'	=> 4,
                    'default'	=> 0,
                    'notnull'	=> TRUE
                ),
            'jupyter_evaluation' =>
                array(
                    'type'		=> 'clob',
                    'default'	=> '',
                    'notnull'	=> FALSE
                ),
            'jupyter_result_storage' =>
                array(
                    'type'		=> 'integer',
                    'length'	=> 1,
                    'default'	=> 0,
                    'notnull'	=> FALSE
                ),
            'jupyter_auto_scoring' =>
                array(
                    'type'		=> 'integer',
                    'default'	=> 0,
                    'length'	=> 1,
                    'notnull'	=> true
                ),
		)
	);

	$ilDB->addPrimaryKey('il_qpl_qst_jupyter', array('question_fi'));
}

?>
