<?php

require_once('lexiconToolBox.php');

chooseDb($_REQUEST['sDatabase']);

dontShow($_REQUEST['iUserId'], $_REQUEST['iDontShowId'],
	 $_REQUEST['sDontShowMode'], $_REQUEST['bShow'], $_REQUEST['iRowNr'],
	 $_REQUEST['iWordFormId']);

?>