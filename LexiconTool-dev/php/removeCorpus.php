<?php

require_once('lexiconToolBox.php');

$sDatabase = $_REQUEST['sDatabase'];
$iCorpusId = $_REQUEST['iCorpusId'];

chooseDb($sDatabase);

removeCorpus($sDatabase, $iCorpusId);

?>