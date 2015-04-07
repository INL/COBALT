package xmlParser::EventHandler::morphBenchmark;

# Constructor
sub new {
  my ($class, %hOptions) = @_;

  my $self = \%hOptions;
  bless $self, $class;

  # Initialise 
  $self->{bInWordTag} = undef;
  $self->{bInAnalysisTag} = undef;
  $self->{bInBinTag} = undef;
  $self->{iNrOfWords} = 0;

  return $self;
}

sub atStartOfFile {
  my ($self) = @_;

}

# This one is called when a tag has been read completely
sub atTag {
  my ($self, $hrTag) = @_;

  if( $hrTag->{sTagName} eq 'word' ) {
    $self->{bInWordTag} = 1;
  }
  elsif( $hrTag->{sTagName} eq '/word' ) {
    $self->{bInWordTag} = undef;
  }
  elsif( $hrTag->{sTagName} eq 'analysis' ) {
    $self->{bInAnalysisTag} = 1;
  }
  elsif( $hrTag->{sTagName} eq '/analysis' ) {
    $self->{bInAnalysisTag} = undef;
    $self->{iNrOfWords}++; # Update when we are done with the word
  }
  elsif( $hrTag->{sTagName} eq 'bin') {
    $self->{bInBinTag} = 1;
  }
  elsif( $hrTag->{sTagName} eq '/bin') {
    $self->{bInBinTag} = undef;
  }
}

# Gets called when some text has been read
sub atText {
  my ($self, $hrText) = @_;

  $DB::single = 1;

  if( $self->{bInWordTag} ) {
    print "\n" unless($self->{iNrOfWords} == 0);
    print $hrText->{sText};
  }
  elsif( $self->{bInAnalysisTag} ) {
    print "\t" . substr($hrText->{sText}, 1, index($hrText->{sText}, ' ') - 1) .
      "\t" . $hrText->{sText} . "\t" . $self->{sYear};
  }
  elsif( $self->{bInBinTag} ) {
    # We assume we always have a year.
    $self->{sYear} = $hrText->{sText};
  }
}

sub setOutputFileHandle {
  my ($self, $fhOut) = @_;

  $self->{fhOut} = $fhOut;
}

sub atEndOfFile {
  my ($self) = @_;
}

1;
