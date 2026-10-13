-- SUISEN の表を作る。phpMyAdmin の「SQL」タブに貼り付けて実行するか、mysql コマンドで流す。
-- 13項目の値は JSON の列に入れる（項目は policy/features.php で変わりうるため）。

CREATE DATABASE IF NOT EXISTS suisen CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE suisen;

CREATE TABLE IF NOT EXISTS users (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email      VARCHAR(255) NOT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_codes (
  id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  email           VARCHAR(255)     NOT NULL,
  code_hash       VARCHAR(255)     NOT NULL,
  failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  expires_at      DATETIME         NOT NULL,
  used_at         DATETIME         NULL,
  created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_login_codes_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS courses (
  id            TINYINT UNSIGNED NOT NULL,
  code          VARCHAR(30)      NOT NULL,
  name          VARCHAR(50)      NOT NULL,
  features      JSON             NOT NULL,
  display_order TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_courses_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seniors (
  id           INT UNSIGNED                         NOT NULL AUTO_INCREMENT,
  course_id    TINYINT UNSIGNED                     NOT NULL,
  features     JSON                                 NOT NULL,
  career_type  ENUM('employment', 'further_study')  NOT NULL,
  destination  VARCHAR(100)                         NOT NULL,
  is_test_data BOOLEAN                              NOT NULL DEFAULT TRUE,
  PRIMARY KEY (id),
  CONSTRAINT fk_seniors_course FOREIGN KEY (course_id) REFERENCES courses (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conversations (
  id         INT UNSIGNED                   NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED                   NOT NULL,
  status     ENUM('active', 'diagnosed')    NOT NULL DEFAULT 'active',
  created_at DATETIME                       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_conversations_user (user_id),
  CONSTRAINT fk_conversations_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
  id              INT UNSIGNED                  NOT NULL AUTO_INCREMENT,
  conversation_id INT UNSIGNED                  NOT NULL,
  role            ENUM('user', 'assistant')     NOT NULL,
  content         TEXT                          NOT NULL,
  topic           VARCHAR(50)                   NULL,     -- AI の返事で聞かせた項目（同じ項目を何度も聞かせないため）
  created_at      DATETIME                      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_messages_conversation (conversation_id),
  CONSTRAINT fk_messages_conversation FOREIGN KEY (conversation_id) REFERENCES conversations (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS diagnoses (
  id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  conversation_id INT UNSIGNED     NOT NULL,
  user_id         INT UNSIGNED     NOT NULL,
  features        JSON             NOT NULL,
  course_id       TINYINT UNSIGNED NOT NULL,
  course_scores   JSON             NOT NULL,
  senior_ids      JSON             NOT NULL,
  recommendation_text TEXT         NULL,     -- 推薦の文章（結果の画面で読み直すため。2026-10-13 追加）
  career_text     TEXT             NOT NULL,
  sources         JSON             NULL,     -- 参考にしたシラバスの見出し（2026-10-13 追加）
  created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_diagnoses_conversation (conversation_id),
  KEY idx_diagnoses_user (user_id),
  CONSTRAINT fk_diagnoses_conversation FOREIGN KEY (conversation_id) REFERENCES conversations (id),
  CONSTRAINT fk_diagnoses_user FOREIGN KEY (user_id) REFERENCES users (id),
  CONSTRAINT fk_diagnoses_course FOREIGN KEY (course_id) REFERENCES courses (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
