<?php

require_once('lexiconToolBox.php');

chooseDb($_REQUEST['sDatabase']);

$bCaseInsensitivity = (isset($_REQUEST['bCaseInsensitivity'])) ?
($_REQUEST['bCaseInsensitivity'] == 'true') ? TRUE : FALSE : FALSE;
$bSortReverse = (isset($_REQUEST['bSortReverse'])) ?
 ($_REQUEST['bSortReverse'] == 'true') ? TRUE : FALSE : FALSE;
$iStartAt = $_REQUEST['iStartAt'];

$iId = $_REQUEST['iId'];
$sMode = $_REQUEST['sMode'];
$sSortBy = $_REQUEST['sSortBy'];
$sSortMode = $_REQUEST['sSortMode'];
$sFilter = $_REQUEST['sFilter'];
$sLemmaFilter = $_REQUEST['sLemmaFilter'];
$bDoShowAll = $_REQUEST['bDoShowAll'];
$bDoShowCorpus = $_REQUEST['bDoShowCorpus'];
$bDoShowDocument = $_REQUEST['bDoShowDocument'];
$iStepValue = $_REQUEST['iStepValue'];
$iNrOfWordFormsPerPage = $_REQUEST['iNrOfWordFormsPerPage'];


fillWordsToAttest($iId, $sMode,
		  $sSortBy, $sSortMode,
		  $bSortReverse,
		  //rawurldecode($_REQUEST['sFilter']),
		  $sFilter, $bCaseInsensitivity,
		  $_REQUEST['sLemmaFilter'],
		  $bDoShowAll, $bDoShowCorpus,
		  $bDoShowDocument, $iStartAt,
		  $iStepValue, $iNrOfWordFormsPerPage,
		  $_REQUEST['iUserId']);

?>