-- Points de la séance : une ligne par attribution (positive ou négative), à une
-- équipe OU à un joueur. Points d'équipe et points individuels sont
-- indépendants : les uns ne s'additionnent pas aux autres.
--
-- `source` repère les points donnés par un exercice (ex. 'boule' : les 10 pts
-- du prompt boule de neige), pour ne pas les donner deux fois et pouvoir les
-- reprendre ; NULL pour les points donnés à la main.
--
-- Nécessite migration-equipes.sql (table participants). À jouer avec un compte
-- administrateur. Sans effet s'il est rejoué.
--
--   mysql --default-character-set=utf8mb4 -u root -p formation_ia < migration-points.sql

CREATE TABLE IF NOT EXISTS `points` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `formation`      VARCHAR(32)  NOT NULL,
  `seance`         DATE         NOT NULL,
  `equipe`         VARCHAR(16)  DEFAULT NULL,      -- points d'équipe : clé d'équipe de formation.php
  `participant_id` INT UNSIGNED DEFAULT NULL,      -- points individuels
  `valeur`         INT          NOT NULL,          -- négatif pour retirer
  `motif`          VARCHAR(120) DEFAULT NULL,
  `source`         VARCHAR(32)  DEFAULT NULL,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_seance` (`formation`, `seance`),
  CONSTRAINT `fk_points_participant` FOREIGN KEY (`participant_id`)
    REFERENCES `participants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
