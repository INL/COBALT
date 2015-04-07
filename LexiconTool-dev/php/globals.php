<?php

// Global variables
// Most of these need to be set once at installation time to reflect the local
// environment

// Database connection
 
$sDbHostName = "yourhost.inl.nl";
$sDbUserName = "username";
$sDbPassword = "password";


// Projects list

$asProject['lexicon_db1'] = 'Lexicon project #1';
$asProject['lexicon_db2'] = 'Lexicon project #2';
$asProject['some_other_lexicon'] = 'Another lexicon project';



// Set here the project that must be selected by default upon startup
// (set to NULL if none should be selected)

$sChecked = 'lexicon_db1';

// speaking of the original files that will be loaded into Cobalt, we have two possibilities:
// 1. the old way:
//    We'll be using files stored in some directory of the file server.
//    Each time we need to see the content of a file, we'll need to read it from the file server.
// 2. the new way: 
//    When starting a project, we'll be loading files from the file server into the database.
//    After that, each time we need to see the content of a file, we'll get it from the database.
//
// Set the value of aFullDatabaseMode for a given project to
// - true  for the new way (default)
// - false for the old way 

$aFullDatabaseMode['dummy'] = false;



// There is a separate token database.
// It needs to have the same user name/password combinations as the lexicon database.
// This token database can be configured two ways:
//  1. All available lexicon databases use one single token database
//     In this case, this single token database must be declared in variable $sTokenDbName
//     ex: $sTokenDbName = 'some_token_dBname';
//  or:
//  2. Each available database uses its own token database
//     In this case, each token database must be declared in associative array $asTokenDbName
//     To do so, set the lexicon database name as a key, and the token database name as its value.
//     ex: $asTokenDbName['some_lexicon_dBname'] = 'some_token_dBname';

// Name of single token database. It will be only chosen when no token database was
// set for a given lexicon database.
$sTokenDbName = 'dummy';

// Token databases set per lexicon database
$asTokenDbName['lexicon_db1'] = 'lexicon_token_db1';
$asTokenDbName['lexicon_db2'] = 'lexicon_token_db2';
$asTokenDbName['some_other_lexicon'] = 'lexicon_token_db3';




// Custom css files can be set here, if needed
$asCustomCss['dummy'] = 'dummy';
$asCustomCss['lexicon_db1'] = 'lexiconTool_alitheia.css';
$asCustomCss['lexicon_db2'] = 'lexiconTool_latijn.css';


// Document root to save uploaded files to
// NOTE: NO SLASH at the end
$sDocumentRoot = "/uploaddocs";


// Location of directory where uploaded zip files are to be extracted 
// (needed when starting up a new project)
$sZipExtractDir = 'zipExtractDir';

// This variable should hold the absolute path the tokenizer is at.
// NOTE that there is no slash at the end.
$sTokenizerDir = '/Tokenizer_metXMLFunctionaliteit';


// The tokenizer can do things differently depending on the language it deals
// with.
// NOTE that for the default IMPACT tokenizer the value stated here should
// appear exactly the same as extension to the abbr.LANG and apostrof.LANG
// files in the tokenizer directory (see above).
$sTokenizerLanguage = 'ned';


// Under Ubuntu PHP is not permitted to execute external programs (like the
// tokenizer. Therefore it is necessary to include the user the web server
// runs as in the sudo'ers file and execute the tokenizer using 'sudo'.
// Just give the variable an empty string as value if this not required.
$sSudo = '';

// If the next variable is set to a true value, every lemma part of speech the
// the user types in is uppercased (unless the lemma in question already
// exists).
// So, a new lemma 'chair, Nou' will become 'chair, NOU'.
$bUppercaseLemmaPos = TRUE;

// There can be an additional check on the validity of any new analyses the
// user types in.
// Set this to 'true' (NOTE the quotes!) if this is desired. Set to 'false'
// otherwise.
// If set to 'true' a file called js/analysisFormatChecker.js is expected to
// exist which should contain a function called analysisFormatIsValid().
// This function should take a string as input and should return a boolean.
$bCheckAnalysisFormatValidity = 'false';

