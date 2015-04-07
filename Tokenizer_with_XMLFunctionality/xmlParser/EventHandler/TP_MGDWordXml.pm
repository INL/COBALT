package xmlParser::EventHandler::TP_MGDWordXml;

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
 # print "Tag: '$hrTag->{sTagName}'; \n";
  if( $hrTag->{sTagName} eq 'w:t' || $hrTag->{sTagName} eq 'w:pPr' || $hrTag->{sTagName} eq 'w:pStyle'){
	if($hrTag->{sTagName} eq 'w:pStyle'){
		print "[$hrTag->{hrAttributes}->{'w:val'}]\n";
	}
	print "[$hrTag->{sTagName}]\t";
    $self->{bInContentTag} = 1;
  }
  elsif( $hrTag->{sTagName} eq '/w:t'  || $hrTag->{sTagName} eq '/w:pPr' || $hrTag->{sTagName} eq '/w:pStyle') {
    $self->{bInContentTag} = undef;
  }
}

# Gets called when some text has been read
sub atText {
  my ($self, $hrText) = @_;

  if($self->{bInContentTag} ) {
    # We tellen eentje op bij de iEndPos zodat de checkOnsetOffsets.pl klopt.
    # Maar eigenlijk wijst iOffset dus naar het laatste karakter van de string
    # (en niet eentje daarna).
    print "Text: '$hrText->{sText}'\n";
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
