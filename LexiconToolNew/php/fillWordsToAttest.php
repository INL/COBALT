<?php

require_once('./lexiconToolBox.php');

chooseDb($_REQUEST['sDatabase']);

$bCaseInsensitivity = (isset($_REQUEST['bCaseInsensitivity'])) ?
($_REQUEST['bCaseInsensitivity'] == 'true') ? TRUE : FALSE : FALSE;
$bSortReverse = (isset($_REQUEST['bSortReverse'])) ?
 ($_REQUEST['bSortReverse'] == 'true') ? TRUE : FALSE : FALSE;

fillWordsToAttest($_REQUEST['iId'], $_REQUEST['sMode'],
		  $_REQUEST['sSortBy'], $_REQUEST['sSortMode'],
		  $bSortReverse,
		  //rawurldecode($_REQUEST['sFilter']),
		  $_REQUEST['sFilter'], $bCaseInsensitivity,
		  $_REQUEST['sLemmaFilter'],
		  $_REQUEST['bDoShowAll'], $_REQUEST['bDoShowCorpus'],
		  $_REQUEST['bDoShowDocument'], $_REQUEST['iStartAt'],
		  $_REQUEST['iStepValue'], $_REQUEST['iNrOfWordFormsPerPage'],
		  $_REQUEST['iUserId']);

?>