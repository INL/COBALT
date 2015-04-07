package xmlParser::EventHandler::Example;

# Constructor
sub new {
  my ($class, %hOptions) = @_;

  my $self = \%hOptions;
  bless $self, $class;

  # Initialise something?!?
  $self->{bInContentTag} = undef;

  return $self;
}

sub atStartOfFile {
  my ($self) = @_;

  print ">> At start of file\n";
}

# This one is called when a tag has been read completely
sub atTag {
  my ($self, $hrTag) = @_;
  if( $hrTag->{sTagName} eq 'content' ) {
    $self->{bInContentTag} = 1;
  }
  elsif( $hrTag->{sTagName} eq '/content' ) {
    $self->{bInContentTag} = undef;
  }
}

# Gets called when some text has been read
sub atText {
  my ($self, $hrText) = @_;
  print "Text: '$hrText->{sText}'\n";
  if($self->{bInContentTag} ) {
    # We tellen eentje op bij de iEndPos zodat de checkOnsetOffsets.pl klopt.
    # Maar eigenlijk wijst iOffset dus naar het laatste karakter van de string
    # (en niet eentje daarna).
    
	print "Text: '$hrText->{sText}' (character positions: " .
      "$hrText->{iStartPos}, " . ($hrText->{iEndPos} + 1) . ")\n";
  }
}

sub setOutputFileHandle {
  my ($self, $fhOut) = @_;

  $self->{fhOut} = $fhOut;
}

sub atEndOfFile {
  my ($self) = @_;

  print ">> At end of file\n";
}

1;
