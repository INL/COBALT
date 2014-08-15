// Very useful function that I found on QuirksMode:
// http://www.quirksmode.org/js/findpos.html
//
// NOTE however that it doesn't work with divs that are scrolled
function findPos(obj) {
  var curleft = curtop = 0;
  if (obj.offsetParent) {
    do {
      curleft += obj.offsetLeft;
      curtop += obj.offsetTop;
    } while (obj = obj.offsetParent);
    return [curleft,curtop];
  }
}

// This function gets you a 'unique' string in the form of the number
// of milliseconds since 1970 January 1st. It can be appended to a URL
// which will then always be unique to the webserver/caching mechanism.
// This pretty stupid trick has to be performed in order to prevent IE
// from never actually carrying out AJAX calls more than once.
// I got the suggestion from http://www.howtoadvice.com/StopCaching
function uniqueString() {
  return "sUnique=" + new Date().getTime();
}

function stripSpaces(sString) {
  // Delete spaces at the front end
  sString = sString.replace(/^\s+/, '');
  // Delete trailing spaces
  return sString.replace(/\s+$/, '');
}

function escapeSingleQuotes(sString) {
  return sString.replace(/\'/g, "&#39;");
}

// Found this one on:
// http://stackoverflow.com/questions/576343/how-to-html-encode-a-string-in-
// javascript-from-a-firefox-extension
//
// Bit strange that javascript doens't have a built-in function I think, but
// well..
function htmlDecode(sString){
  if( sString.length) {
    var oDiv = document.createElement('div');
    oDiv.innerHTML = sString;
    return oDiv.firstChild.nodeValue;
  }
  return '';
}
