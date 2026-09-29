-- Le prompt boule de neige (niveau 2) : chaque équipe construit un prompt en
-- 4 briques (tâche, contexte, destinataire, contraintes et format), puis un
-- 5e joueur le passe à l'IA et colle sa réponse. Une partie par équipe et par
-- séance ; une ligne par brique validée.
--
-- Nécessite migration-equipes.sql (table participants). À jouer avec un compte
-- administrateur. Sans effet s'il est rejoué.
--
--   mysql --default-character-set=utf8mb4 -u root -p formation_ia < migration-boule.sql

CREATE TABLE IF NOT EXISTS `boule_parties` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `formation`  VARCHAR(32)  NOT NULL,
  `seance`     DATE         NOT NULL,
  `equipe`     VARCHAR(16)  NOT NULL,                 -- clé d'équipe de formation.php
  `tache`      VARCHAR(255) NOT NULL,                 -- la situation affichée en haut de l'écran
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_partie` (`formation`, `seance`, `equipe`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `boule_briques` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `partie_id`      INT UNSIGNED NOT NULL,
  `etape`          TINYINT UNSIGNED NOT NULL,         -- 1 à 4 : briques du prompt ; 5 : réponse de l'IA
  `participant_id` INT UNSIGNED DEFAULT NULL,         -- auteur ; NULL s'il a été retiré de la séance depuis
  `prenom`         VARCHAR(40)  NOT NULL,             -- conservé pour le tableau, même si l'auteur est retiré
  `texte`          TEXT         NOT NULL,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_etape` (`partie_id`, `etape`),       -- deux validations simultanées : une seule passe
  CONSTRAINT `fk_brique_partie` FOREIGN KEY (`partie_id`)
    REFERENCES `boule_parties` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_brique_participant` FOREIGN KEY (`participant_id`)
    REFERENCES `participants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
