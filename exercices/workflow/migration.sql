-- Atelier workflow (niveau 2) : chaque stagiaire dessine un processus
-- (déclencheurs, étapes, conditions et flèches) sur un tableau blanc ; un
-- schéma par participant et par séance, en JSON.
--
-- Nécessite exercices/equipes/migration.sql (table participants). À jouer avec
-- un compte administrateur. Sans effet s'il est rejoué.
--
--   mysql --default-character-set=utf8mb4 -u root -p formation_ia < exercices/workflow/migration.sql

CREATE TABLE IF NOT EXISTS `workflow_schemas` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `formation`      VARCHAR(32)  NOT NULL,
  `seance`         DATE         NOT NULL,
  `participant_id` INT UNSIGNED NOT NULL,
  `donnees`        MEDIUMTEXT   NOT NULL,               -- JSON : titre, noeuds (formes), liens (flèches)
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_schema` (`formation`, `seance`, `participant_id`),
  CONSTRAINT `fk_workflow_participant` FOREIGN KEY (`participant_id`)
    REFERENCES `participants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
