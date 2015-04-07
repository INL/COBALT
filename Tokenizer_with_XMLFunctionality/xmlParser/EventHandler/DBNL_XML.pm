package xmlParser::EventHandler::DBNL_XML;

use strict;

use DBI;

# Deze is gemaakt om de milestones in het DBNL materiaal te interpreteren en
# om te zetten naar DBNL-compliant TEI XML.

# Constructor
sub new {
  my ($class, %hOptions) = @_;

  # Call the constructor of the class we inherit from
  my $self = \%hOptions;
  bless $self, $class;

  # Initialise
  $self->{arMilestoneTags} = [];
  $self->{sText} = '';
  # The constructor should be called with an sDatabase parameter
  $self->initDatabase();

  open(FH_SCRIPT_OUTPUT, "> $self->{sOutputDir}/scriptOutput.txt") or
    die "Couldn't open $self->{sOutputDir}/scriptOutput.txt for writing: $!\n";
  select(FH_SCRIPT_OUTPUT);
  $|++; # Autoflush
  # Dit doe ik omdat je dan het script middenin kan afbreken en dat de output
  # dan ook nog echt in die file staat (in plaats van niks).
  $self->{fhScriptOutput} = *FH_SCRIPT_OUTPUT;
  select(STDOUT); # Restore default

  return $self;
}

# This one is called when a tag has been read completely
#
sub atTag {
  my ($self, $hrTag) = @_;

  if($hrTag->{sTagName} eq 'milestone') {
    # Kwam voor...
    $hrTag->{hrAttributes}->{unit} = "bo"
      if( $hrTag->{hrAttributes}->{unit} eq "`bo");

    if( ($hrTag->{hrAttributes}->{unit} ne "bo") &&
	($hrTag->{hrAttributes}->{unit} ne "eo") ) {
      # be en ee slaan we gewoon over...
      $self->printOut("ERROR in '$self->{sFileName}': " .
		      "Wat doen we met milestone " .
		      "$hrTag->{hrAttributes}->{unit} '$hrTag->{sTagText}'?\n")
	unless( ($hrTag->{hrAttributes}->{unit} eq 'be') ||
		($hrTag->{hrAttributes}->{unit} eq 'ee') );
      # andere dan eo en bo moeten wel gewoon geechood (Jesse)
      $self->{sText} .= $hrTag->{sTagText};
    }
    else { # Print it, but without ana/corresp, and add an id
      if( exists($hrTag->{hrAttributes}->{n}) &&
	  length($hrTag->{hrAttributes}->{n}) ) {
	my $sMilestoneId;
	if( $hrTag->{hrAttributes}->{unit} eq 'bo' ) { # Start of milestone;
	  # Add some info to the tag for later on
	  # E.g: bred001groo01_1
	  $hrTag->{sBeginMilestoneId} = "milestone_$self->{sFileBaseName}_" .
	    "bo_$hrTag->{hrAttributes}->{n}";
	  $hrTag->{sEndMilestoneId} = "milestone_$self->{sFileBaseName}_" .
	    "eo_$hrTag->{hrAttributes}->{n}";
	  $hrTag->{sInterpGrpId} = "interp_$self->{sFileBaseName}_" .
	    "$hrTag->{hrAttributes}->{n}";

	  $sMilestoneId = $hrTag->{sBeginMilestoneId};

	  # Remember the tag
	  push(@{$self->{arMilestoneTags}}, $hrTag);
	}
	else { # End tag
	  $sMilestoneId = "milestone_$self->{sFileBaseName}_" .
	    "eo_$hrTag->{hrAttributes}->{n}";
	}
	$self->{sText} .=	"<milestone id=\"$sMilestoneId\" " .
	  "n=\"$hrTag->{hrAttributes}->{n}\" " .
	    "unit=\"$hrTag->{hrAttributes}->{unit}\"/>\n";
      }
      else {
	$self->printOut("ERROR in '$self->{sFileName}': " .
			"Geen (goede) n attribute in '$hrTag->{sTagText}' " .
			"voor '$self->{sFileBaseName}'.\n")
      }
    }
  }
  else {
    $self->{sText} .= $hrTag->{sTagText};
  }
}

