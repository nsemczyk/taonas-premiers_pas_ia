-- Prompt Battle (niveau 2) : par manche, deux équipes s'affrontent, un
-- volontaire chacune, sur une tâche tirée au sort ; 5 minutes chrono, copies
-- projetées anonymement (A, B), vote du groupe, 20 points à l'équipe gagnante.
--
-- Nécessite exercices/equipes/migration.sql (table participants). À jouer avec un compte
-- administrateur. Sans effet s'il est rejoué.
--
--   mysql --default-character-set=utf8mb4 -u root -p formation_ia < exercices/battle/migration.sql

CREATE TABLE IF NOT EXISTS `battle_manches` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `formation`  VARCHAR(32)  NOT NULL,
  `seance`     DATE         NOT NULL,
  `numero`     SMALLINT UNSIGNED NOT NULL,           -- 1, 2, 3… dans la séance
  `tache`      VARCHAR(255) DEFAULT NULL,            -- tirée au sort
  `tirage_at`  INT UNSIGNED DEFAULT NULL,            -- horodatage Unix du tirage (animation au vidéoprojecteur)
  `equipe_a`   VARCHAR(16)  DEFAULT NULL,
  `equipe_b`   VARCHAR(16)  DEFAULT NULL,
  `joueur_a`   INT UNSIGNED DEFAULT NULL,            -- volontaire de l'équipe A
  `joueur_b`   INT UNSIGNED DEFAULT NULL,
  `etat`       VARCHAR(12)  NOT NULL DEFAULT 'preparation',  -- preparation | jeu | vote | revele
  `fin_jeu`    INT UNSIGNED DEFAULT NULL,            -- horodatage Unix de la fin du chrono
  `decompte`   TINYINT(1)   NOT NULL DEFAULT 0,      -- 1 : votes affichés en direct au vidéoprojecteur
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_manche` (`formation`, `seance`, `numero`),
  CONSTRAINT `fk_battle_joueur_a` FOREIGN KEY (`joueur_a`) REFERENCES `participants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_battle_joueur_b` FOREIGN KEY (`joueur_b`) REFERENCES `participants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La copie de chaque équipe : enregistrée au fil de la saisie, figée à la fin du chrono
CREATE TABLE IF NOT EXISTS `battle_copies` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `manche_id`      INT UNSIGNED NOT NULL,
  `equipe`         VARCHAR(16)  NOT NULL,
  `participant_id` INT UNSIGNED DEFAULT NULL,
  `prenom`         VARCHAR(40)  NOT NULL,
  `prompt`         TEXT         NOT NULL,
  `resultat`       TEXT         NOT NULL,
  `lettre`         CHAR(1)      DEFAULT NULL,        -- A, B… attribuée au hasard à l'ouverture du vote
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_copie` (`manche_id`, `equipe`),
  CONSTRAINT `fk_copie_manche` FOREIGN KEY (`manche_id`) REFERENCES `battle_manches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_copie_participant` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Un bulletin par votant et par manche ; il peut changer d'avis tant que le vote est ouvert
CREATE TABLE IF NOT EXISTS `battle_votes` (
  `manche_id`      INT UNSIGNED NOT NULL,
  `participant_id` INT UNSIGNED NOT NULL,
  `copie_id`       INT UNSIGNED NOT NULL,
  PRIMARY KEY (`manche_id`, `participant_id`),
  CONSTRAINT `fk_vote_manche` FOREIGN KEY (`manche_id`) REFERENCES `battle_manches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vote_participant` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vote_copie` FOREIGN KEY (`copie_id`) REFERENCES `battle_copies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