// Normalize tokens when they are changed.
// In order for this to work there should be a file called
// php/normalizeToken.php that has a function in it called normalizeToken().
$bNormalizeToken = FALSE;

// A pop-up appears when someone wants to edit a word that has recently been
// edited by someone else. 
// What we mean by 'recently' can be set here:
// Right now it is (less than) 5 minutes (=300 seconds)
$iMaxLastViewedDiff1 = 300; // <-- In seconds
// Also when it is less recent, but still *rather* recent, the last view span
// is presented to the user in a more striking(/alarming) manner (color)
// The next figure defines 'rather recent', which is an hour (3600 seconds)
$iMaxLastViewedDiff2 = 3600; // <-- In seconds




// BEGIN OF LOGGING PART

// If you want all the queries the application issues to be logged for a while,
// give this variable a value (an absolute path name).
// Otherwise, make it FALSE.
//
//          \\\\============================================////
//           |||       DON'T FORGET TO TURN THIS OFF        |||
//           ||| The log file VERY quickly becomes VERY big |||
//          ////============================================\\\\
//
$sLogFile = FALSE; 

// We can also choose to log the queries of a particular user only: this is convenient
// when debugging, as we can concentrate on one single flow of operations (instead of
// getting a mess of queries related to different users in the same log).
// To achieve that, we must set the user_id of the user we want to follow.
// Normally, this user is the tester himself (who is testing the proper execution of the
// functionalities he is testing), so the variable to be set is understandably called 'iTesterId'.
// The right user_id to put in here, is to be found in the 'users' table in the database.
// Set to FALSE if no distinctive logging is needed
$iTesterId = FALSE;

// END OF LOGGING PART



// Number of wordforms to show per page:
// In the GUI, this array shows as a dropdown list of values the user can choose from.
// You can use any (natural) number you want, but you should put them in
// quotes, as they are treated as strings.
// The only non-number you can use is 'all' (meaning: show all wordforms).
$aNrOfWordFormsPerPage = array('10', '20', '50', '100', 'all');

// Number of sentences to show per wordform:
// In the GUI, this array shows as a dropdown list of values the user can choose from.
// You can use any (natural) number you want, but you should put them in
// quotes, as they are treated as strings.
// The only non-number you can use is 'all' (meaning: show all sentences).
//
// NOTE that the default (the first value) is also set in lexiconTool.php
$aNrOfSentencesPerWordform = array('10','15','100', '150', '250', '500', 'all');

// Drop down list about the amount of context to be given in the GUI:
// For the context a window of characters is taken in the >>tokenized<< files.
// The numbers in this list determine how big this window is.
// NOTE that there is no way of telling how many words this amounts to.
$aAmountOfContext = array('normal' => 220, 'a bit more' => 420,
			  'a lot more' => 820, 'truckload' => 1800);

// When you start typing in an analysis box, a drop down will appear of the
// lemmata/analyses there already exist in the database.
// Here you can specify how many suggestions you will see at most.
// Do not set this too high, as this will slow things down and hence will lead
// to unexpected/irritating behaviour.
$iNrOfLemmaSuggestions = 20;

// These get filled with the relevant value at runtime (don't modify those two)
$aLanguages = FALSE;
$iTokenDbDatabaseId = FALSE;

// Settings for showing images through a web service
$bDisplayImageInService = TRUE;
$sImageServiceLocation = "http://host/";

// Setting for context sentences in quotation screen (new tab upon clicking on quote in lower screen part)
// set FALSE for sentences without tag information, or TRUE for sentences with tag information
$bFullAnalyses = TRUE;

// Allow editing wordforms in lower part of screen (upon click + CTRL)
// As it appeared that this function is not 100% reliable, using TRUE is sadly discouraged.
$bAllowEditingWordforms = FALSE;

?>
