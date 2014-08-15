<?php

require_once('lexiconToolBox.php');

$sDatabase = $_REQUEST['sDatabase'];
$iCorpusId = $_REQUEST['iCorpusId'];
$iDocumentId = $_REQUEST['iDocumentId'];

chooseDb($sDatabase);

removeFileFromCorpus($sDatabase, $iCorpusId, $iDocumentId);