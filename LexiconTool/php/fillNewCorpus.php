<?php

$iUserId = isset($_REQUEST['iUserId']) ? $_REQUEST['iUserId'] : false;
$sUserName = isset($_REQUEST['sUserName']) ? $_REQUEST['sUserName'] : false;

if( ! $iUserId ) {
  print "You appear not to be logged in. Please do...";
  return;
}

require_once('databaseUtils.php');

chooseDb($_REQUEST['sDatabase']);

$sSelectQuery = "SELECT document_id, title FROM documents";

$bPrintedSomething = false;
if( ($oResult = doSelectQuery($sSelectQuery)) ) {
  if( mysql_num_rows($oResult) > 0 ) {
    print "<form name=corpusForm id=corpusForm action='./lexiconTool.php' " .
      "method=POST>" .
      " <input type=hidden name=sUserName value='$sUserName'> " .
      " <input type=hidden name=iUserId value=$iUserId>\n";
?>
<table border=0>
 <tr>
  <td>Name</td>
  <td>
   <input type=text name=sCorpusName>
  </td>
 </tr>
 <tr>
  <td class=chooseCol>Choose files</td>
  <td>
    <!-- NOTE that the id is different form the name on purpose.          -->
    <!-- The former is used by javascript to check its values.            -->
    <!-- The latter is used to ensure that the post variable is an array. -->
   <select id=aDocumentIds name='aDocumentIds[]' multiple size=10>
<?php
    while( ($oRow = mysql_fetch_assoc($oResult)) ) {
      print ' <option value=' . $oRow['document_id'] . ">" . $oRow['title'] .
      "</option>\n";
      $bPrintedSomething = true;
    }
  }
  mysql_free_result($oResult);
?>
</select>
</td>
</tr>
<tr>
 <td></td>
 <td align=right>
  <button type=button
          onClick="javascript: if(checkCorpusForm(document.forms['corpusForm'])) document.forms['corpusForm'].submit();">Create corpus</button>
  </td>
</tr>
</table>
</form>

<?php
    } // End of if( ($oResult = doSelectQuery($sSelectQuery)) )
if(! $bPrintedSomething) {
  print "No files to create a corpus from yet...";
}
?>