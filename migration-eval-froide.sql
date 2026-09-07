-- Évaluation à froid : liens uniques + réponses.
-- À jouer une seule fois, avec un compte administrateur (l'utilisateur
-- applicatif n'a que SELECT/INSERT/UPDATE/DELETE et ne peut pas créer de table).
-- Sans effet s'il est rejoué (CREATE TABLE IF NOT EXISTS).
--
--   mysql --default-character-set=utf8mb4 -u root -p formation_ia < migration-eval-froide.sql

-- Un lien = une personne = un envoi. Le token vit dans l'URL du mail de relance.
CREATE TABLE IF NOT EXISTS `eval_froide_tokens` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `token`      CHAR(32)     NOT NULL,                       -- 16 octets aléatoires en hexadécimal
  `libelle`    VARCHAR(120) DEFAULT NULL,                   -- repère animateur (nom, email…), facultatif
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `used_at`    TIMESTAMP    NULL DEFAULT NULL,              -- NULL = lien pas encore utilisé
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Une ligne par questionnaire rempli. Les questions à choix multiple sont
-- stockées en codes joints par virgule (volumes faibles, lecture humaine).
CREATE TABLE IF NOT EXISTS `eval_froide` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `token_id`       INT UNSIGNED NOT NULL,
  `nom_prenom`     VARCHAR(120) DEFAULT NULL,               -- facultatif
  -- Q2 : usages de l'IA depuis la formation (multi)
  `q2_usages`      VARCHAR(255) DEFAULT NULL,
  `q2_autre`       VARCHAR(255) DEFAULT NULL,
  -- Q3 : capable d'utiliser seul(e) (simple)
  `q3_autonomie`   VARCHAR(20)  DEFAULT NULL,
  -- Q4 : plus à l'aise pour rédiger un prompt (simple)
  `q4_prompt`      VARCHAR(20)  DEFAULT NULL,
  -- Q5 : IA pour une candidature réelle (simple) + sous-question multi
  `q5_candidature` VARCHAR(20)  DEFAULT NULL,
  `q5_usages`      VARCHAR(255) DEFAULT NULL,
  `q5_autre`       VARCHAR(255) DEFAULT NULL,
  -- Q6 : changement de manière de rechercher (simple)
  `q6_changement`  VARCHAR(20)  DEFAULT NULL,
  -- Q7 : entretiens obtenus (simple) + sous-question IA préparation (simple)
  `q7_entretiens`  VARCHAR(30)  DEFAULT NULL,
  `q7_ia_prepa`    VARCHAR(10)  DEFAULT NULL,
  -- Q8 : formation utile avec le recul (simple)
  `q8_utile`       VARCHAR(20)  DEFAULT NULL,
  -- Q9 : davantage d'autonomie dans les démarches (simple)
  `q9_autonomie`   VARCHAR(20)  DEFAULT NULL,
  -- Q10 : chose apprise la plus utilisée (libre)
  `q10_apprise`    TEXT,
  -- Q11 : freins restants (multi)
  `q11_freins`     VARCHAR(255) DEFAULT NULL,
  `q11_autre`      VARCHAR(255) DEFAULT NULL,
  -- Q12 : ce qu'on aurait aimé pratiquer davantage (libre)
  `q12_manque`     TEXT,
  -- Q13 : poursuivre l'apprentissage (simple) + sujets (multi)
  `q13_poursuivre` VARCHAR(20)  DEFAULT NULL,
  `q13_sujets`     VARCHAR(255) DEFAULT NULL,
  `q13_autre`      VARCHAR(255) DEFAULT NULL,
  -- Q14 : évolution de la situation professionnelle (simple)
  `q14_situation`  VARCHAR(40)  DEFAULT NULL,
  `q14_autre`      VARCHAR(255) DEFAULT NULL,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_token` (`token_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
