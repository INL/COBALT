<?php

require_once('lexiconToolBox.php');

chooseDb($_REQUEST['sDatabase']);

$iCorpusId = isset($_REQUEST['iCorpusId']) ? $_REQUEST['iCorpusId'] : false;

if( $iCorpusId )
  showCorpusFiles($_REQUEST['sDatabase'], $_REQUEST['iUserId'],
		  $_REQUEST['sUserName'], $iCorpusId);
else
  print "ERROR: No corpus id given";

?>
