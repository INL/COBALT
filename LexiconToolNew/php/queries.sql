--
-- Klad file om queries in te editten...
--

DELETE FROM lexicon_databases WHERE lexicon_database_id = 12;
DELETE FROM document_indices WHERE lexicon_database_id = 12;
DELETE FROM tokens WHERE lexicon_database_id = 12;

SELECT document_indices.document_path_id
  FROM document_indices 
  LEFT JOIN document_paths
    ON (document_indices.document_path_id = document_paths.document_path_id)
 WHERE document_paths.document_path_id IS NULL