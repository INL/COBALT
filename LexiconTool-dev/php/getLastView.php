<?php

require_once('lexiconToolBox.php');

chooseDb($_REQUEST['sDatabase']);

printLog("getLastView('" . $_REQUEST['sDatabase'] . "', " .
	 $_REQUEST['iUserId'] . ', ' . $_REQUEST['iWordFormId'] . ")\n");

getLastView($_REQUEST['iWordFormId']);

?>