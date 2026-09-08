# Formation IA générative — mini-site quiz & documents

Zéro dépendance, PHP ≥ 8.0 + MariaDB + Apache.

## Installation

```bash
# 1. Base de données
mysql -u root -p -e "CREATE DATABASE formation_ia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'formation'@'localhost' IDENTIFIED BY 'MOT_DE_PASSE';
GRANT SELECT, INSERT, UPDATE, DELETE ON formation_ia.* TO 'formation'@'localhost';"
mysql --default-character-set=utf8mb4 -u formation -p formation_ia < schema.sql

# 2. Fichiers
cp config.example.php config.php   # puis renseigner DB_* et CLE_ANIMATEUR
# déposer le tout dans le vhost, HTTPS obligatoire (navigator.clipboard l'exige)
```

`config.php` ne doit pas être versionné. Vérifier que Apache sert bien les
`.php` et que `config.php` n'est pas lisible en direct (il ne produit aucune
sortie, mais un `<FilesMatch "^config\.php$"> Require all denied </FilesMatch>`
ne coûte rien).

## Jour de formation

1. Générer un QR code vers `https://votredomaine.fr/` et le mettre dans les
   slides. Rien d'autre à préparer : plus de code de session à choisir ni à
   projeter, les participants n'ont que leur prénom à saisir. Les résultats
   sont regroupés par date d'enregistrement.
2. Ouvrir la télécommande sur votre téléphone :
   `https://votredomaine.fr/pilotage.php?cle=VOTRE_CLE`
3. Ouvrir le tableau de bord (pour vous, pas au projecteur pendant le quiz) :
   `https://votredomaine.fr/resultats.php?cle=VOTRE_CLE`

## La télécommande

`pilotage.php?cle=VOTRE_CLE` ouvre les sections de l'accueil au rythme du
groupe, pour éviter que les plus rapides prennent de l'avance sur les
exercices. Un interrupteur par étape, plus « Ouvrir l'étape suivante » et
« Tout refermer » à lancer en début de journée.

Une étape fermée **n'est pas envoyée** au navigateur : le participant n'en voit
que le titre, grisé et cadenassé. Rien à inspecter dans le code source, rien à
contourner. Un sondage toutes les 10 s fait apparaître un bandeau « Une
nouvelle étape est disponible » dès que vous ouvrez quelque chose ; le
participant l'affiche quand il veut, sans rechargement subi en pleine lecture.

La même page pilote aussi `quizzes.actif`, qui se réglait jusqu'ici en SQL :
l'étape « Les quiz » commande l'affichage du bloc sur l'accueil, les
interrupteurs du bas ouvrent et ferment chaque quiz individuellement.

Les étapes vivent dans la table `etapes` (`cle`, `titre`, `ordre`, `ouverte`).
Si la table est absente, l'accueil affiche tout comme avant et la télécommande
le signale — le site ne tombe jamais à cause d'une migration oubliée.

## Heure locale

Le serveur stocke les horodatages dans son propre fuseau, souvent UTC. Rien
n'est modifié en base : la conversion se fait à la lecture, dans
`horodatage.php`. Les heures affichées — tableau de bord comme PDF — sont donc
en heure locale, et le rattachement d'un enregistrement à une journée suit lui
aussi l'heure locale. Un quiz rempli à 00 h 30 appartient bien au jour même,
pas à la veille, y compris au changement d'heure.

Par défaut : serveur en `UTC`, affichage en `Europe/Paris`. Pour un autre
réglage, ajoutez dans `config.php` :

```php
define('FUSEAU_SERVEUR', 'UTC');
define('FUSEAU_AFFICHAGE', 'Europe/Paris');
```

## Le tableau de bord

`resultats.php?cle=VOTRE_CLE` s'ouvre sur la liste des journées de formation,
de la plus récente à la plus ancienne. Un clic sur une journée charge en AJAX
tout ce qui a été enregistré ce jour-là, toutes sessions et tous quiz
confondus : participants, score moyen, questions les plus ratées, satisfaction
et verbatims. La journée en cours se rafraîchit toute seule toutes les 10 s.

