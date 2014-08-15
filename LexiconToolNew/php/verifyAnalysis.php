<?php

require_once('./lexiconToolBox.php');

chooseDb($_REQUEST['sDatabase']);

verifyAnalysis($_REQUEST['iUserId'], $_REQUEST['iAnalyzedWordFormId'],
	       $_REQUEST['bVerify']);

?>