package xmlParser::EventHandler::PageXML;

# NOTE that we never 'die' as that will print to stderr which the
# Lexicon Tool's php scripts won't print.

# Constructor
sub new {
  my ($class, %hOptions) = @_;

  my $self = \%hOptions;
  bless $self, $class;

  # Initialise
  # $self->{bInBodyTag} = undef;
  #$self->{bInCoordsTag} = undef;
  $self->{bInUnicodeTag} = undef;
  $self->{iPointNr} = 0;
  $self->emptyToken();

  return $self;
}

sub initDatabase {
  my ($self, $sDatabase) = @_;

  # This function is intentionally left empty. It is here because the Lexicon
  # Tool calls tokenizeXml.pl with option -d, even though in the PageXML case
  # we don't do anything with a database (yet...).
}

sub closeDatabase {
  my ($self) = @_;

  $self->{dbh}->disconnect() if( exists($self->{dbh}) );
}

sub setOutputFileHandle {
  my ($self, $fhOut) = @_;

  $self->{fhOut} = $fhOut;
}

# This one is called when a tag has been read completely
sub atTag {
  my ($self, $hrTag) = @_;

  if( $hrTag->{sTagName} eq "Point" ) {
    # Coordinates = x, y, h, w
    if( $self->{iPointNr} == 0) {
      $self->{arCoordinates} = [$hrTag->{hrAttributes}->{x},
				$hrTag->{hrAttributes}->{"y"} ];
    }
    elsif( $self->{iPointNr} == 2) {
      # Height: (this point's y) - (first point's y)
      $self->{arCoordinates}->[2] =
	$hrTag->{hrAttributes}->{'y'} - $self->{arCoordinates}->[1];
      # Same for width: (this point's x) - (first point's x)
      $self->{arCoordinates}->[3] =
	$hrTag->{hrAttributes}->{x} - $self->{arCoordinates}->[0];
    }
    $self->{iPointNr}++;
  }
  elsif( $hrTag->{sTagName} eq "Coords" ) {
    $self->{arCoordinates} = [];
    $self->{iPointNr} = 0;
  }
  elsif( $hrTag->{sTagName} eq "/Unicode" ) {
    $self->writeToken();
    $self->{bInUnicodeTag} = undef;
  }
  elsif( $hrTag->{sTagName} eq "Unicode" ) {
    $self->{bInUnicodeTag} = 1;
  }
}

# Gets called when some text has been read
sub atText {
  my ($self, $hrText) = @_;

  if( $self->{bInUnicodeTag} ) {
    $self->fillToken($hrText);
  }
}

sub fillToken {
  my ($self, $hrText) = @_;

  $self->emptyToken();

  # Tja, maar gewoon hetzelfde
  $self->{hrToken}->{sCanonicalWordFrom} = $hrText->{sText};
  $self->{hrToken}->{sWordForm} = $hrText->{sText};
  $self->{hrToken}->{iStartPos} = $hrText->{iStartPos};
  # NOTE that we add 1 to the end position here...
  $self->{hrToken}->{iEndPos} = $hrText->{iEndPos} + 1;
  # Here is a bit to prevent punctuation from being uploaded as a word from
  # to the Lexicon Tool database.
  $self->{hrToken}->{sPunctuation} ="isNotAWordformInDb"
    if( $hrText->{sText} !~ /\w/);
}

sub writeToken {
  my ($self) = @_;

  unless(exists($self->{fhOut})) {
    print "ERROR: before printing \$self->{fhOut} should be set for " .
      "xmlParser::EventHandler::JSI";
    exit;
  }

  my $fhOut = $self->{fhOut};
  if( $self->{hrToken}->{iEndPos} ) {
    print $fhOut $self->{hrToken}->{sCanonicalWordFrom} . "\t" .
      $self->{hrToken}->{sWordForm} . "\t" .
	$self->{hrToken}->{iStartPos} . "\t" .
	  $self->{hrToken}->{iEndPos};
    if( length($self->{hrToken}->{sPunctuation})) {
      print $fhOut "\t" . $self->{hrToken}->{sPunctuation};
    }
    elsif( exists($self->{arCoordinates}) && 
	   scalar(@{$self->{arCoordinates}}) ) {
      print $fhOut "\t"; # Empty column, for the coordinates to appear after
    }

    if( exists($self->{arCoordinates}) && scalar(@{$self->{arCoordinates}}) ) {
      print $fhOut "\t" . join("\t", @{$self->{arCoordinates}});
    }

    print $fhOut "\n";
  }
  $self->emptyToken();
}

sub emptyToken {
  my ($self) = @_;

  $self->{hrToken} = {sCanonicalWordFrom => '',
		      sWordForm => '',
		      iStartPos => undef,
		      iEndPos => undef,
		      sPunctuation => ''
		     };
}

1;