Le bouton « Bilan toutes sessions » donne les statistiques cumulées :
nombre de journées et de participants, score moyen global, évolution journée
par journée, moyennes par quiz, questions les plus ratées depuis le début et
satisfaction consolidée.

Les anciens avis migrés (notes à 0) sont comptés mais exclus des moyennes ;
la page le signale quand c'est le cas.

Deux paramètres facultatifs : `&d=2026-07-29` ouvre directement une journée,
`&s=PARIS-0726` ouvre la journée de cette session (compatibilité avec les
anciens liens).

### La colonne `session_code`

La saisie d'un code de session a été supprimée : le serveur écrit désormais
la date du jour (`AAAA-MM-JJ`) dans `session_code`. La colonne est conservée
telle quelle — aucune migration à jouer — et les enregistrements antérieurs
gardent leurs codes d'origine, que le tableau de bord affiche encore pour les
journées concernées. Le regroupement, lui, s'appuie sur `created_at` et non
sur cette colonne.

## Ajouter ou modifier un quiz

Tout passe par SQL (phpMyAdmin ou CLI), aucun code à toucher :

```sql
INSERT INTO quizzes (slug, titre) VALUES ('quiz-matin', 'Quiz de mi-journée');
INSERT INTO questions (quiz_id, ordre, texte, bonne_reponse, explication)
VALUES (LAST_INSERT_ID(), 1, 'Texte de la question ?', 'VRAI', 'Explication affichée après la réponse.');
```

### Formulaire de satisfaction

Lien sur l accueil (bloc « Votre avis ») ou URL directe a projeter en fin de
journee : `https://votredomaine.fr/satisfaction.php`.
Anonyme : aucune donnee personnelle, seule la date est rattachee.
Les moyennes, repartitions et commentaires remontent en direct dans
`resultats.php`, journée par journée et en cumulé.

Installation existante (base deja creee avant cet ajout) : rejouer uniquement
le bloc `CREATE TABLE IF NOT EXISTS satisfaction` de `schema.sql`.

### Exporter les questionnaires en PDF

`export-satisfaction.php?cle=VOTRE_CLE` liste les journées ayant recueilli des
avis ; le lien figure aussi en bas du bloc Satisfaction du tableau de bord.
Le PDF restitue le questionnaire tel qu'il a été rempli — une page par avis,
cases cochées d'une croix, zones de commentaire encadrées — pour l'archivage
ou la remise au financeur.

Un avis très bavard peut déborder sur une seconde page : le commentaire est
conservé en entier plutôt que tronqué, et l'avis suivant repart toujours d'une
page neuve.

La génération repose sur **FPDF**, déposé dans `lib/fpdf/` (fichier `fpdf.php`
et dossier `font/`, qui doivent rester côte à côte). Aucun gestionnaire de
dépendances, aucun autoloader. FPDF écrit en CP1252, qui couvre l'intégralité
du français ; les caractères hors de ce jeu — emojis saisis dans un
commentaire, par exemple — sont retirés sans casser le reste du texte.

### Activer l'ouverture progressive sur une base existante

```bash
mysql --default-character-set=utf8mb4 -u root -p formation_ia < migration-etapes.sql
```

À jouer une seule fois, avec un compte administrateur : l'utilisateur
applicatif n'a que `SELECT/INSERT/UPDATE/DELETE` et ne peut pas créer la table.
Le script est sans effet s'il est rejoué.

Désactiver un quiz sans le supprimer : `UPDATE quizzes SET actif = 0 WHERE slug = '...';`
Il disparaît de l'accueil et `save.php` refuse les enregistrements.

## Évaluation à froid (suivi après formation)

Questionnaire envoyé quelques semaines après la formation, pour mesurer ce que
les participants ont réellement réutilisé. **Il n'est accessible depuis aucune
page publique** : chaque participant reçoit un lien personnel à usage unique.

### Générer et suivre les liens

