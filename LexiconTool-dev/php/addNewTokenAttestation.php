<?php

require_once('lexiconToolBox.php');

chooseDb($_REQUEST['sDatabase']);

//$sGloss = (isset($_REQUEST['sGloss']))
//     ? addslashes(rawurldecode($_REQUEST['sGloss'])) : false;

addNewTokenAttestation($_REQUEST['iUserId'],
		       /// $_REQUEST['iDocumentId'], $_REQUEST['iSentenceNr'],
		       $_REQUEST['iWordFormId'],
		       /// $_REQUEST['iStartPos'], $_REQUEST['iEndPos'],
		       $_REQUEST['sSelecteds'],
		       ///
		       rawurldecode($_REQUEST['sLemmaTuple']) );

?>