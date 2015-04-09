<table border=0>
<tr>
<?php

require_once('databaseUtils.php');

chooseDb($_REQUEST['sDatabase']);

$sUserName = isset($_REQUEST['sUserName']) ? $_REQUEST['sUserName'] : false;
$iUserId = isset($_REQUEST['iUserId']) ? $_REQUEST['iUserId'] : false;

if( ! $iUserId || ! $sUserName ) {
  print "User not logged in";
  return;
}

$sSelectQuery = "SELECT corpus_id, name FROM corpora";

$bPrintedSomething = false;
if( ($oResult = doSelectQuery($sSelectQuery)) ) {
  if( mysql_num_rows($oResult) > 0 ) {
    print "<td class=chooseCol>Choose corpus</td>";
    print '<td><form id="chooseCorpusForm" action="./lexiconTool.php" ' .
      "method='POST' " .
      "onChange=\"document.forms['chooseCorpusForm'].submit()\">" .
      "<input type=hidden name=sUserName value='$sUserName'>\n" .
      "<input type=hidden name=iUserId value='$iUserId'>\n" .
      "<select name=sCorpus><option value=0>&nbsp;</option>\n";
    while( ($oRow = mysql_fetch_assoc($oResult)) ) {
      // NOTE that the value is the id and the title with a TAB in between
      print '<option value="' . $oRow['corpus_id'] . "\t" . $oRow['name'] .
	'">' . $oRow['name'] . "</option>\n";
      $bPrintedSomething = true;
    }
    print "</select>\n</form>\n</td>\n";
  }
  mysql_free_result($oResult);
}

if( ! $bPrintedSomething ) {
  print "<td>Dind't find any corpora in the database.</td>\n";
}

?>
</tr>
</table>
