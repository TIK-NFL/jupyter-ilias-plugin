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
    if ($res->numRows() == 0)
    {
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
if(!$ilDB->tableExists('il_qpl_qst_jupyter'))
{
	$ilDB->createTable('il_qpl_qst_jupyter',
		array(
			'question_fi'	=>	
				array(
					'type'		=> 'integer',
					'length'	=> 4,
					'default'	=> 0,
					'notnull'	=> true
				),
			'jupyter_sub_id'	=>
				array(
					'type'		=> 'integer',
					'length'	=> 4,
					'default'	=> 0,
					'notnull'	=> true
				),
			'jupyter_cookie'	=>
				array(
					'type'		=> 'text',
					'length'	=> 128,
					'default'	=> null,
					'notnull'	=> false
				),
			'jupyter_width'	=>
				array(
					'type'		=> 'integer',
					'length'	=> 4,
					'default'	=> 0,
					'notnull'	=> true
				),
			'jupyter_height'	=>
				array(
					'type'		=> 'integer',
					'length'	=> 4,
					'default'	=> 0,
					'notnull'	=> true
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
				)
		)
	);

	$ilDB->addPrimaryKey('il_qpl_qst_jupyter', array('question_fi'));
}
?>

<#4>
<?php

if(!$ilDB->tableColumnExists('il_qpl_qst_jupyter','jupyter_exercise_id'))
{
	$ilDB->addTableColumn(
			'il_qpl_qst_jupyter',
			'jupyter_exercise_id',
			array(
				'type'		=> 'integer',
				'length'	=> 4,
				'default'	=> 0,
				'notnull'	=> TRUE
			)
		);
}
?>

<#5>
<?php

if(!$ilDB->tableColumnExists('il_qpl_qst_jupyter','jupyter_evaluation'))
{
	$ilDB->addTableColumn(
			'il_qpl_qst_jupyter',
			'jupyter_evaluation',
			array(
				'type'		=> 'clob',
				'default'	=> '',
				'notnull'	=> FALSE
			)
		);
}
?>
<#6>
<?php

if(!$ilDB->tableColumnExists('il_qpl_qst_jupyter','jupyter_result_storage'))
{
	$ilDB->addTableColumn(
			'il_qpl_qst_jupyter',
			'jupyter_result_storage',
			array(
				'type'		=> 'integer',
				'length'	=> 1,
				'default'	=> 0,
				'notnull'	=> FALSE
			)
		);
}
?>
<#7>
<?php
;
?>

<#8>
<?php
;
?>

<#9>
<?php

if(!$ilDB->tableExists('il_qpl_qst_jupyter_res'))
{
	$ilDB->createTable('il_qpl_qst_jupyter_res',
		array(
			'id'	=>	
				array(
					'type'		=> 'integer',
					'length'	=> 4,
					'default'	=> 0,
					'notnull'	=> true
				),
			'res_id'	=>
				array(
					'type'		=> 'integer',
					'length'	=> 4,
					'default'	=> 0,
					'notnull'	=> true
				),
			'ecs_res'	=>
				array(
					'type'		=> 'text',
					'length'	=> 128,
					'default'	=> null,
					'notnull'	=> false
				),
			'create_dt'	=>
				array(
					'type'		=> 'integer',
					'length'	=> 4,
					'default'	=> 0,
					'notnull'	=> true
				)
			)
		);
	
	$ilDB->addPrimaryKey('il_qpl_qst_jupyter_res', array('id'));
	$ilDB->createSequence('il_qpl_qst_jupyter_res');

}
?>
<#10>
<?php

if(!$ilDB->tableColumnExists('il_qpl_qst_jupyter','jupyter_auto_scoring'))
{
	$ilDB->addTableColumn(
			'il_qpl_qst_jupyter',
			'jupyter_auto_scoring',
			array(
				'type'		=> 'integer',
				'default'	=> 0,
				'length'	=> 1,
				'notnull'	=> true
			)
		);
}
?>
<#11>
<?php
if($ilDB->tableColumnExists('il_qpl_qst_jupyter', 'jupyter_width'))
{
	$ilDB->dropTableColumn('il_qpl_qst_jupyter', 'jupyter_width');
}

if($ilDB->tableColumnExists('il_qpl_qst_jupyter', 'jupyter_height'))
{
	$ilDB->dropTableColumn('il_qpl_qst_jupyter', 'jupyter_height');
}

$jupyterSettings = new ilSetting('ass_jupyter');
$jupyterSettings->delete('width');
$jupyterSettings->delete('height');

$jupyterSettings->delete('active');
?>
