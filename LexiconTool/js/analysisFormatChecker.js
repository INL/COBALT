// Check if what the user typed in complies to some format.
// This may differ from language to language, project to project, etc. which is
// why it is in a separate file.
//
// If you set bCheckAnalysisFormatValidity to 'true' (in php/globals.php) then
// the tool assumes this file (called analysisFormatChecker,js) exists and that
// a function called analysisFormatIsValid() exists that has a string as input
// and returns a boolean.

// Here is the implementation for Slovene
//
// An analysis:
// - has exactly one lemma
// - has exactly one modern day equivalent (mform)
// - has exactly one PoS from the closed tagset list at
//    http://nl.ijs.si/impact/msd/html-sl/#msd.index.msds
// - can have some further info, which is free
//
function analysisFormatIsValid(sString) {
  var sPatSingle = ' *[^ <>\,]+ *, <[^ <>\,]+> *, *(N(c[mfn]|p[mfn])|V(a|m[epb])|A(g[pcs]|[sp]p)|R(g[pcs]|r)|P|M[drl]|S|C|Q|I|Y|X[ftp]?) *(\,.+)?';
  var rePat = new
    RegExp('^(' + sPatSingle + '|' + sPatSingle + '(&' + sPatSingle + ')+)$');

  return sString.match(rePat);
}

function analysisFormatIsValid__old(sString) {
  var sPatSingle = ' *[^ <>\,]+ *, <[^ <>\,]+> *, *(N(c[mfn]|p[mfn])|V(a|m[epb])|A(g[pcs]|[sp]p)|R(g[pcs]|r)|P|M[drl]|S|C|Q|I|Y|X[ftp]?) *(\,.+)?';
  var rePatSingle = new RegExp('^' + sPatSingle + '$');

  if(sString.match(rePatSingle))
    return true;
  else {
    // NOTE that we don't allow (yet) for modern wordforms in multiple analyses.
    var sPatDouble = ' *[^ <>\,]+ *, *(N(c[mfn]|p[mfn])|V(a|m[epb])|A(g[pcs]|[sp]p)|R(g[pcs]|r)|P|M[drl]|S|C|Q|I|Y|X[ftp]?) *(\,.+)?';
    var rePatDouble =
      new RegExp('^(' + sPatDouble + '(&' + sPatDouble + ')+)$');
    return sString.match(rePatDouble);
  }
}

