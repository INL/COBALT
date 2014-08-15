<?php

require_once('databaseUtils.php');

$sDatabase =  $_REQUEST['sDatabase'];
$iWordformId =  $_REQUEST['iWordformId'];
$sRowsData =  $_REQUEST['sRowsData'];

updateAnalysesOfSelectedRows($sDatabase, $iWordformId, $sRowsData)
?>
