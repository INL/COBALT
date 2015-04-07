package impactok::impactok;

use strict;

use HTML::Entities;

my %hTokenIndex = (onset => 0, offset => 1, iOriginalOnset => 2,
		   iOriginalOffset => 3, word => 4, string => 5,
		   sOriginalToken => 6,
		   iPosX => 7, iPosY => 8, iPosHeight => 9, iPosWidth => 10
		  );

my $PING = "_PING_";
my $PANG = "_PANG_";
my %pingpang = ( "(" => $PING, ")" => $PANG);

sub new {
  my ($class, %hOptions) = @_;

  my $self = \%hOptions;
  bless $self, $class;

  die "Couldn't construct impactok::impactok object without language.\n"
    unless(exists($self->{sLanguage}) && defined($self->{sLanguage}) );

  $self->initialize();

  return $self;
}

# Methods #####################################################################

sub initialize {
  my ($self) = @_;

  $self->{bMapToLowercase} = undef;

  # Token separator class.
  # These characters will NOT end op in the resulting tokens. So it is highly
  # advisable to use only white space characters here.
  $self->{reSepChars} = qr/[ \t\r\n\240]/;

  # We set the break characters depending on the language
  # A break character is a character that marks the end of a token, regardless
  # of whether there is a space or not behind it.
  # So 'hello:he said' will become 'hello', 'he' and 'said' (in stead of
  # 'hello:he' and 'said')
  # 2011-11-04: Added slash '/'
  $self->{reBreakChars} = ($self->{sLanguage} eq 'esp')
    ? qr/[\?¿\!¡«»";:\(\)\[\]\{\}\,\/]/ : qr/[\/]/;

  # Punctuation class.
  # You can specify two separate classes for the start and the end of a token
  # though usually these will be the same.
  # These characters will be omitted from for the first ('normalized') column
  # but they *will* end up in the second column.
  # NOTE that they are dependent on the language (Spanish treats brackets
  # differently).
  #
  # NOTE that THERE IS NO dash (-) here!!
  # NOTE that THERE IS NO single quote (') here!!
  # Single quotes are treated differently because of things like 't in Dutch.
  # (cf. apostrof.ned file...)
  # Dashes are treated differently in Dutch, so we add them per language.
  #
  # NOTE that all those weird quotes (including \x{}'s) at the end are all
  # different utf-8 characters. The back-quote is the one between the '¡' and
  # the (escaped) normal double quote.
  #
  # 2011-07-07: Added '*' and '+'
  # 2011-11-04: Added slash '/'
  $self->{sPunctuationChars} =
    " \*\+\n«»¿¡`\"\?\!;:\,\.=΄˝΄‘’‚‛“”„‘’\x{2018}\x{2019}\x{201E}\x{201D}\\[\\]\\{\\}\/";

  # TK: 24 jun 2011
  # Moved square and curly brackets from only 'esp' to general punctuation class

  # NOTE that this is punctuation characters AND tags
  # NOTE there MUST be a $1.
  $self->{sPunctAtStart} = ($self->{sLanguage} eq 'esp')
    ? "((?:<[^<>]*>|[\\(\\)$self->{sPunctuationChars}-])+)"
      : "((?:<[^<>]*>|[$self->{sPunctuationChars}])+)" ;
  $self->{rePunctAtStart} = qr/^$self->{sPunctAtStart}/;

  # NOTE that this is punctuation characters AND tags
  # NOTE that this one ALSO MUST HAVE $1.
  $self->{sPunctAtEnd} = ($self->{sLanguage} eq 'esp')
    ? "((?:<[^<>]*>|[\\(\\)$self->{sPunctuationChars}'-])+)"
      : "((?:<[^<>]*>|[$self->{sPunctuationChars}'])+)" ;
  $self->{rePunctAtEnd} = qr/$self->{sPunctAtEnd}$/;

  $self->{reTag} = qr/<[\w\/][^>]*>/;
}

# Here we make an array of arrays that lists all the tokens in the file
# plus their position.
# This used to be called splitAtChar
sub makeTokenArray {
  my ($self, $sDoc, $iStartPos, $arCoordinates) = @_;

  # An experiment.
  # We replace initial and end-tags tags with their length in spaces.
  # Tags within a word are maintained.
  # So, there is some code below (above) that deals with tags at the begin/end
  # of a word, but that isn't needed anymore.
  # Remove tag at start of doc
  $sDoc =~ s/^(($self->{reTag})+)/' ' x length($1)/e;
  # Remove tags preceded by whitespace (and possible at the start of a word)
  $sDoc =~ s/(\s)(($self->{reTag})+)/$1 . ' ' x length($2)/eg;
  # Remove tags followed by whitespace (and possible at the end of a word)
  $sDoc =~ s/(($self->{reTag})+)(\s)/' ' x length($1) . $3/eg;

  # Empty it if there was anything in it...
  $self->{arTokens} = [];
  my $sPart = '';
  my ($c, $cPrev) = ('', '');
  my ($iOnset, $iPos) = ($iStartPos, 0);
  my $bInWhiteSpace = undef;
  my %hState = (sState => ''); # Needed for frAlternativeAddToken
  for (my $iPos = 0; $iPos < length($sDoc); $iPos++) {
    $c = substr($sDoc, $iPos, 1);

    if( $c =~ /$self->{reSepChars}/ ) {
      unless($bInWhiteSpace) {
	# Save the part so far
	$self->addToken($iOnset, $sPart, $arCoordinates, \%hState);
        $iOnset = $iStartPos + $iPos;
        $sPart = $c;
      }
      $bInWhiteSpace = 1;
    }
    elsif( $self->{reBreakChars} &&
	   ($cPrev =~ /$self->{reBreakChars}/) &&
	   ($c !~ /$self->{reBreakChars}/) &&
	   ($sPart =~ /\w/)       # See if the part isn't just break chars
	 ) {
      # If the previous character was break character, and the current one
      # isn't, save whatever we had if it is interesting.
      $self->addToken($iOnset, $sPart, $arCoordinates, \%hState);
      $sPart = $c;
      $iOnset = $iStartPos + $iPos;
    }
    else {
      if ($bInWhiteSpace) {
        $sPart = '';
 	$iOnset = $iStartPos + $iPos;
      }
      $sPart .= substr($sDoc, $iPos, 1);
      $bInWhiteSpace = undef;
    }
    $cPrev = $c; # Remember the last character we saw
  }
  $self->addToken($iOnset, $sPart, $arCoordinates, \%hState)
    if( ! $bInWhiteSpace );
}

sub addToken {
  my ($self, $iOnset, $sPart, $arCoordinates, $hrState) = @_;

  return unless(length($sPart));

  if( exists($self->{frAlternativeAddToken}) ) {
    $self->{frAlternativeAddToken}->($self->{arTokens}, $iOnset, $sPart,
				     $arCoordinates, $hrState);
  }
  else {
    my $iOffset = $iOnset + (length($sPart));
    # Three times $sPart, and twice onset and offset.
    # The first onnset/offset will be altered in making the canonical wordform.
    # the first part will become the canoical wordfrom, the second the word
    # 'as is' and the third is the original token (possibly only punctuation).
    # The second onset/offset pair and the third $sPart
    # will not (SHOULD not!) be altered during the following steps.
    my $arNewToken = ($arCoordinates) ?
      [$iOnset, $iOffset, $iOnset, $iOffset, $sPart, $sPart, $sPart,
       @$arCoordinates]
	: [$iOnset, $iOffset, $iOnset, $iOffset, $sPart, $sPart, $sPart];
    push (@{$self->{arTokens}}, $arNewToken );
  }
}

sub cleanse_word {
  my ($self, $iWordNr) = @_;

  $self->{arTokens}->[$iWordNr][$hTokenIndex{"word"}] =~ s/&[rl]dquor;//g;
  ### Begin/end tags worden gedaan in handle_punct_at_start en
  ### handle_punct_at_end
  $self->{arTokens}->[$iWordNr][$hTokenIndex{"word"}] =~ s!<[^<>]+>!!g;

  $self->{arTokens}->[$iWordNr][$hTokenIndex{"word"}]
    = decode_entities($self->{arTokens}->[$iWordNr][$hTokenIndex{"word"}]);

  $self->{arTokens}->[$iWordNr][$hTokenIndex{"word"}] =
    lc($self->{arTokens}->[$iWordNr][$hTokenIndex{"word"}])
      if( $self->{bMapToLowercase} );
}

sub analyseTokenArray {
  my ($self) = @_;

  # Adjust the tokenization according to more specific rules
  for (my $iWordNr = 0; $iWordNr < scalar(@{$self->{arTokens}}); $iWordNr++) {
    $self->handle_hyphenation($iWordNr);
    $self->handle_punct_at_start($iWordNr);
    $self->handle_punct_at_end($iWordNr);
    $self->handle_brackets($iWordNr);

    $self->cleanse_word($iWordNr);
  }
}

sub handle_hyphenation {
  my ($self, $iWordNr) = @_;

  # I don't know how to do $#array for an array reference... :-(
  my $iLastIndex = scalar(@{$self->{arTokens}}) - 1;
  if (($iWordNr < $iLastIndex ) &&	# not the last word of a doc
      # string was at the end of a line
      (($self->{arTokens}->[$iWordNr][$hTokenIndex{"string"}] =~ m!()?\n$!) ||
       # or next string was at the start of a line
       ($self->{arTokens}->[$iWordNr + 1][$hTokenIndex{"string"}] =~ m!^()?\n!)
      ) &&
      # word ends with a hyphen preceded by a letter
      ($self->{arTokens}->[$iWordNr][$hTokenIndex{"word"}] =~ m!\S[­-]$!)) {
    my @first = @{$self->{arTokens}->[$iWordNr]};
    my @second = @{$self->{arTokens}->[$iWordNr + 1]};
    splice (@{$self->{arTokens}}, $iWordNr, 2,
	    [$first[$hTokenIndex{"onset"}], $second[$hTokenIndex{"offset"}],
	     $first[$hTokenIndex{"word"}]. $second[$hTokenIndex{"word"}],
	     $first[$hTokenIndex{"string"}] . $second[$hTokenIndex{"string"}]]
	   );
  }
}

# Word starts with punctuation # don't separate ,, ... -- '' etc.
sub handle_punct_at_start {
  my ($self, $iWordNr) = @_;

  # First try the punctuation
  $self->{arTokens}->[$iWordNr][$hTokenIndex{"word"}] =~
    s/$self->{rePunctAtStart}//;
  $self->{arTokens}->[$iWordNr][$hTokenIndex{"onset"}] += length($1)
    if ($1 && ($1 ne "") );

  # 's morgens
  if((substr($self->{arTokens}->[$iWordNr][$hTokenIndex{"word"}], 0, 1) eq "'")
     &&
     (!exists($self->{hrApostrophes}->{$self->{arTokens}->[$iWordNr][$hTokenIndex{"word"}]}))) {
    substr($self->{arTokens}->[$iWordNr][$hTokenIndex{"word"}], 0, 1) = "";
    $self->{arTokens}->[$iWordNr][$hTokenIndex{"onset"}]++;
  }
}

# Word ends in punctuation # don't separate ,, ... -- '' 5,- etc.
sub handle_punct_at_end {
  my ($self, $iWordNr) =  @_;

  # etc. e.g.
  unless(exists($self->{hrAbbreviations}->{$self->{arTokens}->[$iWordNr][$hTokenIndex{"word"}]})
	 # e.g. s.v.o.p.
	 ||
	 ($self->{arTokens}->[$iWordNr][$hTokenIndex{"word"}] =~ /^(\w\.)+$/)
	 # word contains no vowels and ends with a period e.g. svp.
	 ||
	 ($self->{arTokens}->[$iWordNr][$hTokenIndex{"word"}] =~
	  /^[bcdfghjklmnpqrstvwxz]+\.$/i)
	) {
    # Rather inelegant way of avoiding initials to loose their dot
    # NOTE that there can be brackets/punctuation characters in front of the
    # initial...
    my ($bIsInitial, $iPunctLength) = (undef, 0);
    if( $self->{arTokens}->[$iWordNr][$hTokenIndex{"word"}] =~
	/^([$self->{sPunctuationChars}\(\)\<\>\[\]]*)[A-ZÄÀÁÃËÉÈÏÍÌÖÕÓÒÜÙÚ]\./
      ) {
      $bIsInitial = 1;
      $iPunctLength = length($1);
    }

    # Here is the actual deletion of interpunction
    $self->{arTokens}->[$iWordNr][$hTokenIndex{"word"}] =~
      s/$self->{rePunctAtEnd}//;
    $self->{arTokens}->[$iWordNr][$hTokenIndex{"offset"}] -= length($1)
      if ($1 && ($1 ne "") );
    # This is the inelegant bit, here we restore the dot again
    if( $bIsInitial &&
	(length($self->{arTokens}->[$iWordNr][$hTokenIndex{"word"}]) ==
	 ($iPunctLength + 1) ) ) {
      $self->{arTokens}->[$iWordNr][$hTokenIndex{"word"}] .= '.';
      $self->{arTokens}->[$iWordNr][$hTokenIndex{"offset"}]++;
    }
  }
}

sub handle_brackets {
  my ($self, $iWordNr) = @_;

  my ($sNewWord, $iPre, $iPost) =
    $self->removeBrackets($self->{arTokens}->[$iWordNr][$hTokenIndex{"word"}]);
  $self->{arTokens}->[$iWordNr][$hTokenIndex{"word"}] = $sNewWord;
  $self->{arTokens}->[$iWordNr][$hTokenIndex{"onset"}] += $iPre;
  $self->{arTokens}->[$iWordNr][$hTokenIndex{"offset"}] -= $iPost;
}

# Read in the known abbreviations
sub setAbbreviations {
  my ($self, $sPath) = @_;

  my $sLine;
  my $sAbbreviationsFile = "$sPath/abbr.$self->{sLanguage}";
  open(FH,"<$sAbbreviationsFile")
    or die "Cannot open $sAbbreviationsFile: $!\n";
  while( defined($sLine=<FH>) ) {	        # For each line
    next if (substr($sLine,0,1) eq '#'); # Except comment
    chomp($sLine);
    $self->{hrAbbreviations}->{$sLine} = 1;
  }
  close(FH);
}

# Read in the known words starting with "'"
# E.g. 's morgens => 's morgens
sub setApostrophes {
  my ($self, $sPath) = @_;

  my $sLine;
  my $sApostropheFile = "$sPath/apostrof.$self->{sLanguage}";
  open(FH,"<$sApostropheFile") or
    die "Cannot open $sApostropheFile: $!\n";
  while( defined($sLine=<FH>) ) {	        # For each line
    next if (substr($sLine,0,1) eq '#'); # Except comment
    chop($sLine);
    $self->{hrApostrophes}->{$sLine} = 1;
  }
  close(FH);
}

# This sub is meant to leave the brackets in constructs like 'waar(voor)'
# but remove them in '(waarvoor)'.
sub removeBrackets {
  my ($self, $token) = @_;

  # No need to go through all the trouble of there are no brackets...
  return ($token,0,0) if ($token !~ /[\(\)]/);

  $token =~ s/(\w.*?)\(/$1$PING/g;
  $token =~ s/\)(.*?\w)/$PANG$1/g;

  my @stack;
  my %unsafe;

  # now: brackets matching an unremovable bracket are unremovable
  while ($token =~ /(\(|\)|($PING)|($PANG))/g) {
    my $match = $&;
    my $p = pos $token;
    if ($match eq "(" || $match eq $PING) {
      push(@stack,"$p:$match");
    }
    elsif ($match eq ")" || $match eq $PANG) {
      my $z = pop @stack;
      if($z) {
	my ($mpos, $mbrack) = split(/:/,$z);
	### TK: 11 jan 2011 Volgens mij klopt dit niet. In dit geval is het
	### juist wel safe
	### if ($mbrack eq $PING) {
	###  $unsafe{$p}++;
	### }
	### els
	if ($match eq $PANG && $mbrack eq "(") {
	  $unsafe{$mpos}++;
	}
      }
    }
  }

  my @chars = split(//,$token);
  for (my $i=0; $i < @chars; $i++) {
    if ($unsafe{$i+1}) {
      $chars[$i] = $pingpang{$chars[$i]};
    }
  }
  $token = join("",@chars);
  my $pre=0;
  my $post = 0;
  $token =~ s/^.*\(+/$pre = length($&); ""/e;
  $token =~ s/\)+.*/$post = length($&); ""/e;
  $token =~ s/$PING/(/g;
  $token =~ s/$PANG/)/g;
  return ($token,$pre,$post);
}

sub printTokenArray {
  my ($self) = @_;

  die "ERROR: Before printing you should set a file handle for " .
    "printing (called \$self->{fhOut}) in the impactok object."
      unless(exists($self->{fhOut}));
  my $fhOut = $self->{fhOut};
  my $sIsNotAWordformInDb;
  foreach my $arWrd ( @{$self->{arTokens}} ) {
    if( length($arWrd->[$hTokenIndex{"word"}]) ) {
      print $fhOut $arWrd->[$hTokenIndex{"word"}] . "\t" . # Canonical form
	# Word as it appeared in the text
	noNewlines($arWrd->[$hTokenIndex{"string"}]) .
	  "\t" . $arWrd->[$hTokenIndex{"onset"}] . "\t" .
	    $arWrd->[$hTokenIndex{"offset"}];
      # NOTE: always a 5th column (albeit empty in this case)
      print $fhOut "\t";
    }
    else { # No length, that means that it were only punctuations marks etc.
      print $fhOut $arWrd->[$hTokenIndex{"sOriginalToken"}] . "\t" .
	noNewlines($arWrd->[$hTokenIndex{"sOriginalToken"}]) . "\t" .
	  $arWrd->[$hTokenIndex{"iOriginalOnset"}] . "\t" .
	    $arWrd->[$hTokenIndex{"iOriginalOffset"}] . "\t" .
	      "isNotAWordformInDb"; # NOTE: NOT empty 5th column
    }

    # If we have position info, print it
    print $fhOut "\t" . $arWrd->[$hTokenIndex{iPosX}] . "\t" .
      $arWrd->[$hTokenIndex{iPosY}] . "\t" .
	$arWrd->[$hTokenIndex{iPosHeight}] . "\t" .
	  $arWrd->[$hTokenIndex{iPosWidth}] if( scalar(@$arWrd) > 7);

    print $fhOut "\n";
  }
}

# NOTE that we never 'die' as that will print to stderr which the
# Lexicon Tool's php scripts won't print.
sub endProgram {
  my ($self, $sMessage) = @_;

  print $sMessage;
  exit;
}

# Separate functions ##########################################################

sub noNewlines {
  my ($sString) = @_;
  $sString =~ s/^\n/_/;
  return $sString;
}

1;
