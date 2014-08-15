<?php

require_once('./lexiconToolBox.php');

chooseDb($_REQUEST['sDatabase']);

printLog("fillEditLemma(" . $_REQUEST['iUserId'] . ", " .
	 $_REQUEST['iLemmaId'] . ")\n");

fillEditLemma($_REQUEST['iUserId'], $_REQUEST['iLemmaId']);

?>