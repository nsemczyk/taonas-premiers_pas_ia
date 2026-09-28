-- Participants de la séance et équipes.
--
-- Un participant se déclare en arrivant (son prénom, sur son téléphone) ; le
-- téléphone garde un jeton en cookie qui le relie à cette ligne. Le formateur
-- le place ensuite dans une équipe depuis equipes.php. Les équipes elles-mêmes
-- ne sont pas en base : leurs noms sont fixés dans formations/<slug>/formation.php.
--
-- Une séance = une formation + une journée (heure locale), comme le reste du site.
--
-- À jouer une seule fois, avec un compte administrateur (l'utilisateur
-- applicatif n'a que SELECT/INSERT/UPDATE/DELETE). Sans effet s'il est rejoué.
--
--   mysql --default-character-set=utf8mb4 -u root -p formation_ia < migration-equipes.sql

CREATE TABLE IF NOT EXISTS `participants` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `formation`  VARCHAR(32)  NOT NULL,
  `seance`     DATE         NOT NULL,                  -- journée locale
  `prenom`     VARCHAR(40)  NOT NULL,
  `jeton`      CHAR(32)     NOT NULL,                  -- cookie du téléphone, 16 octets aléatoires en hexadécimal
  `equipe`     VARCHAR(16)  DEFAULT NULL,              -- clé d'équipe de formation.php, NULL = pas encore placé
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_jeton` (`jeton`),
  KEY `idx_seance` (`formation`, `seance`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
