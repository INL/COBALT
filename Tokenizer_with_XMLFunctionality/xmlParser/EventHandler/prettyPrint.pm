package xmlParser::EventHandler::prettyPrint;

# Constructor
sub new {
  my ($class, %hOptions) = @_;

  my $self = \%hOptions;
  bless $self, $class;

  # Initialise 
  $self->{iLevel} = 0;

  return $self;
}

sub atStartOfFile {
  my ($self) = @_;

}

# This one is called when a tag has been read completely
sub atTag {
  my ($self, $hrTag) = @_;

  my $sFirstChar = substr($hrTag->{sTagName}, 0, 1);

  $hrTag->{sTagText} =~ s/[\r\n]+$//;

  if( $sFirstChar eq '/' ) {
    $self->{iLevel}--;
  }

  print ' ' x $self->{iLevel} . $hrTag->{sTagText} . "\n";

  if( $sFirstChar ne '/') {
    $self->{iLevel}++
      unless( ($sFirstChar eq '?') ||
	      # Self closing tags don't alter the level (like <a/>)
	      (substr($hrTag->{sTagText}, -2) eq "/>") );
  }
}

# Gets called when some text has been read
sub atText {
  my ($self, $hrText) = @_;

  $hrText->{sText} =~ s/[\r\n]+$//;

  print ' ' x $self->{iLevel} . $hrText->{sText} . "\n";
}

sub setOutputFileHandle {
  my ($self, $fhOut) = @_;

  $self->{fhOut} = $fhOut;
}

sub atEndOfFile {
  my ($self) = @_;
}

1;
