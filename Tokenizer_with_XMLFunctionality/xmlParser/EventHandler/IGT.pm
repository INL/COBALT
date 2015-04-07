package xmlParser::EventHandler::IGT;

# Inherit database functionality
use xmlParser::EventHandler::databaseFunctions;
@ISA = (xmlParser::EventHandler::databaseFunctions);

use strict;

use HTML::Entities;

use impactok::impactok;
use xmlParser::EventHandler::databaseFunctions;

# NOTE that we never 'die' as that will print to stderr which the
# Lexicon Tool's php scripts won't print.
# Instead, we use impactok::impactok::endProgram()

# Constructor
sub new {
  my ($class, %hOptions) = @_;

  # Call the constructor of the class we inherit from.
  my $self = $class->SUPER::new(%hOptions);
  bless $self, $class;

  # Initialise
  $self->{sLanguage} =
    ( exists($hOptions{sLanguage}) ) ? $hOptions{sLanguage} : undef;
  $self->{sLemmata} = '';
  $self->{bInPageTag} = undef;
  $self->{sCurrentRelevantBlock} = undef;
  $self->{bInLocTag} = undef;
  $self->{bInIgnoreTag} = undef;
  $self->{iGroupId} = 0;
  $self->{arNameTokens} = [];
  $self->emptyTextBlock();

  $self->{oImpactok} = impactok::impactok->new(sLanguage =>$self->{sLanguage});
  $self->{oImpactok}->{frAlternativeAddToken} = \&addToken;

  return $self;
}

# This also sets the file handle for the impactok object.
sub setOutputFileHandle {
  my ($self, $fhOut) = @_;

  $self->{fhOut} = $fhOut;
  $self->{oImpactok}->{fhOut} = $self->{fhOut};
}