# Is called when some text has been read
#
sub atText {
  my ($self, $hrText) = @_;

  $self->{sText} .= $hrText->{sText};
}

sub setOutputFileHandle {
  my ($self, $fhOut) = @_;

  $self->{fhOut} = $fhOut;
}

sub emptyText {
  my ($self) = @_;

  $self->{sText} = '';
  $self->{arMilestoneTags} = [];
}

sub setFileBaseName {
  my ($self, $sFileName) = @_;

  # If we set a new file base name, we are presumably dealing with a new text
  $self->emptyText();

  $self->{sFileName} = $sFileName;
  if( $sFileName =~ /^(.+)\.xml$/i ) {
    $self->{sFileBaseName} = $1;
    # Zoals +dat enzo
    $self->{sFileBaseName} =~ s/_01([\+_-]\D+)*$//;
    ###
    ### print "Base name: $self->{sFileBaseName}.\n";
    ###
    $self->findMetadataRecord();
  }
  else {
    die "ERROR: Couldn't find file base name for '$sFileName'.\n";
  }
}

sub atEndOfFile {
  my ($self) = @_;

  ###
  ### $DB::single = 1;
  ###

  my $sComCorFile = $self->isComCorFile();

  my $sListBibl = "\n<listBibl id=\"inlMetadata\">\n";
  foreach my $hrTag (@{$self->{arMilestoneTags}}) {
    my $sAuthors ='';
    my $hrDates;

    # getMilestoneRecord() print een error als er geen record is in db
    my $hrResultRow = $self->getMilestoneRecord($hrTag);

    # Bij comcor files heeft de tag voorrang boven de database
    if( $sComCorFile ) {
      # Als er helemaal geen ana was, dan pakken we toch de db
      if( ! exists($hrTag->{hrAttributes}->{ana}) ) {
	if( $hrResultRow ) {
	  $hrDates->{sDateFrom} = $hrResultRow->{dateFrom}
	    if($hrResultRow->{dateFrom});
	  $hrDates->{sDateTo} = $hrResultRow->{dateTo}
	    if($hrResultRow->{dateTo});
	  $hrDates->{sDateWitnessFrom} = $hrResultRow->{dateWitnessFrom}
	    if($hrResultRow->{dateWitnessFrom});
	  $hrDates->{sDateWitnessTo} = $hrResultRow->{dateWitnessTo}
	    if($hrResultRow->{dateWitnessTo});
	}
      }
      else { # Gewone geval: alles uit de tag
	# Data
	$hrDates = getAnas($hrTag->{hrAttributes}->{ana});
      }

      # Auteur
      # Die pakken we ook eerst uit de tag, maar als die er niet is uit de db
      # (dus het kan zijn dat de data uit de db en de auteur uit de tag of
      # andersom).

      if($hrResultRow->{auteurStukje}) {
	# Eerst nog wat opschonen
	$hrResultRow->{auteurStukje} =~ s/^\s+//;
	$hrResultRow->{auteurStukje} =~ s/\,?\s+$//;
	$sAuthors = "\n   " .
	  " <interpGrp type=\"author.level1\"><interp value=\"$hrResultRow->{auteurStukje}\"/></interpGrp>";
	warn $sAuthors;
      } elsif($hrResultRow->{authors}) {
	# Eerst nog wat opschonen
	$hrResultRow->{authors} =~ s/^\s+//;
	$hrResultRow->{authors} =~ s/\,?\s+$//;
	$sAuthors = "\n   " .
	  "<interpGrp type=\"author.level2\"><interp value=\"$hrResultRow->{authors}\"/></interpGrp>";
      }
      elsif(exists($hrTag->{hrAttributes}->{sAuthors})) {
	# Eerst nog wat opschonen
	$hrTag->{hrAttributes}->{sAuthors} =~ s/^\s+//;
	$hrTag->{hrAttributes}->{sAuthors} =~ s/\,?\s+$//;
	$sAuthors = "\n   <interpGrp type=\"author.level2\"><interp " .
	  "value=\"$hrTag->{hrAttributes}->{sAuthors}\"/></interpGrp>";
      }

      levelDates($hrDates, 'sDateFrom', 'sDateTo');
      # Zelfde voor dateWitness
      levelDates($hrDates, 'sDateWitnessFrom', 'sDateWitnessTo');
    }
    else { # Geen comcor file, kijk in de database
      # Pak ook de data uit de tag
      my $hrAnaDates;
      $hrAnaDates = getAnas($hrTag->{hrAttributes}->{ana})
	if(exists($hrTag->{hrAttributes}->{ana}));

      if( $hrResultRow ) {
	$hrDates->{sDateFrom} = $hrResultRow->{dateFrom}
	  if($hrResultRow->{dateFrom});
	$hrDates->{sDateTo} = $hrResultRow->{dateTo}
	  if($hrResultRow->{dateTo});
	$hrDates->{sDateWitnessFrom} = $hrResultRow->{dateWitnessFrom}
	  if($hrResultRow->{dateWitnessFrom});
	$hrDates->{sDateWitnessTo} = $hrResultRow->{dateWitnessTo}
	  if($hrResultRow->{dateWitnessTo});

	# Als de tekst data hetzelfde zijn kunnen we de tekst getuige wel
	# pakken uit de tag als die dat wel heeft en de database niet.
	if( $hrAnaDates && $hrDates &&
	    exists($hrDates->{sDateFrom}) && exists($hrDates->{sDateTo}) &&
	    exists($hrAnaDates->{sDateFrom}) &&
	    exists($hrAnaDates->{sDateTo}) &&
	    ( exists($hrAnaDates->{sDateWitnessFrom}) ||
	      exists($hrAnaDates->{sDateWitnessTo}) )
	  ) {
	  if( ($hrAnaDates->{sDateFrom} eq $hrDates->{sDateFrom}) &&
	      ($hrAnaDates->{sDateTo} eq $hrDates->{sDateTo}) ) {
	    $hrDates->{sDateWitnessFrom} = $hrAnaDates->{sDateWitnessFrom};
	    $hrDates->{sDateWitnessTo} = $hrAnaDates->{sDateWitnessTo};
	  }
	  else {
	    $self->printOut(">> Ja, dat komt dus voor " .
			    "($self->{sFileBaseName}): " .
			    "dateFrom/dateTo verschillen " .
			    "en er is wel een dateWitness in de tag " .
			    "(ana dateFrom: $hrAnaDates->{sDateFrom} <->" .
			    " db dateFrom: $hrDates->{sDateFrom}) en " .
			    "(ana dateTo: $hrAnaDates->{sDateTo} <->" .
			    " db dateTo: $hrDates->{sDateTo})\n");
	  }
	}

	# Als we geen dateTo/dateFrom velden in de database hadden dan pakken
	# we die van de tag.
	if( (! $hrDates->{sDateFrom}) && (! $hrDates->{sDateFromTo}) &&
	    $hrAnaDates &&
	    ( exists($hrAnaDates->{sDateFrom}) ||
	      exists($hrAnaDates->{sDateFrom})) ) {
	  $hrDates->{sDateFrom} = $hrAnaDates->{sDateFrom};
	  $hrDates->{sDateTo} = $hrAnaDates->{sDateTo};
	  $hrDates->{bCirca} = 1 if( exists($hrAnaDates->{bCirca}));
	}

	levelDates($hrDates, 'sDateFrom', 'sDateTo');
	# Zelfde voor dateWitness
	levelDates($hrDates, 'sDateWitnessFrom', 'sDateWitnessTo');

	# Auteur ook alleen als ingevuld
        if ($hrResultRow->{auteurStukje}) {
        # Eerst nog wat opschonen
        $hrResultRow->{auteurStukje} =~ s/^\s+//;
        $hrResultRow->{auteurStukje} =~ s/\,?\s+$//;
        $sAuthors = "\n   " .
          "<interpGrp type=\"authors\"><interp value=\"$hrResultRow->{auteurStukje}\"/></interpGrp>";
         warn $sAuthors;
      } elsif($hrResultRow->{authors}) {
	  # Eerst nog wat opschonen
	  $hrResultRow->{authors} =~ s/^\s+//;
	  $hrResultRow->{authors} =~ s/\,?\s+$//;
	  $sAuthors = "\n   " .
	    "<interpGrp type=\"authors\"><interp value=\"$hrResultRow->{authors}\"/></interpGrp>";
	}
      }
      else { # Als er geen database rij is dan pakken we de info uit de tag
	if( $hrAnaDates ) {
	  %$hrDates = %$hrAnaDates; # Copy

	  levelDates($hrDates, 'sDateFrom', 'sDateTo');
	  # Zelfde voor dateWitness
	  levelDates($hrDates, 'sDateWitnessFrom', 'sDateWitnessTo');

	}
	else {
	  $self->printOut("ERROR in '$self->{sFileBaseName}': " .
			  "geen data in tag noch in de database voor " .
			  "$hrTag->{sTagText}'\n");
	}
      }
    }

    my $sComCor = ($sComCorFile) ? " (comcor: $sComCorFile)" : '';
    if( exists($hrDates->{sDateFrom}) || exists($hrDates->{sDateTo}) ||
	exists($hrDates->{sDateWitnessFrom}) ||
	exists($hrDates->{sDateWitnessTo}) ) {
      next if( $self->dateNotSpecificEnough($hrDates, $hrTag, $sComCor));

      my ($sDatePrint,$sNewLine) = ('', '');
      # Moeten altijd of allebei niet of allebei wel gedefinieerd zijn.
      if( exists($hrDates->{sDateFrom}) && $hrDates->{sDateFrom} ) {
	$sDatePrint = "   <interpGrp type=\"textYear_from\"><interp" .
	  " value=\"$hrDates->{sDateFrom}\"/></interpGrp>\n" .
	    "    <interpGrp type=\"textYear_to\"><interp value=\"$hrDates->{sDateTo}\"/></interpGrp>";
	$sNewLine = "\n";
      }

      # Moeten altijd of allebei niet of allebei wel gedefinieerd zijn.
      my $sDateWitnessPrint = '';
      if( exists($hrDates->{sDateWitnessFrom}) &&
	  $hrDates->{sDateWitnessFrom} ) {
	$sDateWitnessPrint = "$sNewLine    <interpGrp type=\"witnessYear_from\"><interp" .
	  " value=\"$hrDates->{sDateWitnessFrom}\"/></interpGrp>\n" .
	    "    <interpGrp type=\"witnessYear_to\"><interp" .
	      " value=\"$hrDates->{sDateWitnessTo}\"/></interpGrp>";
      }
      $sListBibl .= <<BIBL;
 <bibl id="$hrTag->{sInterpGrpId}">
$sDatePrint$sDateWitnessPrint$sAuthors
  <biblScope>
    <xref from="$hrTag->{sBeginMilestoneId}" to="$hrTag->{sEndMilestoneId}"/>
  </biblScope>
 </bibl>
BIBL

      $self->printOut("ERROR ($self->{sFileBaseName}: " .
		      "Wat doen we met circa '$hrTag->{sTagText}'\n")
	if( exists($hrDates->{bCirca}) && $hrDates->{bCirca} );
    }
    else { # Geen dateFrom/dateTo/dateWitnessFrom/dateWitnessTo
      $self->printOut("ERROR in '$self->{sFileBaseName}'$sComCor: " .
		      "Geen dateFrom/dateTo/dateWitnessFrom/dateWitnessTo " .
		      "voor '$hrTag->{sTagText}', ook niet in db.\n");
    }
  }

  # Voeg de inlMetadata/listBibl toe als er ook echt wat toe te voegen is
  $self->{sText} =~ s#</sourceDesc>#$sListBibl</listBibl>\n</sourceDesc>#
    if( length($sListBibl) > 30);

  # Print resulting XML
  my $fhOut = $self->{fhOut};
  print $fhOut $self->{sText};
}

