-- 2026-10-13：診断結果の画面で読み直すため、推薦の文章と参考にしたシラバスを保存する列を足す。
-- この日より前に schema.sql で表を作った DB で1回だけ流す。
USE suisen;
ALTER TABLE diagnoses
  ADD COLUMN recommendation_text TEXT NULL AFTER senior_ids,
  ADD COLUMN sources JSON NULL AFTER career_text;
