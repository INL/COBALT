package xmlParser::EventHandler::buildTeiEntry;

use strict;

# Constructor
sub new {
  my ($class, %hOptions) = @_;

  my $self = \%hOptions;
  bless $self, $class;

  # Initialise
  # This happens in atStartOfFile

  # Initialize
  # $self->emptyTextBlock();

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

  if( $hrTag->{sTagName} eq "?xml" ) {
    $self->{hrHeader} = {};
    my %hTag = %{$hrTag};
    $self->{hrHeader}->{'?xml'} = \%hTag;
  }
  elsif( $hrTag->{sTagName} eq "TEI" ) {
    my %hTag = %{$hrTag};
    $self->{hrHeader}->{'TEI'} = \%hTag;
  }
  elsif( $hrTag->{sTagName} eq "teiHeader" ) {
    $self->{hrHeader}->{teiHeader} = {};
  }
  elsif($hrTag->{sTagName} eq "fileDesc" ) {
    $self->{hrHeader}->{teiHeader}->{fileDesc} = {};
  }
  elsif($hrTag->{sTagName} eq "titleStmt" ) {
    $self->{hrHeader}->{teiHeader}->{fileDesc}->{titleStmt} = [];
  }
  elsif($hrTag->{sTagName} eq "title" ) {
    $self->{bInTitleTag} = 1;
  }
  elsif($hrTag->{sTagName} eq "/title" ) {
    $self->{bInTitleTag} = undef;
  }
  elsif($hrTag->{sTagName} eq "publicationStmt" ) {
    $self->{hrHeader}->{teiHeader}->{fileDesc}->{publicationStmt} = [];
  }
  elsif($hrTag->{sTagName} eq "date" ) {
    $self->{bInDateTag} = 1;
  }
  elsif($hrTag->{sTagName} eq "/date" ) {
    $self->{bInDateTag} = undef;
  }
  elsif($hrTag->{sTagName} eq "sourceDesc" ) {
    $self->{hrHeader}->{teiHeader}->{fileDesc}->{sourceDesc} = [];
  }
  elsif($hrTag->{sTagName} eq "p" ) {
    $self->{bInPTag} = 1;
  }
  elsif($hrTag->{sTagName} eq "/p" ) {
    $self->{bInPTag} = undef;
  }
  elsif($hrTag->{sTagName} eq 'entry') {
    my %hTag = %$hrTag;
    $self->{hrEntry} = {hrTag => \%hTag,
			arForms => [],
		       };
    $self->{bInEntry} = 1;
  }
  elsif($hrTag->{sTagName} eq '/entry') {
    $self->{bInEntry} = undef;
  }
  elsif($hrTag->{sTagName} eq 'form') {
    # <form> tags can occur at different levels. This becomes a bit of a hassle
    # so we deal with it in a separate sub.
    $self->handleFormTag($hrTag);
  }
  elsif($hrTag->{sTagName} eq '/form') {
    if($self->{bInForm_cited}) {
      $self->{bInForm_cited} = undef;
    }
    elsif($self->{bInForm_historical}) {
      $self->{bInForm_historical} = undef;
    }
    elsif($self->{bInForm_wordForm}) {
      $self->{bInForm_wordForm} = undef;
    }
    # NOTE that we don't keep track of nested form tags. That is, we only
    # maintain a reference to the deepest form tag, and if it is done, we loose
    # track.
    # However, if a new form tag starts right after this one, hrCurrentFormTag
    # gets a value again.
    $self->{hrCurrentFormTag} = undef;
  }
  elsif($hrTag->{sTagName} eq 'orth') {
    $self->handleOrthTag($hrTag);
    $self->{bInOrthTag} = 1;
  }
  elsif($hrTag->{sTagName} eq '/orth') {
    ### $self->{hrCurrentOrthTag} = undef;
    $self->{bInOrthTag} = undef;
  }
  elsif($hrTag->{sTagName} eq 'gramGrp') {
    die "ERROR: Stray gramGrp in $self->{sInputFileName}.\n"
      unless( scalar(@{$self->{hrEntry}->{arForms}}) == 1);
    $self->{hrEntry}->{arForms}->[0]->{arGramGrp} = [];
  }
  elsif($hrTag->{sTagName} eq 'gram') { # Analogous to 'lbl'
    die "ERROR: Stray gram tag in $self->{sInputFileName}.\n"
      unless( scalar(@{$self->{hrEntry}->{arForms}}) == 1);
    my %hTag = %$hrTag;
    push(@{$self->{hrEntry}->{arForms}->[0]->{arGramGrp}}, {hrTag => \%hTag} );
    $self->{bInGramTag} = 1;
  }
  elsif($hrTag->{sTagName} eq '/gram') {
    $self->{bInGramTag} = undef;
  }
  elsif($hrTag->{sTagName} eq 'lbl') { # Analogous to 'gram'
    die "ERROR: Stray lbl tag in $self->{sInputFileName}.\n"
      unless( scalar(@{$self->{hrEntry}->{arForms}}) == 1);
    my %hTag = %$hrTag;
    $self->{hrEntry}->{arForms}->[0]->{lbl} = {hrTag => \%hTag};
    $self->{bInLblTag} = 1;
  }
  elsif($hrTag->{sTagName} eq '/lbl') {
    $self->{bInLblTag} = undef;
  }
  elsif($hrTag->{sTagName} eq 'cit') {
    die "ERROR: Stray cit tag in $self->{sInputFileName}.\n"
      unless( $self->{bInForm_cited});
    if(exists($self->{hrCurrentFormTag}->{arCits})) {
      push(@{$self->{hrCurrentFormTag}->{arCits}}, {sQuote => ''});
    }
    else {
      $self->{hrCurrentFormTag}->{arCits} = [{sQuote => ''}];
    }

    $self->{bInCitTag} = 1;
  }
  elsif($hrTag->{sTagName} eq '/cit') {
    $self->{bInCitTag} = undef;
  }
  elsif($hrTag->{sTagName} eq 'quote') {
    die "ERROR: Stray quote tag in $self->{sInputFileName}.\n"
      unless( $self->{bInCitTag});
    $self->{bInQuoteTag} = 1;
  }
  elsif($hrTag->{sTagName} eq '/quote') {
    $self->{bInQuoteTag} = undef;
  }
  elsif($hrTag->{sTagName} eq 'oVar') {
    die "ERROR: Stray oVar tag in $self->{sInputFileName}.\n"
      unless( $self->{bInQuoteTag});
    $self->{hrCurrentFormTag}->{arCits}->[$#{$self->{hrCurrentFormTag}->{arCits}}]->{sQuote} .= '<oVar>';
  }
  elsif($hrTag->{sTagName} eq '/oVar') {
    $self->{hrCurrentFormTag}->{arCits}->[$#{$self->{hrCurrentFormTag}->{arCits}}]->{sQuote} .= '</oVar>';
  }
  elsif($hrTag->{sTagName} eq 'bibl') {
    if( $self->{bInCitTag} ) {
      $self->{hrCurrentFormTag}->{arCits}->[$#{$self->{hrCurrentFormTag}->{arCits}}]->{arBibl} = [{}];
    }
    elsif( ! $self->{bInForm_wordForm} ) {
      die "ERROR: Stray bibl tag in $self->{sInputFileName}.\n";
    }
    $self->{bInBiblTag} = 1;
  }
  elsif($hrTag->{sTagName} eq '/bibl') {
    $self->{bInBiblTag} = undef;
  }
  elsif($hrTag->{sTagName} eq 'gloss') {
    $self->{bInGlossTag} = 1;
  }
  elsif($hrTag->{sTagName} eq '/gloss') {
    $self->{bInGlossTag} = undef;
  }
  elsif($hrTag->{sTagName} eq 'title') {
    die "ERROR: Stray title tag in $self->{sInputFileName}.\n"
      unless( $self->{bInBiblTag});
    $self->{bInTitleTag} = 1;
  }
  elsif($hrTag->{sTagName} eq '/title') {
    $self->{bInTitleTag} = undef;
  }
  elsif($hrTag->{sTagName} eq 'author') {
    die "ERROR: Stray author tag in $self->{sInputFileName}.\n"
      unless( $self->{bInBiblTag});
    $self->{bInAuthorTag} = 1;
  }
  elsif($hrTag->{sTagName} eq '/author') {
    $self->{bInAuthorTag} = undef;
  }
  elsif($hrTag->{sTagName} eq 'dateRange') {
    die "ERROR: Stray dateRange tag in $self->{sInputFileName}.\n"
      unless( $self->{bInBiblTag});
    my %hTag = %$hrTag;
    my $hrLastCit = $self->{hrCurrentFormTag}->{arCits}->[$#{$self->{hrCurrentFormTag}->{arCits}}];
    $hrLastCit->{arBibl}->[$#{$hrLastCit->{arBibl}}]->{dateRange} =
      {hrTag => \%hTag};
  }
  elsif( ! $self->knownTag($hrTag->{sTagName}) ) {
    die "ERROR: Unknown tag '$hrTag->{sTagName}' in $self->{sInputFileName}.\n";
  }
}

# Gets called when some text has been read
sub atText {
  my ($self, $hrText) = @_;

  if( $self->{bInTitleTag} ) {
    if( $self->{bInBiblTag} ) {
      my $hrLastCit = $self->{hrCurrentFormTag}->{arCits}->[$#{$self->{hrCurrentFormTag}->{arCits}}];
      $hrLastCit->{arBibl}->[$#{$hrLastCit->{arBibl}}]->{sTitle} =
	$hrText->{sText};
    }
    else {
      push(@{$self->{hrHeader}->{teiHeader}->{fileDesc}->{titleStmt}},
	   {sTag => 'title',
	    sText => $hrText->{sText}});
    }
  }
  elsif( $self->{bInDateTag} ) {
    push(@{$self->{hrHeader}->{teiHeader}->{fileDesc}->{publicationStmt}},
	 {sTag => 'date',
	  sText => $hrText->{sText}});

  }
  elsif( $self->{bInPTag} ) {
    push(@{$self->{hrHeader}->{teiHeader}->{fileDesc}->{sourceDesc}},
	 {sTag => 'p',
	  sText => $hrText->{sText}});
  }
  elsif( $self->{bInLblTag} ) {
    $self->{hrEntry}->{arForms}->[0]->{lbl}->{sText} = $hrText->{sText};
  }
  elsif( $self->{bInGramTag} ) {
    # Get the last entry of the gramGrp array, and the text to it.
    $self->{hrEntry}->{arForms}->[0]->{arGramGrp}->[$#{$self->{hrEntry}->{arForms}->[0]->{arGramGrp}}]->{sText} = $hrText->{sText};
  }
  elsif( $self->{bInOrthTag} ) {
    # Maybe this is a bit superfluous, as bInOrthTag is only TRUE when
    # handleOrthTag() was called, and returned successfully
    die "ERROR: error in handling orth tag in $self->{sInputFileName}.\n"
      if( (! $self->{hrCurrentFormTag}) &&
	  ( exists($self->{hrCurrentFormTag}->{hrOrthTag}->{sText})) );

    $self->{hrCurrentFormTag}->{hrOrthTag}->{sText} = $hrText->{sText};
  }
  elsif( $self->{bInQuoteTag} ) {
    $self->{hrCurrentFormTag}->{arCits}->[$#{$self->{hrCurrentFormTag}->{arCits}}]->{sQuote} .= $hrText->{sText};
  }
  elsif( $self->{bInAuthorTag} ) {
    my $hrLastCit = $self->{hrCurrentFormTag}->{arCits}->[$#{$self->{hrCurrentFormTag}->{arCits}}];
    $hrLastCit->{arBibl}->[$#{$hrLastCit->{arBibl}}]->{sAuthor} =
      $hrText->{sText};
  }
  elsif( $self->{bInBiblTag} ) {
    # NOTE that we ignore 'white space only'
    if( $hrText->{sText} =~ /\S/ ) {
      if($self->{bInCitTag} || $self->{bInForm_wordForm} ) {


	my $hrLastCit = $self->{hrCurrentFormTag}->{arCits}->[$#{$self->{hrCurrentFormTag}->{arCits}}];
	$hrLastCit->{arBibl}->[$#{$hrLastCit->{arBibl}}]->{sText} =
	  $hrText->{sText};

      }
      else {
	die "ERROR: Don't know what to do with <bibl> " .
	  "in $self->{sInputFileName}.\n";
      }
    }
  }
  elsif( $self->{bInGlossTag} ) {
    $self->{hrCurrentFormTag}->{sGloss} = $hrText->{sText};
  }
}

# Additional sub routines #####################################################

sub handleFormTag {
  my ($self, $hrTag) = @_;

  if( $self->{bInEntry} ) {
    if( $self->{bInForm_wordForm} ) { # The tag is nested inside a form tag
      if( $self->{bInForm_historical} ) { # Nested in yet another form tag
	my %hTag = %$hrTag;
	# Get the last element of the forms of the forms of the entry
	my $arEntryForms = $self->{hrEntry}->{arForms};
	my $hrLastEntryForm = $arEntryForms->[$#{$arEntryForms}];
	my $hrLastWordFormForm =
	  $hrLastEntryForm->{arForms}->[$#{$hrLastEntryForm->{arForms}}];
	push(@{$hrLastWordFormForm->{arForms}}, {hrTag => \%hTag} );
	# Keep a reference to the form tag at hand.
	$self->{hrCurrentFormTag} =
	  $hrLastWordFormForm->{arForms}->[$#{$hrLastWordFormForm->{arForms}}];
	$self->{bInForm_cited} = 1;
      }
      else { # At word form level
	my %hTag = %$hrTag;
	# Get the last element from the forms in the entry
	my $arEntryForms = $self->{hrEntry}->{arForms};
	my $hrLastEntryForm = $arEntryForms->[$#{$arEntryForms}];
	push(@{$hrLastEntryForm->{arForms}}, {hrTag => \%hTag});
	# Keep a reference to the form tag at hand.
	$self->{hrCurrentFormTag} =
	  $hrLastEntryForm->{arForms}->[$#{$hrLastEntryForm->{arForms}}];
	$self->{bInForm_historical} = 1;
      }
    }
    else { # A non-nested form tag. So it is at entry level
      my %hTag = %$hrTag;
      push(@{$self->{hrEntry}->{arForms}}, { hrTag => \%hTag } );
      # Keep a reference to the form tag at hand.
      $self->{hrCurrentFormTag} =
	$self->{hrEntry}->{arForms}->[$#{$self->{hrEntry}->{arForms}}];
      $self->{bInForm_wordForm} = 1; # Now we are in the tag
    }
  }
  else {
    die "ERROR: stray 'form' tag in $self->{sInputFileName}.\n";
  }
}

sub handleOrthTag {
  my ($self, $hrTag) = @_;

  my %hTag = %$hrTag;
  $self->{hrCurrentFormTag}->{hrOrthTag} = {hrTag => \%hTag};
}


# This is an initialisation function
sub atStartOfFile {
  my ($self, $sInputFileName) = @_;

  $self->{sInputFileName} =
    (defined($sInputFileName)) ? $sInputFileName : "XML string";
  $self->{bInTitleTag} = undef;
  $self->{bInDateTag} = undef;
  $self->{bInPTag} = undef;
  $self->{bInEntry} = undef;
  $self->{bInForm_wordForm} = undef;
  $self->{bInForm_historical} = undef;
  $self->{bInForm_cited} = undef;
  $self->{bInOrthTag} = undef;
  $self->{bInGramTag} = undef
  $self->{bInLblTag} = undef;
  $self->{bInCitTag} = undef;
  $self->{bInQuoteTag} = undef;
  $self->{bInBiblTag} = undef;
  $self->{bInGlossTag} = undef;
  $self->{bInTitleTag} = undef;
  $self->{bInAuthorTag} = undef;
  $self->{sText} = '';
  $self->{hrEntry} = undef;
  $self->{hrHeader} = undef;
  $self->{hrCurrentFormTag} = undef;
  $self->initKnownTags();
}

sub atEndOfFile {
  my ($self) = @_;

}

# This is only here to make sure we will notice when a tag is there which we
# hadn't anticipated.
sub initKnownTags {
  my ($self) = @_;

  # NOTE that we only do this if we haven't done so before
  # Because initialisation is done at start of file, it can happen more often
  # when we parse chunks.
  $self->{hrKnownTags} = {'?xml' => 1,
			  'TEI' => 1,
			  '/TEI' => 1,
			  teiHeader => 1,
			  '/teiHeader' => 1,
			  fileDesc => 1,
			  '/fileDesc' => 1,
			  titleStmt => 1,
			  '/titleStmt' => 1,
			  title => 1,
			  '/title' => 1,
			  publicationStmt => 1,
			  '/publicationStmt' => 1,
			  date => 1,
			  '/date' => 1,
			  sourceDesc => 1,
			  '/sourceDesc' => 1,
			  p => 1,
			  '/p' => 1,
			  text => 1,
			  '/text' => 1,
			  body => 1,
			  '/body' => 1,
			  entry => 1,
			  '/entry' => 1,
			  form => 1,
			  '/form' => 1,
			  orth => 1,
			  '/orth' => 1,
			  gramGrp => 1,
			  '/gramGrp' => 1,
			  gram => 1,
			  '/gram' => 1,
			  lbl => 1,
			  '/lbl' => 1,
			  cit => 1,
			  '/cit' => 1,
			  quote => 1,
			  '/quote' => 1,
			  bibl => 1,
			  '/bibl' => 1,
			  gloss => 1,
			  '/gloss' => 1,
			  title => 1,
			  '/title' => 1,
			  author => 1,
			  '/author' => 1,
			  dateRange => 1,
			  '/dataRange' => 1,
			  oVar => 1,
			  '/oVar' => 1
			 } unless (exists($self->{hrKnownTags}));
}

sub knownTag {
  my ($self, $sTag) = @_;

  return exists($self->{hrKnownTags}->{$sTag});
}

1;
