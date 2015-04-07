package xmlParser::EventHandler::JSI_V03;

# NOTE that we never 'die' as that will print to stderr which the
# Lexicon Tool's php scripts won't print.

# Constructor
sub new {
  my ($class, %hOptions) = @_;

  my $self = \%hOptions;
  bless $self, $class;

  # Initialise
  ### $self->{bInBodyTag} = undef; We don't care about this anymore in V0.3
  $self->{bInWTag} = undef;
  $self->{bInPcTag} = undef;
  $self->emptyToken();

  return $self;
}

sub initDatabase {
  my ($self, $sDatabase) = @_;

  # This function is intentionally left empty. It is here because the Lexicon
  # Tool calls tokenizeXml.pl with option -d, even though in the JSI case
  # we don't do anything with a database.
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

  ### Body tags are not relevant anymore in V0.3
  # if( $hrTag->{sTagName} eq "body" ) {
  #   $self->{bInBodyTag} = 1;
  # }
  # elsif( $hrTag->{sTagName} eq "/body" ) {
  #   $self->{bInBodyTag} = undef;
  # }
  # els
  if( $hrTag->{sTagName} eq "pc" ) {
    $self->{bInPcTag} = 1;
  }
  elsif( $hrTag->{sTagName} eq "/pc" ) {
    $self->writeToken();
    $self->{bInPcTag} = undef;
  }
  elsif( $hrTag->{sTagName} eq "/w" ) {
    $self->writeToken();
    $self->{bInWTag} = undef;
  }
  elsif( $hrTag->{sTagName} eq "w" ) {
    $self->{bInWTag} = 1;
    $self->startToken($hrTag);
  }
}

# Gets called when some text has been read
sub atText {
  my ($self, $hrText) = @_;

  ### No body tags anymore in V0.3
  $self->fillToken($hrText)
###    if($self->{bInBodyTag} && ( $self->{bInWTag} || $self->{bInPcTag}) );
    if( $self->{bInWTag} || $self->{bInPcTag} );
}

sub startToken {
  my ($self, $hrTag) = @_;

  # Just to be sure, empty everything
  $self->emptyToken();

  $self->{hrToken}->{sCanonicalWordFrom} = $hrTag->{hrAttributes}->{nform}
    if( exists($hrTag->{hrAttributes}->{nform}) );
}

sub fillToken {
  my ($self, $hrText) = @_;

  $self->{hrToken}->{sCanonicalWordFrom} = $hrText->{sText}
    unless ( length($self->{hrToken}->{sCanonicalWordFrom}) );

  $self->{hrToken}->{sWordForm} = $hrText->{sText};
  $self->{hrToken}->{iStartPos} = $hrText->{iStartPos};
  # NOTE that we add 1 to the end position here...
  $self->{hrToken}->{iEndPos} = $hrText->{iEndPos} + 1;
  # Here is a bit to prevent punctuation from being uploaded as a word from
  # to the Lexicon Tool database.
  $self->{hrToken}->{sPunctuation} ="isNotAWordformInDb"
    if($self->{bInPcTag});
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
	  $self->{hrToken}->{iEndPos} . "\t";
    # NOTE tab at the end of the previous print statement. There is an empty
    # column if there is no punctuation.
    print $fhOut $self->{hrToken}->{sPunctuation}
      if( length($self->{hrToken}->{sPunctuation}));
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
