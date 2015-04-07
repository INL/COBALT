package xmlParser::xmlParser;

# This package implements an event driven XML parser.
#
# Usually, it will be used something like this:
#
# use myEventHandler;
# my $oXmlParser = xmlParser::xmlParser->new();
#
# $oXmlParser->setInputFileHandle($sFileName);
# $oXmlParser->{oEventHandler} = $sEventHandler->new();
#

# NOTE that we never 'die' as that will print to stderr which the
# Lexicon Tool's php scripts won't print.

# Constructor
sub new {
  my ($class, %hOptions) = @_;

  my $self = \%hOptions;
  bless $self, $class;

  # Initialise members
  $self->{fhInput} = undef;
  $self->{iPosition} = 0;
  $self->{iLineNr} = 1;
  $self->{hrState} = {bInTag => undef,
		      bInTagName => undef,
		      bInAttributeValue => undef,
		      iCommentEndTag => 0,
		      iProcessInstructionEndTag => 0,
		     };
  # Initialise
  $self->emptyCurrentTag();
  $self->emptyText();
  return $self;
}

sub setInputFileHandle {
  my ($self, $sFileName, $sEncoding) = @_;

  if( $sEncoding ) {
    open(FH, "<:encoding($sEncoding)", $sFileName)
      or $self->endProgram("Couldn't open $sFileName: $!\n");
  }
  else {
    open(FH, "<$sFileName")
      or $self->endProgram("Couldn't open $sFileName: $!\n");
  }

  # Remember the file name as well.
  $self->{sInputFileName} = $sFileName;
  $self->{fhInput} = *FH;
  # New filehandle, so we assume we start anew here
  $self->{iLineNr} = 1;
}

## 
sub findFirstTag {
  my $fileName = shift;
  open(F,$fileName);
  my $oldSep = $/;
  $/ = ">";
  while (<F>) {
    if (/<[A-Za-z][^<>]*>/)
    {
      close(F);
      $/ = $oldSep;
      return $&;
    } 
  } 
  $/ = $oldSep;
  close(F);
  return "";
}