`eval-froide-liens.php?cle=VOTRE_CLE` génère un lien par participant (avec un
libellé facultatif : nom, email…) et affiche leur statut — *en attente* ou
*répondu*. Copiez chaque lien dans le message de relance envoyé au participant.

Un lien vaut `evaluation-froide.php?t=TOKEN`. **Une seule réponse par lien** :
après envoi, le token est consommé (colonne `used_at`) et une nouvelle visite
affiche « Vous avez déjà répondu ». La consommation est atomique — un double-clic
ou un rechargement n'enregistre jamais deux fois.

### Consulter et exporter les réponses

`eval-froide-resultats.php?cle=VOTRE_CLE` (aussi accessible depuis le tableau de
bord) présente une synthèse (répartition de chaque question) puis le détail de
chaque réponse. Le lien « Exporter en PDF » produit `export-eval-froide.php`,
un questionnaire par page, cases cochées — même rendu FPDF que l'export de
satisfaction, pour l'archivage ou le financeur.

Le champ Nom / Prénom est facultatif ; le libellé du lien permet de retrouver
qui a répondu même sans nom saisi.

### Activer sur une base existante

```bash
mysql --default-character-set=utf8mb4 -u root -p formation_ia < migration-eval-froide.sql
```

À jouer une seule fois avec un compte administrateur (l'utilisateur applicatif
ne peut pas créer de table). Sans effet s'il est rejoué. Le questionnaire lui-même
(intitulés, options) vit dans `eval-froide-questions.php`.

## RGPD

- Collecte minimale : prénom, réponses, score, horodatage. Ni nom, ni IP, ni cookie.
- Mention d'information affichée sur l'accueil et avant chaque quiz.
- Purge (cron mensuel conseillé) :

```
0 4 1 * * mysql -u formation -pMOT_DE_PASSE formation_ia -e "DELETE FROM resultats WHERE created_at < NOW() - INTERVAL 12 MONTH;"
```

- Suppression à la demande : `DELETE FROM resultats WHERE DATE(created_at)='2026-07-29' AND prenom='...';`

## Fichiers

| Fichier | Rôle |
|---|---|
| `schema.sql` | Tables + quiz final pré-rempli (10 questions) |
| `migration-etapes.sql` | Ajout de la table `etapes` sur une base existante |
| `migration-eval-froide.sql` | Ajout des tables de l'évaluation à froid sur une base existante |
| `config.example.php` | Identifiants BDD + clé animateur + helpers |
| `etapes.php` | Lecture des étapes ouvertes (aucune sortie, helpers seuls) |
| `horodatage.php` | Conversion des horodatages serveur vers l'heure locale (helpers seuls) |
| `pilotage.php` | Télécommande animateur : ouvre les étapes et les quiz (protégée par clé) |
| `index.php` | Accueil : sections dévoilées au fur et à mesure |
| `quiz.php` | Le quiz : prénom → questions une par une → feedback → score |
| `save.php` | Enregistrement du résultat (POST JSON, validations serveur) |
| `resultats.php` | Tableau de bord animateur : détail par journée + bilan global (protégé par clé) |
| `export-satisfaction.php` | Export PDF des questionnaires d'une journée, un par page (protégé par clé) |
| `eval-froide-questions.php` | Définitions du questionnaire à froid (intitulés, options, disposition) — helpers seuls |
| `evaluation-froide.php` | Formulaire d'évaluation à froid, accès par lien unique `?t=TOKEN` (invisible depuis l'accueil) |
| `eval-froide-liens.php` | Génération et suivi des liens uniques (protégé par clé) |
| `eval-froide-resultats.php` | Consultation des réponses à froid : synthèse + détail (protégé par clé) |
| `export-eval-froide.php` | Export PDF des évaluations à froid, une par page (protégé par clé) |
| `lib/fpdf/` | Bibliothèque FPDF (fpdf.php + font/), licence permissive, à conserver telle quelle |
| `style.css` | Styles partagés — Design System TAONAS (palette bleue, Eric Machat / Effra CC, angles vifs) |
| `assets/` | Polices TAONAS (Eric Machat, Effra CC) et logos ; référencés par `style.css` |
