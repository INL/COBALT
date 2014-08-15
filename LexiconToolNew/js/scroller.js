// Globals for these functions
var bBusyScrolling = false;
var iScrollDistance = 0;
var iTimeOutId = 0;

// On every mouse move in the scroller div this function is called.
// When we are already scrolling (i.e. bBusyScrolling == true) we just update
// the scroll distance (depending on how far the mouse is from the center
// of the scroller div).
//
function startScrolling(e) {
  // The mouse x coordinate minus the middle of the scroller div
  // Division by 5 to get some slower scrolling
  //
  // The coordinates are always calculated again because the screen may have
  // been resized in the meanwhile
  iScrollDistance =
    ((getMouseX(e) - 
      (parseInt(findPos(document.getElementById('scroller'))[0]) + 250)) / 5);

  if( ! bBusyScrolling) {
    // Go into recursion (just once)
    bBusyScrolling = true;
    doScrolling('scroller');
  }
}

function startSentenceScrolling(e) {
  // The mouse x coordinate minus the middle of the scroller div
  // Division by 5 to get some slower scrolling
  //
  // The coordinates are always calculated again because the screen may have
  // been resized in the meanwhile
  if( bBusyScrolling ) {
    iScrollDistance =
      ((getMouseX(e) - 
	(parseInt(findPos(document.getElementById('sentenceScroller'))[0]) +
	 250)) / 5);
  }
  else { /// if( ! bBusyScrolling) {
    // Go into recursion (just once)
    bBusyScrolling = true;
    doScrolling('sentenceScroller');
  }
}

// Stop the recursion
function stopScrolling() {
  clearTimeout(iTimeOutId);
  bBusyScrolling = false;
}

// Here is the actual scrolling of the div.
// We allow for some room in the middle of the div where no scrolling takes
// place (otherwise you'd have to keep the mouse *exactly* in the middle for
// the scrolling to stop
//
function doScrolling(sScroller) {
  if( (iScrollDistance < -4) || (iScrollDistance > 4)) {
    var oScroller = document.getElementById(sScroller);
    if( oScroller )
      oScroller.scrollLeft += iScrollDistance;
  }

  // Recurse
  iTimeOutId = setTimeout('doScrolling(\'' + sScroller + '\')', 100);
}

// Found this function on
// http://www.quirksmode.org/js/events_properties.html
//
// It is adjusted because we only need the X coordinate
//
function getMouseX(e) {
  var iPosX = 0;
  if (!e) var e = window.event;
  if (e.pageX )
    iPosX = e.pageX;
  else
    if (e.clientX )
      iPosX = e.clientX + document.body.scrollLeft
	+ document.documentElement.scrollLeft;
  return iPosX;
}

// This functions scrolls to the right position, assuming you start off at
// the beginning of the list.
// Except when the the iNr argument is 0, in which case you just jump to the
// start.
function scrollToNr(oScroller, iNr, sScrollSpan) {
  if( iNr == 0)
    oScroller.scrollLeft = 0;
  else {
    var oSpan = document.getElementById(sScrollSpan + iNr);
    var iNrLeft = findPos(oSpan)[0];
    
    var iMiddle = parseInt(findPos(oScroller)[0])+ 250;

    oScroller.scrollLeft += (iNrLeft + (oSpan.offsetWidth / 2)) - iMiddle;
  }
}

function setScrollerBackground(iLastSpanNr) {
  var oSpan = document.getElementById('scrollSpan_' + iLastSpanNr);
  var iLastSpanRightSide = findPos(oSpan)[0] + oSpan.offsetWidth;

  var iScrollerRightSide =
    parseInt(findPos(document.getElementById('scroller'))[0]) + 500;

  var oScrollerCol = document.getElementById('scrollerCol');
  if( iLastSpanRightSide > iScrollerRightSide) {
    oScrollerCol.style.background =
      "url('./img/bgScroller.png') top no-repeat";
  }
  else {
    oScrollerCol.style.background = "";

    // Also, hide the "jump to begin/end" buttons
    document.getElementById('scrollJumpToBegin').innerHTML = '';
    document.getElementById('scrollJumpToEnd').innerHTML = '';
    document.getElementById('scrollJumpToPrev').innerHTML = '';
    document.getElementById('scrollJumpToNext').innerHTML = '';
  }
}

function setSentenceScrollerBackground(iLastSpanNr) {
  var oSpan = document.getElementById('scrollSentenceSpan_' + iLastSpanNr);
  var iLastSpanRightSide = findPos(oSpan)[0] + oSpan.offsetWidth;

  var iScrollerRightSide =
    parseInt(findPos(document.getElementById('sentenceScroller'))[0]) + 500;

  var oScrollerCol = document.getElementById('sentenceScrollerCol');
  if( iLastSpanRightSide > iScrollerRightSide)
    oScrollerCol.style.background= "url('./img/bgScroller.png') top no-repeat";
  else {
    oScrollerCol.style.background = "";

    // Also, hide the "jump to begin/end" buttons

    document.getElementById('sentenceScrollJumpToBegin').innerHTML = '';
    document.getElementById('sentenceScrollJumpToEnd').innerHTML = '';
    document.getElementById('sentenceScrollJumpToPrev').innerHTML = '';
    document.getElementById('sentenceScrollJumpToNext').innerHTML = '';
  }
}
