ALTER TABLE analyzed_wordforms DROP INDEX multipleLemmataAnalysisId;
ALTER TABLE corpusId_x_documentId DROP INDEX documentIdIndex;
ALTER TABLE corpusId_x_documentId DROP INDEX corpusIdIndex;
ALTER TABLE token_attestations DROP INDEX analyzedWordformIdIndex;
ALTER TABLE token_attestations DROP INDEX documentIdIndex;
ALTER TABLE token_attestations DROP INDEX startPosIndex;
ALTER TABLE token_attestations DROP INDEX endPosIndex;
ALTER TABLE type_frequencies DROP INDEX wordformIdIndex;
ALTER TABLE type_frequencies DROP INDEX documentIdIndex;

