-- Le bocal à secrets (niveau 2) : chaque stagiaire surligne, dans un faux mail,
-- les données qui ne doivent pas partir chez une IA, puis réécrit la demande
-- anonymisée. Une copie par participant et par séance ; les points vont dans
-- la table `points` (sources bocal-surlignage et bocal-prompt).
--
-- Nécessite migration-equipes.sql (table participants). À jouer avec un compte
-- administrateur. Sans effet s'il est rejoué.
--
--   mysql --default-character-set=utf8mb4 -u root -p formation_ia < migration-bocal.sql

CREATE TABLE IF NOT EXISTS `bocal_copies` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `formation`      VARCHAR(32)  NOT NULL,
  `seance`         DATE         NOT NULL,
  `participant_id` INT UNSIGNED NOT NULL,
  `surlignes`      TEXT         NOT NULL,              -- JSON : numéros des mots surlignés
  `prompt`         TEXT         NOT NULL,              -- la demande réécrite, anonymisée
  `rendu`          TINYINT(1)   NOT NULL DEFAULT 0,    -- 1 : copie rendue, plus modifiable
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_copie` (`formation`, `seance`, `participant_id`),
  CONSTRAINT `fk_bocal_participant` FOREIGN KEY (`participant_id`)
    REFERENCES `participants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
