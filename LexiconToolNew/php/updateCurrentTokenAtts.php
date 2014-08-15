<?php

require_once('./lexiconToolBox.php');

chooseDb($_REQUEST['sDatabase']);

printTokenAttestations($_REQUEST['iDocumentId'], $_REQUEST['iSentenceNr'],
		       $_REQUEST['iWordFormId'], $_REQUEST['iStartPos'],
		       $_REQUEST['iEndPos']);

?>