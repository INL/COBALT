<?php

require_once('lexiconToolBox.php');

chooseDb($_REQUEST['sDatabase']);

addNewTokenAttestation($_REQUEST['iUserId'],
		       $_REQUEST['iWordFormId'],
		       $_REQUEST['sSelecteds'],
		       rawurldecode($_REQUEST['sLemmaTuple']) );

?>