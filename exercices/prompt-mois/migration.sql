-- Un prompt pour dans un mois (niveau 2) : chaque stagiaire écrit un prompt
-- réutilisable en 7 blocs, l'analyse et l'améliore (version 1, version 2).
-- Une fiche par participant et par séance, en JSON.
--
-- Nécessite exercices/equipes/migration.sql (table participants). À jouer avec
-- un compte administrateur. Sans effet s'il est rejoué.
--
--   mysql --default-character-set=utf8mb4 -u root -p formation_ia < exercices/prompt-mois/migration.sql

CREATE TABLE IF NOT EXISTS `prompt_mois_fiches` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `formation`      VARCHAR(32)  NOT NULL,
  `seance`         DATE         NOT NULL,
  `participant_id` INT UNSIGNED NOT NULL,
  `donnees`        MEDIUMTEXT   NOT NULL,               -- JSON : tâche, version 1, analyse, version 2
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fiche` (`formation`, `seance`, `participant_id`),
  CONSTRAINT `fk_prompt_mois_participant` FOREIGN KEY (`participant_id`)
    REFERENCES `participants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