# This one is called when a tag has been read completely
sub atTag {
  my ($self, $hrTag) = @_;

  if( $hrTag->{sTagName} eq "IGT:Page") {
    $self->{bInPageTag} = 1;
  }
  elsif( $hrTag->{sTagName} eq "/IGT:Page") {
    $self->{bInPageTag} = undef;
  }
  elsif( ($hrTag->{sTagName} eq "IGT:TextBlock") ||
	 ($hrTag->{sTagName} eq "IGT:TableBlock") ||
	 ($hrTag->{sTagName} eq "IGT:IllustrationBlock") ||
	 ($hrTag->{sTagName} eq "IGT:UnknownBlock") ) {
    if( exists($hrTag->{hrAttributes}->{x}) ) {
      $self->{hrText}->{arCoordinates} = [$hrTag->{hrAttributes}->{'x'},
					  $hrTag->{hrAttributes}->{'y'},
					  $hrTag->{hrAttributes}->{'h'},
					  $hrTag->{hrAttributes}->{'w'}];
    }
    $self->{sCurrentRelevantBlock} = $hrTag->{sTagName};
  }
  elsif( ($hrTag->{sTagName} eq "/IGT:TextBlock") ||
	 ($hrTag->{sTagName} eq "/IGT:TableBlock") ||
	 ($hrTag->{sTagName} eq "/IGT:IllustrationBlock") ||
	 ($hrTag->{sTagName} eq "/IGT:UnknownBlock") ) {
    $self->{oImpactok}->endProgram("ERROR: Mismatching tag $hrTag->{sTagName} "
				   . "in file $self->{sInputFileName}\n")
      unless( $self->{sCurrentRelevantBlock} &&
	      ($hrTag->{sTagName} eq "/$self->{sCurrentRelevantBlock}") );

    $self->{hrText}->{iEndPos} = $hrTag->{iStartPos} - 1;
    # Now that we are done with the entire TextBlock we can tokenize and print.
    $self->tokenizeTextBlock();

    $self->{hrText}->{arCoordinates} = [];
    $self->{sCurrentRelevantBlock} = undef;
  }
  elsif( $self->{sCurrentRelevantBlock} ) { # Tags inside the TextBlock tag
    $self->{hrText}->{iStartPos} = $hrTag->{iStartPos}
      unless(defined($self->{hrText}->{iStartPos}));

    # Ignore tags
    if( $hrTag->{sTagName} eq "IGT:Ignore") {
      $self->{bInIgnoreTag} = 1;
      $self->{hrText}->{sText} .=
	' ' x (($hrTag->{iEndPos} - $hrTag->{iStartPos}) + 1);
      $self->{hrText}->{iEndPos} = $hrTag->{iEndPos};
    }
    elsif( $hrTag->{sTagName} eq "/IGT:Ignore" ) {
      $self->{bInIgnoreTag} = undef;
      $self->{hrText}->{sText} .=
	' ' x (($hrTag->{iEndPos} - $hrTag->{iStartPos}) + 1);
      $self->{hrText}->{iEndPos} = $hrTag->{iEndPos};
    } # Insert tags
    elsif($hrTag->{sTagName} eq "/IGT:Insert" ) {
      # The nice thing is that we don't have to do anything for the insert
      # tags. The tag itself is replaced by a number of spaces, causing the
      # surrounding string to end up  as separate tokens, which is exactly
      # what we want.
      $self->{hrText}->{sText} .=
	' ' x (($hrTag->{iEndPos} - $hrTag->{iStartPos}) + 1);
      $self->{hrText}->{iEndPos} = $hrTag->{iEndPos};
    } # Start of an NE LOC tag
    elsif( ($hrTag->{sTagName} eq "IGT:NE") &&
	   exists($hrTag->{hrAttributes}->{type}) &&
	   ($hrTag->{hrAttributes}->{type} eq 'LOC' ) ) {
      if( $self->{bInIgnoreTag} ) {
	$self->{hrText}->{sText} .=
	  ' ' x (($hrTag->{iEndPos} - $hrTag->{iStartPos}) + 1);
	$self->{hrText}->{iEndPos} = $hrTag->{iEndPos};
      } # Not in ignore tag
      elsif( exists($hrTag->{hrAttributes}->{gid}) ) {
	# If we have an NE type="LOC" with a group id, that means that there
	# are others with the same id that make up a group.
	# There can be text in between though so we collect them all before
	# we put them in the database.

	# We flush the tokenized output anyway
	$self->tokenizeTextBlock();

	# If it is the same group we add it to the collected tokens
	if( $hrTag->{hrAttributes}->{gid} == $self->{iGroupId} ) {
	  ; # for now, nothing here
	}
	else { # Not a new group member, the we flush everything before
	  $self->putLocTokensInDb($self->{arNameTokens})
	    if(scalar(@{$self->{arNameTokens}}));
	  $self->{arNameTokens} = []; # New name
	}

	# The lemma should always be there
	if(exists($hrTag->{hrAttributes}->{lemmata})) {
	  $self->{sLemmata} = $hrTag->{hrAttributes}->{lemmata};
	}
	else {
	  print "ERROR: No lemmata for '$hrTag->{sTagName}' " .
	    "in file $self->{sInputFileName}\n";
	}

	$self->{iGroupId} = $hrTag->{hrAttributes}->{gid};
      }
      else { # No gid
	# If we have previous names, add them to the database
	if(scalar(@{$self->{arNameTokens}})) {
	  $self->putLocTokensInDb($self->{arNameTokens});
	  $self->{arNameTokens} = []; # New name
	}
	# The lemma should always be there
	if(exists($hrTag->{hrAttributes}->{lemmata})) {
	  $self->{sLemmata} = $hrTag->{hrAttributes}->{lemmata};
	}
	else {
	  print "ERROR: No lemmata for '$hrTag->{sTagName}' " .
	    "in file $self->{sInputFileName}\n";
	}

	$self->{iGroupId} = 0;
	# We flush everything before, so any text in the tag can be treated
	# as one name.
	$self->tokenizeTextBlock();
      }
      $self->{bInLocTag} = 1;
    } # End of an NE LOC tag
    elsif( ($hrTag->{sTagName} eq "/IGT:NE") && $self->{bInLocTag} ) {
      $self->{hrText}->{sText} .=
	' ' x (($hrTag->{iEndPos} - $hrTag->{iStartPos}) + 1);
      $self->{hrText}->{iEndPos} = $hrTag->{iEndPos};
      $self->{bInLocTag} = undef;
    } # Any other tag is replaced by its length in spaces
    else {
      $self->{hrText}->{sText} .=
	' ' x (($hrTag->{iEndPos} - $hrTag->{iStartPos}) + 1);
      $self->{hrText}->{iEndPos} = $hrTag->{iEndPos};
    }
  }
}

