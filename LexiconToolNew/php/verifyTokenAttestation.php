<?php

require_once('./lexiconToolBox.php');

chooseDb($_REQUEST['sDatabase']);

verifyTokenAttestation(// $_REQUEST['iDocumentId'],
		       $_REQUEST['sSelecteds'],
		       $_REQUEST['iWordformId'],
		       // $_REQUEST['iStartPos'], $_REQUEST['iEndPos'],
		       $_REQUEST['iNewValue'], $_REQUEST['iUserId'] );

?>
