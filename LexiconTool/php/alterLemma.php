<?php

require_once('lexiconToolBox.php');

chooseDb($_REQUEST['sDatabase']);

$iLemmaId = $_REQUEST['iLemmaId'];
$sModernLemma= urldecode($_REQUEST['sModernLemma']);
$sPartOfSpeech = urldecode($_REQUEST['sPartOfSpeech']);
$sGloss= urldecode($_REQUEST['sGloss']);
$iLanguageId = $_REQUEST['iLanguageId'];

alterLemma($iLemmaId, $sModernLemma, $sPartOfSpeech, $sGloss, $iLanguageId);

?>