# Gets called when some text has been read
sub atText {
  my ($self, $hrText) = @_;

  $DB::single = 1 if ($hrText->{sText} eq "Ro");

  if( $self->{sCurrentRelevantBlock} ) {
    if( $self->{bInIgnoreTag} ) {
      $self->{hrText}->{sText} .=
	' ' x (($hrText->{iEndPos} - $hrText->{iStartPos}) + 1);
      $self->{hrText}->{iEndPos} = $hrText->{iEndPos};
    }
    else {
      # If we are in a location tag, we have flushed any previous text
      if( $self->{bInLocTag} ) {
	# So we tokenize the name right away
	$self->{hrText}->{iStartPos} = $hrText->{iStartPos}
	  unless(defined($self->{hrText}->{iStartPos}));
	$self->{hrText}->{sText} .= $hrText->{sText};
	$self->{hrText}->{iEndPos} = $hrText->{iEndPos};
	$self->tokenizeTextBlock();

	foreach my $arToken ( @{$self->{oImpactok}->{arTokens}} ) {
	  push(@{$self->{arNameTokens}}, $arToken) if length($arToken->[2]);
	}
      }
      else {
	# In any other case, just add the new text
	$self->{hrText}->{iStartPos} = $hrText->{iStartPos}
	  unless(defined($self->{hrText}->{iStartPos}));
	$self->{hrText}->{sText} .= $hrText->{sText};
	$self->{hrText}->{iEndPos} = $hrText->{iEndPos};
      }
    }
  }
}

sub tokenizeTextBlock {
  my ($self) = @_;

  if( defined($self->{hrText}->{iEndPos}) ) {
    my $arCoordinates = (exists($self->{hrText}->{arCoordinates}))
      ? $self->{hrText}->{arCoordinates} : undef;
    $self->{oImpactok}->makeTokenArray($self->{hrText}->{sText},
				       $self->{hrText}->{iStartPos},
				       $arCoordinates );
    $self->{oImpactok}->analyseTokenArray();
    $self->{oImpactok}->printTokenArray();
  }
  $self->emptyTextBlock();
}

sub emptyTextBlock {
  my ($self) = @_;

  $self->{hrText}->{sText} = '';
  $self->{hrText}->{iStartPos} = undef;
  $self->{hrText}->{iEndPos} = undef;
  # We empty the coordinates at the end of a <IGT:TextBlock> tag
  $self->{hrText}->{arCoordinates} = []
    unless(exists($self->{hrText}->{arCoordinates}));
}

# Additional sub routines #####################################################

# $hrState is a hash that you can use for whatever you want and that will keep
# its value over different calls.
sub addToken {
  my ($arTokens, $iOnset, $sWord, $arCoordinates, $hrState) = @_;

  if( $hrState->{sState} eq 'inTagToBeIgnored') {
    if( $sWord =~ /^<\/IGT:Ignore>(.*)$/ ) {
      my $iLastIndex = scalar(@$arTokens) - 1;
      # Add the word to the existing token
      # Set offset
      $arTokens->[$iLastIndex]->[1] = $iOnset + length($sWord);
      $arTokens->[$iLastIndex]->[2] .= cleanseWord($1);
      $arTokens->[$iLastIndex]->[3] .= $1;
      $hrState->{sState} = '';
    }
  }
  elsif( $sWord =~ /^(.+)<IGT:Ignore$/ ) {
    my $iOffset = $iOnset + length($1);

    my $arNewToken = ($arCoordinates) ?
      [$iOnset, $iOffset, $iOnset, $iOffset, cleanseWord($1), $1, $1,
       @$arCoordinates]
	: [$iOnset, $iOffset, $iOnset, $iOffset, cleanseWord($1), $1, $1];
    push(@$arTokens, $arNewToken);
    $hrState->{sState} = 'inTagToBeIgnored';
  }
  else {
    my $iOffset = $iOnset + length($sWord);
    my $arNewToken = ($arCoordinates) ?
      [$iOnset, $iOffset, $iOnset, $iOffset, cleanseWord($sWord), $sWord,
       $sWord, @$arCoordinates]
	: [$iOnset, $iOffset, $iOnset, $iOffset, cleanseWord($sWord), $sWord,
	   $sWord];
    push(@$arTokens, $arNewToken);
  }
}

sub cleanseWord {
  my ($sWord) = @_;

  $sWord =~ s/&[rl]dquor;//g;
  $sWord =~ s!<[^<>]*>!!g;
  return decode_entities ($sWord);
}

sub atStartOfFile {
  my ($self, $sInputFileName) = @_;

  $self->{sInputFileName} = $sInputFileName;
  $self->insertDocumentInDb();
}

sub atEndOfFile {
  my ($self) = @_;

  # If we still have a name in memory, put it in the database.
  $self->putLocTokensInDb($self->{arNameTokens})
    if(scalar(@{$self->{arNameTokens}}));
}

1;
