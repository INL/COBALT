--
-- Table structure for table databases
--

DROP TABLE IF EXISTS lexicon_databases;
CREATE TABLE lexicon_databases (
  lexicon_database_id bigint unsigned NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  PRIMARY KEY (lexicon_database_id),
  UNIQUE KEY (name)
);

--
-- Table structure for table document_path
--

DROP TABLE IF EXISTS document_paths;
CREATE TABLE document_paths (
  document_path_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  path VARCHAR(255) NOT NULL,
  PRIMARY KEY (document_path_id),
  UNIQUE KEY pathKey (path)
) DEFAULT CHARSET=utf8 ;

--
-- Table structure for table document_index
--

DROP TABLE IF EXISTS document_indices;
CREATE TABLE document_indices (
  document_path_id BIGINT UNSIGNED NOT NULL,
  lexicon_database_id bigint unsigned NOT NULL,
  corpus_id bigint unsigned NOT NULL,
  UNIQUE KEY documentIndexKey (document_path_id, lexicon_database_id,corpus_id)
);

--
-- Table structure for table tokens
--

DROP TABLE IF EXISTS tokens;
CREATE TABLE tokens (
  lexicon_database_id bigint unsigned NOT NULL,
  wordform_id bigint unsigned NOT NULL,
  document_id bigint unsigned NOT NULL,
  onset bigint unsigned NOT NULL,
  offset bigint unsigned NOT NULL,
  UNIQUE KEY tokenKey (lexicon_database_id, wordform_id, document_id, onset, offset),
  INDEX wordformIdIndex(wordform_id),
  INDEX documentIdIndex(document_id),
  INDEX lexiconDatabaseIdIndex (lexicon_database_id),
  INDEX onsetIndex (onset),
  INDEX offsetIndex (offset)
);
