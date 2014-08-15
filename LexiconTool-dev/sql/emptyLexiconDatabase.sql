-- MySQL dump 10.11
--
-- ------------------------------------------------------
-- Server version	5.0.45

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `alternate_modern_lemmata`
--

DROP TABLE IF EXISTS `alternate_modern_lemmata`;
CREATE TABLE `alternate_modern_lemmata` (
  `alternate_lemma_id` bigint unsigned NOT NULL auto_increment,
  `alternate_lemma` varchar(255) default NULL,
  `base_lemma_id` bigint unsigned default NULL,
  PRIMARY KEY  (`alternate_lemma_id`),
  KEY `FKFF2BEC5A829D7F2` (`base_lemma_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `analyzed_wordforms`
--

DROP TABLE IF EXISTS `analyzed_wordforms`;
CREATE TABLE `analyzed_wordforms` (
  `analyzed_wordform_id` bigint unsigned NOT NULL auto_increment,
  `part_of_speech` varchar(255) NOT NULL,
  `lemma_id` bigint unsigned NOT NULL,
  `wordform_id` bigint unsigned NOT NULL,
  `multiple_lemmata_analysis_id` BIGINT UNSIGNED NOT NULL,
  `derivation_id` BIGINT UNSIGNED NOT NULL,
  verified_by bigint unsigned DEFAULT NULL,
  verification_date datetime DEFAULT NULL,
  PRIMARY KEY  (`analyzed_wordform_id`),
  UNIQUE KEY `awfKey` (`part_of_speech`,`lemma_id`,`wordform_id`, multiple_lemmata_analysis_id, derivation_id),
  KEY lemmaIdKey (`lemma_id`),
  KEY wordformIdKey (wordform_id),
  KEY derivationIdKey (derivation_id),
  INDEX multipleLemmataAnalysisId (multiple_lemmata_analysis_id),
  INDEX awfId (analyzed_wordform_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `multiple_lemmata_analyses`
--

DROP TABLE IF EXISTS `multiple_lemmata_analyses`;
CREATE TABLE `multiple_lemmata_analyses` (
  `multiple_lemmata_analysis_id` bigint unsigned NOT NULL,
  `multiple_lemmata_analysis_part_id` bigint unsigned NOT NULL,
  `part_number` bigint unsigned NOT NULL,
  `nr_of_parts` tinyint unsigned NOT NULL,
  UNIQUE KEY `mlaKey` (`multiple_lemmata_analysis_id`, `multiple_lemmata_analysis_part_id`, `part_number`, `nr_of_parts`),
  INDEX multipleLemmataAnalysisIdIndex (multiple_lemmata_analysis_id),
  INDEX multipleLemmataAnalysisPartIdIndex (multiple_lemmata_analysis_part_id),
  INDEX multipleLemmataAnalysisPartNumberIndex (part_number)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `multiple_analyses_parts`
--

DROP TABLE IF EXISTS `multiple_lemmata_analysis_parts`;
CREATE TABLE `multiple_lemmata_analysis_parts` (
  `multiple_lemmata_analysis_part_id` bigint unsigned NOT NULL auto_increment,
  `part_of_speech` varchar(255) NOT NULL default '',
  `lemma_id` bigint unsigned NOT NULL,
  `derivation_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`multiple_lemmata_analysis_part_id`),
  UNIQUE KEY `mlapKey` (`part_of_speech`, `lemma_id`, derivation_id),
  INDEX lemmaIdIndex (lemma_id),
  INDEX multipleLemmataAnalysisPartIdIndex (multiple_lemmata_analysis_part_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `wordform_groups`
--

DROP TABLE IF EXISTS `wordform_groups`;
CREATE TABLE `wordform_groups` (
  `wordform_group_id` bigint unsigned NOT NULL,
  `document_id` bigint unsigned NOT NULL,
  `onset` bigint unsigned NOT NULL,
  `offset` bigint unsigned NOT NULL,
  UNIQUE KEY `wordFormGroupKey` (`wordform_group_id`, document_id, onset, offset),
  INDEX documentIdIndex (document_id),
  INDEX onsetIndex (onset),
  INDEX offsetIndex (offset),
  INDEX wordformGroupIdIndex (wordform_group_id)
) ENGINE=MyISAM;

--
-- Table structure for table `conversion_rules`
--

DROP TABLE IF EXISTS `conversion_rules`;
CREATE TABLE `conversion_rules` (
  `rule_id` bigint unsigned NOT NULL auto_increment,
  `main_pos` varchar(255) default NULL,
  `sub_pos` varchar(255) default NULL,
  `transcategorization_id` bigint unsigned default NULL,
  PRIMARY KEY  (`rule_id`),
  KEY `FK8164488E638479FE` (`transcategorization_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `corpora`
--

DROP TABLE IF EXISTS `corpora`;
CREATE TABLE `corpora` (
  `corpus_id` bigint unsigned NOT NULL auto_increment,
  `name` varchar(255) default NULL,
  PRIMARY KEY  (`corpus_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `corpusId_x_documentId`
--

DROP TABLE IF EXISTS `corpusId_x_documentId`;
CREATE TABLE `corpusId_x_documentId` (
  `corpus_id` bigint unsigned NOT NULL,
  `document_id` bigint unsigned NOT NULL,
  PRIMARY KEY  (`corpus_id`,`document_id`),
  INDEX documentIdIndex (document_id),
  INDEX corpusIdIndex (corpus_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `derivations`
--
-- NOTE that there is no (analyzed_wordform_id, normalized_form) KEY on
-- purpose, since more than one of these can exist
--

DROP TABLE IF EXISTS `derivations`;
CREATE TABLE `derivations` (
  `derivation_id` bigint unsigned NOT NULL auto_increment,
  `normalized_form` varchar(255) NOT NULL COLLATE utf8_bin default '',
  `pattern_application_id` bigint unsigned NOT NULL,
  PRIMARY KEY  (`derivation_id`),
  UNIQUE KEY `derivationKey` (`normalized_form`, pattern_application_id)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
CREATE TABLE `documents` (
  `document_id` bigint unsigned NOT NULL auto_increment,
  `persistent_id` varchar(255) default NULL,
  `word_count` bigint unsigned default NULL,
  `encoding` bigint unsigned default NULL,
  `title` varchar(255) default NULL,
   image_location VARCHAR(255) DEFAULT NULL,
  `year_from` bigint unsigned default NULL,
  `year_to` bigint unsigned default NULL,
  `pub_year` bigint unsigned default NULL,
  `author` varchar(255) default NULL,
  `editor` varchar(255) default NULL,
  `publisher` varchar(255) default NULL,
  `publishing_location` varchar(255) default NULL,
  `text_type` varchar(255) default NULL,
  `region` varchar(255) default NULL,
  `language` varchar(255) default NULL,
  `other_languages` varchar(255) default NULL,
  `spelling` varchar(255) default NULL,
  `parent_document` bigint unsigned default NULL,
  PRIMARY KEY  (`document_id`),
  UNIQUE KEY (title),
  INDEX docPersistentIdIndex (persistent_id),
  INDEX documentIdIndex (document_id),
  KEY `FK383D52B8B5820461` (`parent_document`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `dont_show`
--

DROP TABLE IF EXISTS `dont_show`;
CREATE TABLE `dont_show` (
  `wordform_id` bigint unsigned NOT NULL,
  `document_id` bigint unsigned NOT NULL default '0',
  `corpus_id` bigint unsigned NOT NULL default '0',
  `at_all` tinyint(3) unsigned NOT NULL default '0',
  `user_id` bigint unsigned NOT NULL,
  `date` datetime NOT NULL,
  PRIMARY KEY  (`wordform_id`,`document_id`,`corpus_id`,`at_all`),
  INDEX (wordform_id),
  INDEX (document_id),
  INDEX (corpus_id),
  INDEX (at_all)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `inflection_classes`
--

DROP TABLE IF EXISTS `inflection_classes`;
CREATE TABLE `inflection_classes` (
  `inflection_class_id` bigint unsigned NOT NULL auto_increment,
  `inflection_class_name` varchar(255) default NULL,
  PRIMARY KEY  (`inflection_class_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `lemma_feature_assignments`
--

DROP TABLE IF EXISTS `lemma_feature_assignments`;
CREATE TABLE `lemma_feature_assignments` (
  `assignment_id` bigint unsigned NOT NULL auto_increment,
  `feature_id` bigint unsigned default NULL,
  `value_id` bigint unsigned default NULL,
  `lemma_id` bigint unsigned default NULL,
  PRIMARY KEY  (`assignment_id`),
  KEY `FK12E58F6686969244` (`lemma_id`),
  KEY `FK12E58F66D8826568` (`feature_id`),
  KEY `FK12E58F66F305EC1E` (`assignment_id`),
  KEY `FK12E58F66E09B4416` (`value_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `lemma_feature_values`
--

DROP TABLE IF EXISTS `lemma_feature_values`;
CREATE TABLE `lemma_feature_values` (
  `lemma_feature_value_id` bigint unsigned NOT NULL auto_increment,
  `lemma_feature_value` varchar(255) default NULL,
  PRIMARY KEY  (`lemma_feature_value_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `lemma_features`
--

DROP TABLE IF EXISTS `lemma_features`;
CREATE TABLE `lemma_features` (
  `lemma_feature_id` bigint unsigned NOT NULL auto_increment,
  `lemma_feature_name` varchar(255) default NULL,
  PRIMARY KEY  (`lemma_feature_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `lemma_inflection_class`
--

DROP TABLE IF EXISTS `lemma_inflection_class`;
CREATE TABLE `lemma_inflection_class` (
  `lemma_inflection_class_id` bigint unsigned NOT NULL auto_increment,
  `lemma_id` bigint unsigned default NULL,
  `inflection_class_id` bigint unsigned default NULL,
  PRIMARY KEY  (`lemma_inflection_class_id`),
  KEY `FK9F7A688DBE482589` (`inflection_class_id`),
  KEY `FK9F7A688D86969244` (`lemma_id`),
  KEY `FK9F7A688DB11E3DD6` (`lemma_inflection_class_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `lemmata`
--

DROP TABLE IF EXISTS `lemmata`;
CREATE TABLE `lemmata` (
  `lemma_id` bigint unsigned NOT NULL auto_increment,
  `modern_lemma` varchar(255) NOT NULL COLLATE utf8_bin,
  `gloss` varchar(255) NOT NULL default '',
  `persistent_id` varchar(255) default NULL,
  `lemma_part_of_speech` varchar(255) NOT NULL,
  `ne_label` varchar(255) NOT NULL default '',
  `portmanteau_lemma_id` bigint unsigned default NULL,
  `language_id` tinyint unsigned NOT NULL default 0,
  PRIMARY KEY  (`lemma_id`),
  UNIQUE KEY `lemmaKeyTuple` (`modern_lemma`(100),`gloss`(100),`lemma_part_of_speech`(100),`ne_label`(10), language_id),
  KEY `FK3AD7F1586969244` (`lemma_id`),
  KEY `FK3AD7F1576D23645` (`portmanteau_lemma_id`),
  INDEX persistentIdIndex (persistent_id),
  INDEX glossIndex (gloss),
  INDEX modernLemmaIndex (modern_lemma),
  INDEX lemmaPartOfSpeechIndex (lemma_part_of_speech)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

--
-- Table structure for table 'languages'
--

DROP TABLE IF EXISTS `languages`;
CREATE TABLE `languages` (
  `language_id` tinyint unsigned NOT NULL auto_increment,
  `language` varchar(255) NOT NULL,
  PRIMARY KEY  (`language_id`),
  UNIQUE KEY `languageKey` (`language`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

--
-- Table structure for table `lexica`
--

DROP TABLE IF EXISTS `lexica`;
CREATE TABLE `lexica` (
  `lexicon_id` bigint unsigned NOT NULL auto_increment,
  `lexicon_name` varchar(255) default NULL,
  PRIMARY KEY  (`lexicon_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `lexical_source_lemma`
--

DROP TABLE IF EXISTS `lexical_source_lemma`;
CREATE TABLE `lexical_source_lemma` (
  `lemma_source_id` bigint unsigned NOT NULL auto_increment,
  `label` varchar(255) default NULL,
  `lemma_id` bigint unsigned default NULL,
  `foreign_id` varchar(255) default NULL,
  `lexicon_id` bigint unsigned default NULL,
  PRIMARY KEY  (`lemma_source_id`),
  KEY `FK8D721C7F86969244` (`lemma_id`),
  KEY `FK8D721C7F103510C0` (`lexicon_id`),
  KEY `FK8D721C7F495FF5D5` (`lemma_source_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `lexical_source_wordforms`
--

DROP TABLE IF EXISTS `lexical_source_wordform`;
CREATE TABLE `lexical_source_wordform` (
  `wordform_source_id` bigint unsigned NOT NULL auto_increment,
  `foreign_id` varchar(255) default NULL,
  `label` varchar(255) default NULL,
  `wordform_id` bigint unsigned default NULL,
  `lexicon_id` bigint unsigned default NULL,
  PRIMARY KEY  (`wordform_source_id`),
  KEY `FKC1FAB997103510C0` (`lexicon_id`),
  KEY `FKC1FAB997196473E8` (`wordform_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `morphological_analyses`
--

DROP TABLE IF EXISTS `morphological_analyses`;
CREATE TABLE `morphological_analyses` (
  `morphological_analysis_id` bigint unsigned NOT NULL auto_increment,
  `arity` bigint unsigned default NULL,
  `analyzed_lemma_id` bigint unsigned default NULL,
  `morphological_operation_id` bigint unsigned default NULL,
  PRIMARY KEY  (`morphological_analysis_id`),
  KEY `FKC21C843D461BA2A9` (`morphological_analysis_id`),
  KEY `FKC21C843D9D3683CB` (`morphological_operation_id`),
  KEY `FKC21C843DF03C220B` (`analyzed_lemma_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `morphological_operations`
--

DROP TABLE IF EXISTS `morphological_operations`;
CREATE TABLE `morphological_operations` (
  `morphological_operation_id` bigint unsigned NOT NULL auto_increment,
  `description` varchar(255) default NULL,
  `resulting_part_of_speech` varchar(255) default NULL,
  PRIMARY KEY  (`morphological_operation_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `multiword_analyses`
--

DROP TABLE IF EXISTS `multiword_analyses`;
CREATE TABLE `multiword_analyses` (
  `multiword_analysis_id` bigint unsigned NOT NULL auto_increment,
  `arity` bigint unsigned default NULL,
  `analyzed_lemma_id` bigint unsigned default NULL,
  `multiword_operation_id` bigint unsigned default NULL,
  PRIMARY KEY  (`multiword_analysis_id`),
  KEY `FKCC79175C52754D89` (`multiword_operation_id`),
  KEY `FKCC79175CF11DA92B` (`multiword_analysis_id`),
  KEY `FKCC79175CF03C220B` (`analyzed_lemma_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `multiword_operations`
--

DROP TABLE IF EXISTS `multiword_operations`;
CREATE TABLE `multiword_operations` (
  `multiword_operation_id` bigint unsigned NOT NULL auto_increment,
  `description` varchar(255) default NULL,
  `resulting_pos` varchar(255) default NULL,
  PRIMARY KEY  (`multiword_operation_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `paradigm_positions`
--

DROP TABLE IF EXISTS `paradigm_positions`;
CREATE TABLE `paradigm_positions` (
  `paradigm_position_id` bigint unsigned NOT NULL auto_increment,
  `paradigm_position_name` varchar(255) default NULL,
  `paradigm_position` bigint unsigned default NULL,
  `paradigm_id` bigint unsigned default NULL,
  `transformset_id` bigint unsigned default NULL,
  PRIMARY KEY  (`paradigm_position_id`),
  KEY `FKA883B53638D68C10` (`paradigm_id`),
  KEY `FKA883B536DA3A0D10` (`transformset_id`),
  KEY `FKA883B536482CC847` (`paradigm_position_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `paradigms`
--

DROP TABLE IF EXISTS `paradigms`;
CREATE TABLE `paradigms` (
  `paradigm_id` bigint unsigned NOT NULL auto_increment,
  `paradigm_name` varchar(255) default NULL,
  PRIMARY KEY  (`paradigm_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `part_morphological_analysis`
--

DROP TABLE IF EXISTS `part_morphological_analysis`;
CREATE TABLE `part_morphological_analysis` (
  `part_morphological_analysis_id` bigint unsigned NOT NULL auto_increment,
  `part_number` bigint unsigned default NULL,
  `part_lemma_id` bigint unsigned default NULL,
  `morphological_analysis_id` bigint unsigned default NULL,
  PRIMARY KEY  (`part_morphological_analysis_id`),
  KEY `FK1F8B3E85461BA2A9` (`morphological_analysis_id`),
  KEY `FK1F8B3E85CEBD2110` (`part_lemma_id`),
  KEY `FK1F8B3E85330CCC74` (`part_morphological_analysis_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `part_multiword_analysis`
--

DROP TABLE IF EXISTS `part_multiword_analysis`;
CREATE TABLE `part_multiword_analysis` (
  `part_multiword_analysis_id` bigint unsigned NOT NULL auto_increment,
  `part_number` bigint unsigned default NULL,
  `part_lemma_id` bigint unsigned default NULL,
  `multiword_analysis_id` bigint unsigned default NULL,
  PRIMARY KEY  (`part_multiword_analysis_id`),
  KEY `FK1E8497A4CEBD2110` (`part_lemma_id`),
  KEY `FK1E8497A4EA2F7576` (`part_multiword_analysis_id`),
  KEY `FK1E8497A4F11DA92B` (`multiword_analysis_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `pattern_applications`
--

DROP TABLE IF EXISTS `pattern_applications`;
CREATE TABLE `pattern_applications` (
  `pattern_application_id` bigint unsigned NOT NULL,
  `position` bigint unsigned default NULL,
  `pattern_id` bigint unsigned default NULL,
  number_of_patterns BIGINT UNSIGNED NOT NULL,
  UNIQUE KEY patternApplicationKey (pattern_application_id, position, pattern_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `patterns`
--

DROP TABLE IF EXISTS `patterns`;
CREATE TABLE `patterns` (
  `pattern_id` bigint unsigned NOT NULL auto_increment,
  `left_hand_side` varchar(64) default NULL,
  `right_hand_side` varchar(64) default NULL,
  PRIMARY KEY  (`pattern_id`),
  UNIQUE KEY `lhrhKey` (left_hand_side, right_hand_side)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `stem_types`
--

DROP TABLE IF EXISTS `stem_types`;
CREATE TABLE `stem_types` (
  `stem_type_id` bigint unsigned NOT NULL auto_increment,
  `stem_type_name` varchar(255) default NULL,
  PRIMARY KEY  (`stem_type_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `stems`
--

DROP TABLE IF EXISTS `stems`;
CREATE TABLE `stems` (
  `stem_id` bigint unsigned NOT NULL auto_increment,
  `stem_form` varchar(255) default NULL,
  `lemma_id` bigint unsigned default NULL,
  `stem_type_id` bigint unsigned default NULL,
  PRIMARY KEY  (`stem_id`),
  KEY `FK68AD2CA86969244` (`lemma_id`),
  KEY `FK68AD2CA6CBB2B4` (`stem_type_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `text_attestation_verifications`
--

DROP TABLE IF EXISTS `text_attestation_verifications`;
CREATE TABLE `text_attestation_verifications` (
  `document_id` bigint unsigned NOT NULL,
  `wordform_id` bigint unsigned NOT NULL,
  `verification_date` datetime NOT NULL,
  `verified_by` bigint unsigned NOT NULL,
  PRIMARY KEY  (`document_id`,`wordform_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `text_attestations`
--

DROP TABLE IF EXISTS `text_attestations`;
CREATE TABLE `text_attestations` (
  `attestation_id` bigint unsigned NOT NULL auto_increment,
  `frequency` bigint unsigned default NULL,
  `analyzed_wordform_id` bigint unsigned NOT NULL,
  `document_id` bigint unsigned NOT NULL,
  PRIMARY KEY  (`attestation_id`),
  UNIQUE KEY `tlaKey` (`analyzed_wordform_id`,`document_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `token_attestation_verifications`
--

DROP TABLE IF EXISTS `token_attestation_verifications`;
CREATE TABLE `token_attestation_verifications` (
  `document_id` bigint unsigned NOT NULL,
  `wordform_id` bigint unsigned NOT NULL,
  `start_pos` bigint unsigned NOT NULL,
  `end_pos` bigint unsigned NOT NULL,
  `verification_date` datetime NOT NULL,
  `verified_by` bigint unsigned NOT NULL,
  PRIMARY KEY  (`document_id`,`wordform_id`,`start_pos`),
  INDEX documentIdIndex (document_id),
  INDEX tokenAttWordformIdIndex (wordform_id),
  INDEX startPosIndex (start_pos),
  INDEX endPosIndex (end_pos),
  INDEX verifiedByIndex (verified_by)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `token_attestations`
--

DROP TABLE IF EXISTS `token_attestations`;
CREATE TABLE `token_attestations` (
  `attestation_id` bigint unsigned NOT NULL auto_increment,
  `token_id` bigint unsigned default 0,
  `quote` TEXT COLLATE utf8_bin NOT NULL,
  `analyzed_wordform_id` bigint unsigned NOT NULL,
  `derivation_id` bigint NOT NULL,
  `document_id` bigint unsigned NOT NULL,
  `start_pos` bigint unsigned NOT NULL,
  `end_pos` bigint unsigned NOT NULL,
  PRIMARY KEY  (`attestation_id`),
  UNIQUE KEY `tlaKey` (`analyzed_wordform_id`, derivation_id, `document_id`,`start_pos`,`end_pos`, token_id),
  INDEX analyzedWordformIdIndex (analyzed_wordform_id),
  INDEX documentIdIndex (document_id),
  INDEX startPosIndex (start_pos),
  INDEX endPosIndex (end_pos)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `transcategorization_types`
--

DROP TABLE IF EXISTS `transcategorization_types`;
CREATE TABLE `transcategorization_types` (
  `transcategorizationtype_id` bigint unsigned NOT NULL auto_increment,
  `description` varchar(255) default NULL,
  `main_pos` varchar(255) default NULL,
  `sub_pos` varchar(255) default NULL,
  PRIMARY KEY  (`transcategorizationtype_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `transcategorizations`
--

DROP TABLE IF EXISTS `transcategorizations`;
CREATE TABLE `transcategorizations` (
  `transcategorization_id` bigint unsigned NOT NULL auto_increment,
  `mainlemma_id` bigint unsigned default NULL,
  `sublemma_id` bigint unsigned default NULL,
  `transcategorizationtype_id` bigint unsigned default NULL,
  PRIMARY KEY  (`transcategorization_id`),
  KEY `FKCCDB44C27D94DC24` (`transcategorization_id`),
  KEY `FKCCDB44C2D1161084` (`sublemma_id`),
  KEY `FKCCDB44C224F30904` (`transcategorizationtype_id`),
  KEY `FKCCDB44C21F68CEFD` (`mainlemma_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `transformsets`
--

DROP TABLE IF EXISTS `transformsets`;
CREATE TABLE `transformsets` (
  `transformset_id` bigint unsigned NOT NULL auto_increment,
  `inflection_process` varchar(255) default NULL,
  `formal_tag` varchar(255) default NULL,
  `stem_type_id` bigint unsigned default NULL,
  PRIMARY KEY  (`transformset_id`),
  KEY `FK99B8A5BD6CBB2B4` (`stem_type_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `type_frequencies`
--

DROP TABLE IF EXISTS `type_frequencies`;
CREATE TABLE `type_frequencies` (
  `type_frequency_id` bigint unsigned NOT NULL auto_increment,
  `frequency` bigint unsigned NOT NULL,
  `wordform_id` bigint unsigned NOT NULL,
  `document_id` bigint unsigned NOT NULL,
  PRIMARY KEY  (`type_frequency_id`),
  UNIQUE KEY `tfKey` (`wordform_id`,`document_id`),
  INDEX typeFreqWordformIdIndex (wordform_id),
  INDEX documentIdIndex (document_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` bigint unsigned NOT NULL auto_increment,
  `name` varchar(255) default NULL,
  PRIMARY KEY  (`user_id`),
  UNIQUE KEY `nameIndex` (`name`(255))
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `wordform_transform_instance`
--

DROP TABLE IF EXISTS `wordform_transform_instance`;
CREATE TABLE `wordform_transform_instance` (
  `transform_instance_id` bigint unsigned NOT NULL auto_increment,
  `transformset_id` bigint unsigned default NULL,
  `stem_id` bigint unsigned default NULL,
  `analyzed_wordform_id` bigint unsigned default NULL,
  PRIMARY KEY  (`transform_instance_id`),
  KEY `FK90C83D19CEED5B5D` (`transform_instance_id`),
  KEY `FK90C83D19DA3A0D10` (`transformset_id`),
  KEY `FK90C83D1952FAFAC1` (`analyzed_wordform_id`),
  KEY `FK90C83D19B918B650` (`stem_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `wordforms`
--

/* NOTE that the index is on the full wordform. This does not necessarily make
things very fast of course, but it avoids any key violation which will
otherwise, due to Murphy's Law, occur sooner or later.
*/

DROP TABLE IF EXISTS `wordforms`;
CREATE TABLE `wordforms` (
  `wordform_id` bigint unsigned NOT NULL auto_increment,
  `wordform` varchar(255) COLLATE utf8_bin NOT NULL,
  `wordform_lowercase` varchar(255) COLLATE utf8_bin,
  lastviewed_by BIGINT DEFAULT NULL,
  lastview_date datetime DEFAULT NULL,
  `has_analysis` bit(1) DEFAULT NULL,
  PRIMARY KEY  (`wordform_id`),
  UNIQUE KEY `fullWordformIndex` (`wordform`(255)),
  INDEX lowerCaseIndex (wordform_lowercase(255)),
  INDEX wordformIdIndex (wordform_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/* 	NOTE: the tables below will be used for the named entities */

--
-- Table structure for 'ne_part_information'
--

DROP TABLE IF EXISTS `ne_part_information`;
CREATE TABLE `ne_part_information` (
  `lemma_id` bigint unsigned NOT NULL,
  `ne_part_type_id` bigint NOT NULL,
   KEY (`lemma_id`),
   KEY (`ne_part_type_id`),
   UNIQUE KEY (`lemma_id`, `ne_part_type_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `ne_part_types`;
CREATE TABLE `ne_part_types` (
  `ne_part_type_id` bigint unsigned PRIMARY KEY auto_increment,
  `ne_part_type_name` varchar(255) NOT NULL,
   KEY (`ne_part_type_name`)
 ) ENGINE=MyISAM DEFAULT CHARSET=utf8;
 
--
-- Table structure for table `ne_variant_relation_types`
--

DROP TABLE IF EXISTS `ne_variant_relation_types`;

CREATE TABLE `ne_variant_relation_types` (
  `ne_variant_relation_type_id` int(32) NOT NULL auto_increment,
  `ne_variant_relation_name` varchar(255) default NULL,
  `ne_variant_relation_desciption` text,
  PRIMARY KEY  (`ne_variant_relation_type_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ne_variant_relations`
--

DROP TABLE IF EXISTS `ne_variant_relations`;
 CREATE TABLE `ne_variant_relations` (
  `first_lemma_id` int(32) default NULL,
  `second_lemma_id` int(32) default NULL,
  `ne_variant_relation_type_id` int(32) default NULL,
  `verified_by` bigint(20) unsigned default NULL,
  `verification_date` datetime default NULL,
  UNIQUE KEY `neVariantRelationKey` (`first_lemma_id`,`second_lemma_id`),
  KEY `fliIndex` (`first_lemma_id`),
  KEY `sliIndex` (`second_lemma_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `group_attestations`
--
DROP TABLE IF EXISTS `group_attestations`;

CREATE TABLE `group_attestations` (
  `group_attestation_id` bigint(20) unsigned NOT NULL auto_increment,
  `token_id` bigint(20) unsigned default NULL,
  `quote` text,
  `analyzed_wordform_id` bigint(20) unsigned NOT NULL,
  `derivation_id` bigint(20) unsigned NOT NULL,
  `wordform_group_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY  (`group_attestation_id`),
  UNIQUE KEY `groupAttestationKey` (`analyzed_wordform_id`,`derivation_id`,`wordform_group_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `attestation_groups`
--

DROP TABLE IF EXISTS `attestation_groups`;
CREATE TABLE `attestation_groups` (
  `attestation_group_id` bigint unsigned NOT NULL,
  `attestation_id` bigint unsigned NOT NULL,
  UNIQUE KEY `wordFormGroupKey` (`attestation_group_id`, attestation_id)
) ENGINE=MyISAM;

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2009-09-22 14:08:30
