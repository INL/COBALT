package xmlParser::EventHandler::TEI2;

# Constructor
sub new {
  my ($class, %hOptions) = @_;

  my $self = \%hOptions;
  bless $self, $class;

  # Initialise
  $self->{bInPTag} = undef;
  $self->{bInWTag} = undef;
  $self->{bInTextTag} = undef;
  $self->emptyToken();

  return $self;
}

sub setOutputFileHandle {
  my ($self, $fhOut) = @_;

  $self->{fhOut} = $fhOut;
}

# This one is called when a tag has been read completely
sub atTag {
  my ($self, $hrTag) = @_;

  if( $self->{bInTextTag} ) {
    if( $hrTag->{sTagName} eq "/p" ) {
      # Round up any left-over w-tag
      $self->writeToken();
      $self->{bInPTag} = undef;
    }
    elsif($hrTag->{sTagName} eq "p") {
      $self->{bInPTag} = 1;
    }
    elsif( $hrTag->{sTagName} eq "/w" ) {
      $self->{bInWTag} = undef;
    }
    elsif( $hrTag->{sTagName} eq "w" ) {
      # Start a new one
      $self->startWTag($hrTag->{iEndPos});
    }
  }
  elsif( $hrTag->{sTagName} eq "text") {
    $self->{bInTextTag} = 1;
  }
  elsif( $hrTag->{sTagName} eq "/text") {
    $self->{bInTextTag} = undef;
  }
}

# Gets called when some text has been read
sub atText {
  my ($self, $hrText) = @_;

  return unless($self->{bInTextTag});

  if( $self->{bInWTag} ) {
    $self->{hrToken}->{sCanonicalWordFrom} = $hrText->{sText};
    # It could be that there is something before it already
    $self->{hrToken}->{sWordForm} .= $hrText->{sText};
    $self->{hrToken}->{iEndPos} = $hrText->{iEndPos};
  }
  elsif( $self->{bInPTag} ) {
    # Something at the end of a line
    if( $hrText->{sText} =~ /^([^\s\n]+)\s*\n\s*$/) {
      $self->{hrToken}->{sWordForm} .= $1;
      $self->writeToken();
    } # Something at the start of a line
    elsif( $hrText->{sText} =~ /^[\s\n]*([^\s\n]+)$/ ) {
      $self->writeToken();
      $self->{hrToken}->{sWordForm} .= $1;
    }
    else {
      #print "Skipping text: '$hrText->{sText}'\n"
      #	unless($hrText->{sText} =~ /^\s+$/s);
      $self->writeToken();
    }
  }
}

sub startWTag {
  my ($self, $iStartPos) = @_;

  $self->{bInWTag} = 1;
  $self->{hrToken}->{iStartPos} = $iStartPos;
}

sub writeToken {
  my ($self) = @_;

  die "ERROR: before printing \$self->{fhOut} should be set for " .
    "xmlParser::EventHandler::TEI2" unless(exists($self->{fhOut}));

  my $fhOut = $self->{fhOut};
  if( $self->{hrToken}->{iEndPos} ) {
    print $fhOut $self->{hrToken}->{sCanonicalWordFrom} . "\t" .
      $self->{hrToken}->{sWordForm} . "\t" .
	$self->{hrToken}->{iStartPos} . "\t" .
	  $self->{hrToken}->{iEndPos} . "\n";
  }
  $self->emptyToken();
}

sub emptyToken {
  my ($self) = @_;

  $self->{hrToken} = {sCanonicalWordFrom => '',
		      sWordForm => '',
		      iStartPos => undef,
		      iEndPos => undef};
}

1;
