package xmlParser::EventHandler::basicImpactok;

use strict;

# This script is only meant for testing. It puts nothing into the database 
# but only tokenizes. This is just meant to check if PageXML.pm is doing well.

use HTML::Entities;

use impactok::impactok;

# NOTE that we never 'die' as that will print to stderr which the
# Lexicon Tool's php scripts won't print.
# Instead, we use impactok::endProgram()

# Constructor
sub new {
  my ($class, %hOptions) = @_;

  my $self = \%hOptions;
  bless $self, $class;

  # Initialise
  $self->{sLanguage} =
    ( exists($hOptions{sLanguage}) ) ? $hOptions{sLanguage} : undef;
  $self->{sCurrentRelevantBlock} = undef;
  $self->{bInPageTag} = undef;
  $self->{bInIgnoreTag} = undef;
  # Initialize
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
    # Now that we are done with the entire TextBlock/TableBlock/etc. we can
    # tokenize and print.
    $self->tokenizeTextBlock();

    $self->{hrText}->{arCoordinates} = [];
    $self->{sCurrentRelevantBlock} = undef;
  }
  elsif( $self->{sCurrentRelevantBlock} ) { # Tags inside the TextBlock/etc tag
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

  # If we are in a relevant block, and not in an ignorable part
  # just add the new text
  if( $self->{sCurrentRelevantBlock} ) {
    if( $self->{bInIgnoreTag} ) {
      $self->{hrText}->{sText} .=
	' ' x (($hrText->{iEndPos} - $hrText->{iStartPos}) + 1);
    }
    else {
      $self->{hrText}->{sText} .= $hrText->{sText};
    }
    $self->{hrText}->{iStartPos} = $hrText->{iStartPos}
      unless(defined($self->{hrText}->{iStartPos}));
    $self->{hrText}->{iEndPos} = $hrText->{iEndPos};
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

    # Three times $sPart, and twice onset and offset.
    # The first onnset/offset will be altered in making the canonical wordform.
    # the first part will become the canoical wordfrom, the second the word
    # 'as is' and the third is the original token (possibly only punctuation).
    # The second onset/offset pair and the third $sPart
    # will not (SHOULD not!) be altered during the following steps.
    my $arNewToken = ($arCoordinates) ?
      [$iOnset, $iOffset, $iOnset, $iOffset,
       cleanseWord($1), $1, $1, @$arCoordinates]
	: [$iOnset, $iOffset, $iOnset, $iOffset, cleanseWord($1), $1, $1];
    push(@$arTokens, $arNewToken);
    $hrState->{sState} = 'inTagToBeIgnored';
  }
  else {
    my $iOffset = $iOnset + length($sWord);

    # Three times $sPart, and twice onset and offset.
    # The first onnset/offset will be altered in making the canonical wordform.
    # the first part will become the canoical wordfrom, the second the word
    # 'as is' and the third is the original token (possibly only punctuation).
    # The second onset/offset pair and the third $sPart
    # will not (SHOULD not!) be altered during the following steps.
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
}

sub atEndOfFile {
  my ($self) = @_;

}

1;
