<?php

require_once('lexiconToolBox.php');

chooseDb($_REQUEST['sDatabase']);

printLog("Doing saveTokenAttestations2(" . $_REQUEST['iUserId'] . ", " .
	 $_REQUEST['iId'] . ", '" . $_REQUEST['sMode'] . "', " .
	 $_REQUEST['iWordFormId'] . ", '" . $_REQUEST['sTokenAttestations'] .
	 "', " . $_REQUEST['bVerify'] . ")\n");

saveTokenAttestations2($_REQUEST['iUserId'], $_REQUEST['iId'],
		       $_REQUEST['sMode'], $_REQUEST['iWordFormId'],
		       $_REQUEST['sTokenAttestations'], $_REQUEST['bVerify']);

?>