sub dateNotSpecificEnough {
  my ($self, $hrDates, $hrTag, $sComCor) = @_;

  unless($hrDates &&
	 ( (! exists($hrDates->{sDateFrom})) ||
	   $hrDates->{sDateFrom} =~ /^(\d{4}|UNKNOWN)$/) &&
	 ( (! exists($hrDates->{sDateTo})) ||
	   $hrDates->{sDateTo} =~ /^(\d{4}|UNKNOWN)$/) &&
	 ( (! exists($hrDates->{sDateWitnessFrom})) ||
	   $hrDates->{sDateWitnessFrom} =~ /^(\d{4}|UNKNOWN)$/) &&
	 ( (! exists($hrDates->{sDateWitnessTo})) ||
	   $hrDates->{sDateWitnessTo} =~ /^(\d{4}|UNKNOWN)$/) ) {
    my $sOutput = "ERROR in '$self->{sFileBaseName}'$sComCor: " .
      " sommige data niet specifiek genoeg. milestone '$hrTag->{sTagText}' ";
    $sOutput .= "tekst vanaf '$hrDates->{sDateFrom}' "
      if( exists($hrDates->{sDateFrom}) );
    $sOutput .= "tekst tot '$hrDates->{sDateTo}' "
      if( exists($hrDates->{sDateTo}) );
    $sOutput .= "tekstgetuige vanaf '$hrDates->{sDateWitnessFrom}' "
      if( exists($hrDates->{sDateWitnessFrom}) );
    $sOutput .= "tekstgetuige tot '$hrDates->{sDateWitnessTo}' "
      if( exists($hrDates->{sDateWitnessTo}) );

    $self->printOut("$sOutput\n");
    return 1;
  }
  return undef;
}