sub determineXmlFormat {
  my ($self, $sFileName, $sTestPackage) = @_;

  if( $sTestPackage ) {
    $self->{sXmlFormat} = $sTestPackage;
  }
  elsif( $sFileName =~ /\.fixed$/ ) {
    $self->{sXmlFormat} = "Fixed";
  }
  else {
    my $firstTag = findFirstTag($sFileName);
    # Read the first line and ignore it
    if ($firstTag =~ "^<IGT:Impact") {
      $self->{sXmlFormat} = 'IGT';
    }
    elsif($firstTag =~ "<TEI\.2") {
      ##$self->{sXmlFormat} = 'TEI2';
      $self->{sXmlFormat} = 'JSI_V03'; ## <<< for Brieven Als Buit <<<
    }
    elsif( # JSI V0.2
          ($firstTag =~ "<TEI xmlns=") && ($firstTag =~ / xml:lang="sl" /)) {
      $self->{sXmlFormat} = 'JSI_V02';
    }
    elsif( # The format changed in V0.3
          ($firstTag =~ "<div xmlns=") &&
          ($firstTag =~ m! xmlns="http://www\.tei!) ) {
      $self->{sXmlFormat} = 'JSI_V03';
    }
    elsif( $firstTag =~ /^<Page / ) {
      $self->{sXmlFormat} = 'PageXML';
    }
    elsif(($firstTag =~ /^<PcGts xmlns="http:\/\/schema\.primaresearch\.org/) ||
	  ($firstTag =~ /xmlns:IGTnew/) ) {

      $self->{sXmlFormat} = 'PageXML_BritishLibrary';
    }
    elsif( $firstTag =~ /^<PcGts / ) {
      $self->{sXmlFormat} = 'PageXML_polish';
    }
    else {
      $self->endProgram("ERROR (version 1.01): Couldn't find XML format in" .
			"'$firstTag', file '$self->{sInputFileName}'.\n");
    }
  }

}

# It is assumed that there is a valid open file handle here which points to the
# start of the file.
sub determineXmlFormatAssumingTheFirstTagIsOnTheSecondLine {
  my ($self, $sFileName, $sTestPackage) = @_;

  if( $sTestPackage ) {
    $self->{sXmlFormat} = $sTestPackage;
  }
  elsif( $sFileName =~ /\.fixed$/ ) {
    $self->{sXmlFormat} = "Fixed";
  }
  else {
    # Done like this or the code below won't parse.
    my $fhHandle = $self->{fhInput};
    # Read the first line and ignore it
    <$fhHandle>;
    # Read the second line and try to deduce the format
    my $sSecondLine  = <$fhHandle>;
    $sSecondLine =~ s/[\n\r]+$//; # chomping doesn't work
    my $sSubstr = substr($sSecondLine, 0, 11);
    if( $sSubstr eq "<IGT:Impact") {
      $self->{sXmlFormat} = 'IGT';
    }
    elsif( $sSubstr eq "<TEI\.2>") {
      $self->{sXmlFormat} = 'TEI2';
    }
    elsif( # JSI V0.2
	  ($sSubstr eq "<TEI xmlns=") && ($sSecondLine =~ / xml:lang="sl" /)) {
      $self->{sXmlFormat} = 'JSI_V02';
    }
    elsif( # The format changed in V0.3
	  ($sSubstr eq "<div xmlns=") &&
	  ($sSecondLine =~ m! xmlns="http://www\.tei!) ) {
      $self->{sXmlFormat} = 'JSI_V03';
    }
    elsif( $sSubstr =~ /^<Page / ) {
      $self->{sXmlFormat} = 'PageXML';
    }
    elsif( $sSubstr =~ /^<PcGts / ) {
      $self->{sXmlFormat} = 'PageXML_polish';
    }
    else {
      $self->endProgram("ERROR: Couldn't find XML format in '$sSecondLine', ".
			"file '$self->{sInputFileName}'.\n");
    }
    # Rewind the file handle
    seek($self->{fhInput}, 0, 0);
  }
}

# NOTE that we assume that the file handle is already there and opened.
sub parseFile {
  my ($self) = @_;

  $self->endProgram("ERROR: no open file handle is set.\n")
    unless(exists($self->{fhInput}) && defined($self->{fhInput}));

  $self->{oEventHandler}->atStartOfFile($self->{sInputFileName})
    if($self->{oEventHandler}->can(atStartOfFile));

  my $c;
  while( read($self->{fhInput}, $c, 1)  ) {
    $self->handleChar($c);
  }

  # In valid XML the last atTag will have been called at this stage (since a
  # final '>' was encountered). In case of e.g. the .fixed format however,
  # there might be some text left at the end of the file.
  # If so, we should fire a last atText() event.
  if( defined($self->{hrText}->{iStartPos})) {
    # Set the position to the last character
    $self->{hrText}->{iEndPos} = $self->{iPosition} - 1;
    $self->{oEventHandler}->atText($self->{hrText});
    $self->emptyText();
  }

  $self->{oEventHandler}->atEndOfFile()
    if($self->{oEventHandler}->can(atEndOfFile));

  close($self->{fhInput});
}

# For parsing chunks of XML
# NOTE that we need a *REFERENCE* to a string
sub parseString {
  my ($self, $srString) = @_; # <-- Reference to a string

  # Always start from scratch
  $self->reInit();

  # start of string
  $self->{oEventHandler}->atStartOfFile($self->{sInputFileName})
    if($self->{oEventHandler}->can(atStartOfFile));

  my $c;
  while( $$srString =~ /(.)/g ) {
    $self->handleChar($1);
  }

  # In valid XML the last atTag will have been called at this stage (since a
  # final '>' was encountered). In case of e.g. the .fixed format however,
  # there might be some text left at the end of the file.
  # If so, we should fire a last atText() event.
  if( defined($self->{hrText}->{iStartPos})) {
    # Set the position to the last character
    $self->{hrText}->{iEndPos} = $self->{iPosition} - 1;
    $self->{oEventHandler}->atText($self->{hrText});
    $self->emptyText();
  }


  # end of string.
  $self->{oEventHandler}->atEndOfFile()
    if($self->{oEventHandler}->can(atEndOfFile));
}

sub handleChar {
  my ($self, $c) = @_;

  if( $self->{hrState}->{bInTag} ) {
    if( ($c eq '>') && (! $self->{hrState}->{bInAttributeValue}) ) {
      $self->handleEndBracket();
    }
    else { # We are somewhere in de tag (between < and >
      if( $self->{hrCurrentTag}->{sTagName} =~ /^!doctype$/i) {
	$self->handleCharInDocType($c);
      } # HTML comment
      elsif( $self->{hrCurrentTag}->{sTagName} eq '!--' ) {
	$self->handleCharInComment($c);
      } # Process instruction
      elsif( (substr($self->{hrCurrentTag}->{sTagName}, 0, 1) eq '?') &&
	     # First line is <?xml ...?>. 
	     ($self->{iLineNr} != 1) ) {
	$self->handleCharInProcessInstruction($c);
      }
      elsif( ($c eq '"') || ($c eq "'") ) {
	$self->handleQuoteInTag($c);
      }
      elsif( $self->{hrState}->{bInAttributeValue} ) {
	# When we are in a quoted attribute value, anything goes
	$self->{hrCurrentTag}->{hrAttributes}->{$self->{hrCurrentTag}->{sCurrentAttribute}} .= $c;
      }
      elsif( $c eq ' ') { # Not in attribute value and $c is a space
	$self->handleSpaceInTag($c);
      }
      elsif( $c eq "\n") { # Not in attribute value and $c is a newline
	# This is really the same as a space presumably, but we split them
	# anyway.
	$self->handleNewlineInTag($c);
      }
      else { # Not in attribute value and $c is not a space
	$self->handleCharInTag($c);
      }
      # Anyway, we keep track of the entire literal string
      $self->{hrCurrentTag}->{sTagText} .= $c;
    }
  }
  else { # Not in tag
    if( $c eq '<' ) {
      # We start a tag. First finish up any text we have.
      if(length($self->{hrText}->{sText})) {
	# The end position is one before where we are now.
	$self->{hrText}->{iEndPos} = $self->{iPosition} - 1;
	# Fire a text-event
	$self->{oEventHandler}->atText($self->{hrText});
	$self->emptyText();
      }
      $self->{hrCurrentTag}->{iStartPos} = $self->{iPosition};
      $self->{hrCurrentTag}->{sTagText} = $c;
      $self->{hrState}->{bInTag} = 1;
    }
    else {
      $self->{hrText}->{iStartPos} = $self->{iPosition}
	unless(defined($self->{hrText}->{iStartPos}));
      $self->{hrText}->{sText} .= $c;
    }
  }


  $self->{iLineNr}++ if( $c eq "\n" );
  $self->{iPosition}++;
}

sub handleCharInComment {
  my ($self, $c) = @_;

  if( $c eq '-' ) {
    if($self->{hrState}->{iCommentEndTag} == 0) {
      $self->{hrState}->{iCommentEndTag} = 1;
    }
    elsif($self->{hrState}->{iCommentEndTag} == 1) {
      $self->{hrState}->{iCommentEndTag} = 2;
    }
    else {
      $self->{hrState}->{iCommentEndTag} = 0;
    }
  }
  else {
    $self->{hrState}->{iCommentEndTag} = 0;
  }
  # NOTE that the end of the end tag '>' is handled in handleEndBracket()
}

sub handleCharInProcessInstruction {
  my ($self, $c) = @_;

  # We should check if this is actually correct.
  # The way things are done now an arbitrary number of ?'s can occur.
  if( $c eq '?' ) {
    $self->{hrState}->{iProcessInstructionEndTag} = 1;
  }
  else {
    $self->{hrState}->{iProcessInstructionEndTag} = 0;
  }
  # NOTE that the end of the end tag '>' is handles in handleEndBracket()
}

# DOCTYPE tags can contain nested tags. Currently this is NOT supported.
#
# A DOCTYPE declaration can include DTD declarations as an internal DTD subset
# between square brackets, like this,
#
# <!DOCTYPE chapter [
# <!ELEMENT chapter (title,para+)>
# <!ELEMENT title (#PCDATA)>
# <!ELEMENT para (#PCDATA)>
# ]>
#
# Cf: http://www.xml.com/lpt/a/1027
#
sub handleCharInDocType {
  my ($self, $c) = @_;
}

sub handleEndBracket {
  my ($self) = @_;

  # Neglect if we are in a comment or process instruction, and this is not the
  # end of the comment/PI end tag
  if( ($self->{hrCurrentTag}->{sTagName} eq '!--') && # Comment
      ($self->{hrState}->{iCommentEndTag} != 2) ) {
    $self->{hrCurrentTag}->{sTagText} .= '>';
    return;
  } # Same for PI
  elsif( (substr($self->{hrCurrentTag}->{sTagName}, 0, 1) eq '?') &&
	 ($self->{iLineNr} != 1) &&
	 ($self->{hrState}->{iProcessInstructionEndTag} != 1) ) {
    $self->{hrCurrentTag}->{sTagText} .= '>';
    return;
  }

  # Where ever we are, we are not looking for a comment/PI end tag anymore.
  $self->{hrState}->{iCommentEndTag} = 0;
  $self->{hrState}->{iProcessInstructionEndTag} = 0;

  # If there was a stray character, this was taken to be the start of an
  # attribute name, and as such it won't have a value.
  if( length($self->{hrCurrentTag}->{sCurrentAttribute}) &&
      (! exists($self->{hrCurrentTag}->{hrAttributes}->{$self->{hrCurrentTag}->{sCurrentAttribute}})) &&
      # In the case of the start tag there is a '?' just before the closing tag
      # <?xml ... ?> so that is allowed.
      ( ! ( ($self->{iLineNr} == 1) && # <?xml ..?> always on first line
	    ($self->{hrCurrentTag}->{sCurrentAttribute} eq '?')) ) &&
      # What can also be the case is that we have a selve closing tag (ie. it
      # ends in '/>'). Since we are not validating here, let's just assume
      # that that is correct.
      ( ! ( $self->{hrCurrentTag}->{sCurrentAttribute} eq '/') )
    ) {
    $self->endProgram("ERROR: Stray character " .
		      "'$self->{hrCurrentTag}->{sCurrentAttribute}'" .
		      " before '>' at position $self->{iPosition}, " .
		      "line $self->{iLineNr}, " .
		      "file '$self->{sInputFileName}'.\n");
  }

  $self->{hrCurrentTag}->{iEndPos} = $self->{iPosition};
  $self->{hrCurrentTag}->{sTagText} .= '>';
  $self->{oEventHandler}->atTag($self->{hrCurrentTag});
  $self->emptyCurrentTag();
  $self->{hrState}->{bInTag} = undef;
}

# We are not in an attribute value here
sub handleSpaceInTag {
  my ($self, $c) = @_;

  # No space allowed in: attr= "value"
  $self->endProgram("ERROR: Stray space at position $self->{iPosition}, " .
		    "line $self->{iLineNr}, file '$self->{sInputFileName}'.\n")
    if( length($self->{hrCurrentTag}->{sCurrentAttribute}) );
  $self->{hrState}->{bInTagName} = undef;
}

# We are not in an attribute value here
sub handleNewlineInTag {
  my ($self, $c) = @_;

  # No newline allowed in: attr=<NEWLINE>"value"
  $self->endProgram("ERROR: Stray newline at position $self->{iPosition}, " .
		    "line $self->{iLineNr}, file '$self->{sInputFileName}'.\n")
    if( length($self->{hrCurrentTag}->{sCurrentAttribute}) );
  $self->{hrState}->{bInTagName} = undef;
}

# This sub only goes when we are not in an attribute, and the char can not be a
# space
sub handleCharInTag {
  my ($self, $c) = @_;

  if( ! length($self->{hrCurrentTag}->{sTagName}) ) {
    $self->{hrCurrentTag}->{sTagName} .= $c;
    $self->{hrState}->{bInTagName} = 1;
  }
  elsif( $self->{hrState}->{bInTagName} ) {
    $self->{hrCurrentTag}->{sTagName} .= $c;
  }
  elsif( $c eq '=' ) {
    if( ! length($self->{hrCurrentTag}->{sCurrentAttribute}) ) {
      $self->endProgram("ERROR: Stray '=' at $self->{iPosition}, " .
			"line $self->{iLineNr}, " .
			"file '$self->{sInputFileName}'.\n");
    }
  }
  else { # Normal case
    $self->{hrCurrentTag}->{sCurrentAttribute} .= $c;
  }
}

sub handleQuoteInTag {
  my ($self, $c) = @_;

  $DB::single = 1 if( $c eq "'");

  # If we are in an attribute value
  if( $self->{hrState}->{bInAttributeValue} ) {
    if( $c eq $self->{hrCurrentTag}->{cOpenQuote} ) {
      # If the quote is escaped it is ok
      if( substr($self->{hrCurrentTag}->{sTagText}, -1) eq "\\") {
	if( length($self->{hrCurrentTag}->{sCurrentAttribute}) ) {
	  $self->{hrCurrentTag}->{hrAttributes}->{$self->{hrCurrentTag}->{sCurrentAttribute}} .= $c;
	}
	else {
	  $self->endProgram("ERROR: Stray quote at $self->{iPosition}, " .
			    "line $self->{iLineNr}, " .
			    "file '$self->{sInputFileName}'.\n");
	}
      }
      else { # Otherwise it is the end of the attribute value
	$self->{hrCurrentTag}->{sCurrentAttribute} = '';
	$self->{hrCurrentTag}->{cOpenQuote} = '';
	$self->{hrState}->{bInAttributeValue} = undef;
      }
    }
    else { # It is a quote but a different one form the opening quote
      # Just add it
      $self->{hrCurrentTag}->{hrAttributes}->{$self->{hrCurrentTag}->{sCurrentAttribute}} .= $c;
    }
  }
  else { # If we are not in an attribute value, we will start now
    # provided the previous bit looks like aan attribute name and an '='
    if( (substr($self->{hrCurrentTag}->{sTagText}, -1) eq '=') &&
	(substr($self->{hrCurrentTag}->{sTagText}, -2, 1) ne ' ') ) {
      $self->{hrState}->{bInAttributeValue} = 1;
      $self->{hrCurrentTag}->{cOpenQuote} = $c;
      # Here we initiate the value with an empty string, to make sure that
      # if it really is an empty string (as in <tag attr="">) we have the
      # attribute in the list.
      $self->{hrCurrentTag}->{hrAttributes}->{$self->{hrCurrentTag}->{sCurrentAttribute}} = '';
    }
  }
}

sub emptyText {
  my ($self) = @_;

  $self->{hrText} = {sText => '',
		     iStartPos => undef,
		     iEndPos => undef,
		    };
}

sub emptyCurrentTag {
  my ($self) = @_;

  $self->{hrCurrentTag} = {sTagText => '',
			   sTagName => '',
			   hrAttributes => {},
			   sCurrentAttribute => '',
			   cOpenQuote => '',
			   iStartPos => undef,
			   iEndPos => undef,
			  };
}

sub reInit {
  my ($self) = @_;

  $self->{iPosition} = 0;
  $self->{iLineNr} = 1;
  $self->{hrState} = {bInTag => undef,
		      bInTagName => undef,
		      bInAttributeValue => undef,
		      iCommentEndTag => 0,
		      iProcessInstructionEndTag => 0,
		     };
  # Initialise
  $self->emptyCurrentTag();
  $self->emptyText();
}

# NOTE that we never 'die' as that will print to stderr which the
# Lexicon Tool's php scripts won't print.
sub endProgram {
  my ($self, $sMessage) = @_;

  print $sMessage;
  exit;
}

1;
