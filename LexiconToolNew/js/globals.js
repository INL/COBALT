/******************************************************************************
*                                                                             *
* Global variables                                                            *
* The idea is that all global variables are stated here but for the ones that *
* need initialisation at run time. They are set in a function called          *
* printJavascriptGlobals() at the bottom of lexiconToolBox.php                *
*                                                                             *
******************************************************************************/

// You can set the next value to something else if you don't like the Ctrl key.
// Handle with care!
// Setting it to Esc (27) turns out not to be such a good idea as Esc-clicking
// somehow is tricky in Javascript.
// If you want to know what key has what keyCode (the number you see here),
// try:
// http://www.ryancooper.com/resources/keycode.asp
//
// E.g: Ctrl = 17, F9 = 120
var iCtrlKey = 17;

// The ones below need no setting, so leave these as they are...
var iStartAt= 0;
var sClickedInWindowPart = '';
var iHeldDownKey = 0;
var iLastKeyPressed = 0;
var iSelectedRow = -1;
var iSelectedLemmaSuggestionRow = -1;
var iMaxLemmaSuggestionRow = -1;
var sOpenMenu = '';
var bStartRowWasSolelySelected = false;
var bStartRowWasSelected = false;
var sSelectionDirection = '';
var aSelected = new Array();
var iSelectionStart = -1;
var bInSelection = false;
var iSelectionExtremum = -1;
var iSelectedWordId = -1;
var sPosInputBeforeEditing = '';
var oXmlHttpTextRowRequest = false;
var oXmlHttpLemmaSuggestion = false;
var iStartAtSentence = 0;
// For sorting the sentences
/// worden nu gezet in printJavascriptGlobals()
/// var sSortSentencesBy = false;
/// var sSortSentencesMode= false;