sub levelDates {
  my ($hrDates, $sDateFrom, $sDateTo) = @_;

  # Als we alleen dateTo weten blijft dateFrom UNKNOWN.
  # Als we alleen dateFrom weten maken we dateTo daar gelijk aan.
  if( $hrDates ) {
    if( exists($hrDates->{$sDateTo}) &&
	(! exists($hrDates->{$sDateFrom})) ) {
      $hrDates->{$sDateFrom} = 'UNKNOWN';
    }
    elsif( exists($hrDates->{$sDateFrom}) &&
	   (! exists($hrDates->{$sDateTo})) ) {
      $hrDates->{$sDateTo} = $hrDates->{$sDateFrom};
    }
  }
}

sub isComCorFile {
  my ($self) = @_;

  my $sComCorDir =
    "/mnt/Projecten/Taalbank/Klusbestanden/Bewerkte_bestanden/DBNLSpookhuis/" .
      "comcor_die_je_juist_wel_moet_hebben_maar_die_we_al_gepakt_hebben";
  opendir(my $dhDir, $sComCorDir)
    or die "can't opendir $sComCorDir: $!";
  my $sBaseName = $self->{sFileBaseName};
  my @aFiles = grep { /^$sBaseName/ } readdir($dhDir);
  closedir $dhDir;

  if(scalar(@aFiles) > 1) {
    die "ERROR: More than one file matching is $sComCorDir for " .
      "$self->{sFileBaseName}.\n";
  }
  else {
    # Sommige files bestaan niet in de DBNLMilestones dir..?!?
    if( scalar(@aFiles)
	## && ($aFiles[0] =~ /comcor/) <- hoeft nu niet meer...
      ) {
      return $aFiles[0];
    }
    else {
      return undef;
    }
  }
}

