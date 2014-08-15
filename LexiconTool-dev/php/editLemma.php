<?php

require_once('lexiconToolBox.php');

chooseDb($_REQUEST['sDatabase']);

printLog("editLemma(" . $_REQUEST['iUserId'] . ", " .
	 $_REQUEST['iLemmaId'] . ")\n");

editLemma($_REQUEST['iUserId'], $_REQUEST['iLemmaId']);

?>