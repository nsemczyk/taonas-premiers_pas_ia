-- La fiche de poste de votre assistant (niveau 2) : chaque stagiaire décrit
-- l'assistant IA qu'il veut, rubrique par rubrique. Une fiche par participant
-- et par séance, modifiable toute la journée. Pas de dépôt de fichier : les
-- documents de référence sont seulement listés.
--
-- Nécessite exercices/equipes/migration.sql (table participants). À jouer avec
-- un compte administrateur. Sans effet s'il est rejoué.
--
--   mysql --default-character-set=utf8mb4 -u root -p formation_ia < exercices/assistant/migration.sql

CREATE TABLE IF NOT EXISTS `assistant_fiches` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `formation`      VARCHAR(32)  NOT NULL,
  `seance`         DATE         NOT NULL,
  `participant_id` INT UNSIGNED NOT NULL,
  `mission`        TEXT         NOT NULL,
  `contexte`       TEXT         NOT NULL,
  `documents`      TEXT         NOT NULL,             -- un document par ligne, sans le fichier
  `regles`         TEXT         NOT NULL,
  `interdits`      TEXT         NOT NULL,
  `format`         TEXT         NOT NULL,
  `questions`      TEXT         NOT NULL,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fiche` (`formation`, `seance`, `participant_id`),
  CONSTRAINT `fk_assistant_participant` FOREIGN KEY (`participant_id`)
    REFERENCES `participants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