sub getMilestoneRecord {
  my ($self, $hrTag) = @_;

  my %hResultRow;
  foreach ( @{$self->{arDatabaseNumbers}}) {
    $self->{"qhSelectMilestone_$_"}->execute($self->{sFileBaseName},
					     $hrTag->{hrAttributes}->{n});
    if(my $hrRow = $self->{"qhSelectMilestone_$_"}->fetchrow_hashref()) {
      # Copy row
      %hResultRow = %$hrRow;
      # print "Yes, found it in dateringsklus_$_\n";
    }
    $self->{"qhSelectMilestone_$_"}->finish();

    last if(%hResultRow);
  }

  if( %hResultRow ) {
    foreach my $k (keys %hResultRow)
    {
       if ($hResultRow{$k} =~ /"/)
       {
         warn "KWOOTJES: $k --> $hResultRow{$k}";
         $hResultRow{$k} =~ s/"/&quot;/g;
       }
       $hResultRow{$k} =~ s/& /&amp; /g;
    }
    return \%hResultRow;
  }
  else {
    # Alleen een 'ana' is ook al goed (en geen 'corresp')
    $self->printOut("ERROR in '$self->{sFileName}': " .
		    "Geen milestone '$hrTag->{sTagText}' voor " .
		    "'$self->{sFileBaseName}' in de database.\n")
      unless( exists($hrTag->{hrAttributes}->{ana}) );
    return undef;
  }
}

sub findMetadataRecord {
  my ($self) = @_;

  $self->{qhSelectMetadataRecord}->execute($self->{sFileBaseName});

  if(my $hrRow = $self->{qhSelectMetadataRecord}->fetchrow_hashref()) {
    ; # Even niks
  }
  else {
    $self->printOut("ERROR: Kon geen metadata vinden voor " .
		    "'$self->{sFileBaseName}'.\n");
  }
  $self->{qhSelectMetadataRecord}->finish();
}


sub initDatabase {
  my ($self) = @_;

  die "ERROR: Can't connect to empty database..." 
    unless(exists($self->{sDatabase}) && length($self->{sDatabase}));
  $self->{dbh} =
    DBI->connect("dbi:mysql:" . $self->{sDatabase} . ":impactdb:3306",
		 'impact', 'impact',
		 {RaiseError => 1} );
  $self->{dbh}->{'mysql_enable_utf8'} = 1;
  $self->{dbh}->do('SET NAMES utf8');

  $self->{arDatabaseNumbers} = ['1', '2_1', '2_2', '2_3', '2_4', '3'];
  foreach ( @{$self->{arDatabaseNumbers}}) {
    $self->{"qhSelectMilestone_$_"} =
      $self->{dbh}->prepare("SELECT *".
			    "  FROM dateringsklus_$_" .
			    " WHERE docid = ?" .
			   "    AND milestone = ?");
  }
  $self->{qhSelectMetadataRecord} =
    $self->{dbh}->prepare("SELECT * FROM metadata WHERE id = ?");
}

sub printOut {
  my ($self, $sString) = @_;

  print $sString;
  my $fhScriptOut = $self->{fhScriptOutput};
  print $fhScriptOut $sString;
}

sub closeDatabase {
  my ($self) = @_;

  $self->{dbh}->disconnect() if( exists($self->{dbh}) );
}

sub closeScriptOutputFileHandler {
  my ($self) = @_;

  close($self->{fhScriptOutput});
}

# Losse functie ###############################################################

sub getAnas {
  my ($sAna) = @_;

  return undef unless($sAna);

  # d1889 -> 1889 -> tekst
  if( $sAna =~ /^d(\d{4})$/i) {
    return {sDateFrom => $1,
	    sDateTo => $1};
  } # d1868_1894 -> tekst_tekstgetuige (d11701_1791 <- Komt voor, typefoutje..)
  elsif( $sAna =~ /^d1?(\d{4})_(\d{4})$/i ) {
    return {sDateFrom => $1,
	    sDateTo => $1,
	    sDateWitnessFrom => $2,
	    sDateWitnessTo => $2};
  } # 1867-1898 -> tekst from - to
  elsif( $sAna =~ /^d(\d{4})\-(\d{4})$/i ) {
    return {sDateFrom => $1,
	    sDateTo => $2};
  } # d1886-87 -> tekst from 1886 to 1887
  elsif( $sAna =~ /d(\d{2})(\d{2})-(\d{2})$/i ) {
    return {sDateFrom => "$1$2",
	    sDateTo => "$1$3"};
  } # ca1850 -> 
  elsif( $sAna =~ /^d?c[a\.]?(\d{4})$/i ) {
    return {sDateFrom => $1 - 5,
	    sDateTo => $1 + 5};
  } # d897 -> 1897 (typefoutje)
  elsif( $sAna =~ /^d(\d{3})$/i ) {
    return {sDateFrom => "1$1",
	    sDateTo => "1$1"};
  } # d1906_906 (typefoutje);
  elsif( $sAna =~ /^d(\d{4})_(\d{3})$/i ) {
    return {sDateFrom => $1,
	    sDateTo => $1,
	    sDateWitnessFrom => "1$2",
	    sDateWitnessTo => "1$2"};
  } # d1632-34_1682 -> tekst from - to _ tekstgetuige
  elsif( $sAna =~ /^d(\d{2})(\d{2})-(\d{2})_(\d{4})$/i ) {
    return {sDateFrom => "$1$2",
	    sDateTo => "$1$3",
	    sDateWitnessFrom => $4,
	    sDateWitnessTo => $4 };
  } # d1650-51_1650-51 -> tekst from - to - tekstgetuige from - to
  elsif( $sAna =~ /^d(\d{2})(\d{2})-(\d{2})_(\d{2})(\d{2})-(\d{2})$/i ) {
    return {sDateFrom => "$1$2",
	    sDateTo => "$1$3",
	    sDateWitnessFrom => "$4$5",
	    sDateWitnessTo => "$4$6"};
  } # d1650_1650-51 -> tekst from - to - tekstgetuige from - to
  elsif( $sAna =~ /^d(\d{4})_(\d{2})(\d{2})-(\d{2})$/i ) {
    return {sDateFrom => $1,
	    sDateTo => $1,
	    sDateWitnessFrom => "$2$3",
	    sDateWitnessTo => "$2$4"};
  } # d1626__1700-1799
  elsif( $sAna =~ /^d(\d{4})_+(\d{4})\-(\d{4})$/i ) {
    return {sDateFrom => $1,
	    sDateTo => $1,
	    sDateWitnessFrom => $2,
	    sDateWitnessTo => $3};
  } # d1614-1615_1622
  elsif( $sAna =~ /^d(\d{4})\-(\d{4})_(\d{4})$/i ) {
    return {sDateFrom => $1,
	    sDateTo => $2,
	    sDateWitnessFrom => $3,
	    sDateWitnessTo => $3};
  } # d1644-1659_1644-1659
  elsif( $sAna =~ /^d(\d{4})\-(\d{4})_(\d{4})\-(\d{4})$/i ) {
    return {sDateFrom => $1,
	    sDateTo => $2,
	    sDateWitnessFrom => $3,
	    sDateWitnessTo => $4};
  } # d _1600-1617 | d--_1645-1646
  elsif( $sAna =~ m!^d[\s-]*_(\d{4})\-(\d{4})$!i ) {
    return {sDateWitnessFrom => $1,
	    sDateWitnessTo => $2};
  } # d _1600
  elsif( $sAna =~ m!^d[\s-]*_(\d{4})$!i ) {
    my $sYear = ($1 eq '9145') ? '1945' : $1; # Zeer ad hoc, maar het kwam voor
    return {sDateWitnessFrom => $sYear,
	    sDateWitnessTo => $sYear};
  } # d16de e. -> 1501 - 1600
  elsif( $sAna =~ m!^d(\d{2})de\s+e.$!i ) {
    my $sYear = $1 - 1;
    return {sDateFrom => "${sYear}01",
	    sDateTo => "${1}00"};
  } # d1steh.16deeeuw -> 1501 1550
  elsif( $sAna =~ /^d1steh.?(\d{2})deeeuw$/i ) {
    my $sYear = $1 - 1;
    return {sDateFrom => "${sYear}01",
	    sDateTo => "${sYear}50"};
  } # deind18deeeuw -> 1791 - 1800
  elsif( $sAna =~ /^deind(\d{2})deeeuw$/i ) {
    my $sYear = $1 - 1;
    return {sDateFrom => "${sYear}91",
	    sDateTo => "${1}00"};
  } # 2deh16deeeuw -> 1551 - 1600
  elsif( $sAna =~ /^d2deh(\d{2})deeeuw$/i ) {
    my $sYear = $1 - 1;
    return {sDateFrom => "${sYear}51",
	    sDateTo => "${1}00"};
  } # dbegin16deeeuw -> 1501 - 1510
  elsif( $sAna =~ /^dbegin(\d{2})deeeuw$/i ) {
    my $sYear = $1 - 1;
    return {sDateFrom => "${sYear}01",
	    sDateTo => "${sYear}10"};
  } # dmidden16deeeuw -> 1541 - 1560
  elsif( $sAna =~ /^dmidden(\d{2})deeeuw$/i ) {
    my $sYear = $1 - 1;
    return {sDateFrom => "${sYear}41",
	    sDateTo => "${sYear}60"};
  } # d15deeeuw -> 1401 - 1500
  elsif( $sAna =~ /^d(\d{2})deeeuw$/i ) {
    my $sYear = $1 - 1;
    return {sDateFrom => "${sYear}01",
	    sDateTo => "${1}00"};
  }
  else { # Unkown..?!?
    return {sDateFrom => $sAna,
	    sDateTo => $sAna};
  }
}

1;
