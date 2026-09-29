<?php
// Mur des objectifs : un post-it par participant, sur le tableau blanc de mur.php.
//
// Les coordonnées sont des fractions du tableau (0 à 1) : la disposition est la
// même sur tout écran. Le tableau est en 16/9 ; un post-it y occupe
// POSTIT_LARGEUR de la largeur, et autant en hauteur réelle (il est carré).
//
// Aucune sortie : ce fichier ne définit que des helpers.
require_once __DIR__ . '/participants.php';

const OBJECTIF_AMORCE   = 'Ce soir, je veux repartir avec une IA qui m\'aide à';
const OBJECTIF_MAX      = 160;   // caractères, taille de la colonne `texte`
const POSTIT_LARGEUR    = 0.14;
const POSTIT_HAUTEUR    = POSTIT_LARGEUR * 16 / 9;
// Papier pastel : jaune, rose, vert, bleu, orange
const POSTIT_COULEURS   = ['#FFF3A0', '#FBC4D8', '#CDE8A8', '#B9E2F5', '#FFD19A'];

// Le post-it de ce participant, ou null
function objectif_de(int $participant_id): ?array
{
    try {
        $st = db()->prepare('SELECT id, texte, couleur FROM objectifs WHERE participant_id = ?');
        $st->execute([$participant_id]);
        return $st->fetch() ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

// Tous les post-its de la séance, du dessous vers le dessus de la pile
function objectifs_seance(): array
{
    $st = db()->prepare(
        'SELECT o.id, o.texte, o.couleur, o.x, o.y, o.rotation, o.z, p.prenom
           FROM objectifs o JOIN participants p ON p.id = o.participant_id
          WHERE p.formation = ? AND p.seance = ?
          ORDER BY o.z, o.id'
    );
    $st->execute([formation_slug(), aujourdhui_local()]);
    return array_map(fn($o) => [
        'id'       => (int)$o['id'],
        'texte'    => $o['texte'],
        'prenom'   => $o['prenom'],
        'rang'     => (int)$o['couleur'],
        'couleur'  => POSTIT_COULEURS[(int)$o['couleur'] % count(POSTIT_COULEURS)],
        'x'        => (float)$o['x'],
        'y'        => (float)$o['y'],
        'rotation' => (float)$o['rotation'],
        'z'        => (int)$o['z'],
    ], $st->fetchAll());
}

// Emplacement d'un nouveau post-it : parmi quelques tirages au hasard, celui
// qui s'éloigne le plus des post-its déjà collés. La bande du haut reste au titre.
function objectif_emplacement(array $existants): array
{
    $meilleur = null;
    $ecart    = -1.0;
    for ($i = 0; $i < 40; $i++) {
        $x = 0.02 + lcg_value() * (0.96 - POSTIT_LARGEUR);
        $y = 0.13 + lcg_value() * (0.85 - POSTIT_HAUTEUR);
        $min = INF;
        foreach ($existants as $o) {
            // Distance en proportions réelles du tableau (16/9)
            $min = min($min, hypot(($x - $o['x']) * 16, ($y - $o['y']) * 9));
        }
        if ($min > $ecart) {
            [$meilleur, $ecart] = [[$x, $y], $min];
        }
    }
    return $meilleur;
}

// Crée ou met à jour le post-it du participant. Une mise à jour ne change que
// le texte : le post-it reste là où le formateur l'a placé.
function objectif_enregistrer(int $participant_id, string $texte): void
{
    if (objectif_de($participant_id)) {
        $st = db()->prepare('UPDATE objectifs SET texte = ? WHERE participant_id = ?');
        $st->execute([$texte, $participant_id]);
        return;
    }
    $existants = objectifs_seance();
    [$x, $y]   = objectif_emplacement($existants);

    // Couleur au hasard parmi les moins utilisées : un mur varié, jamais tout rose
    $usage = array_fill(0, count(POSTIT_COULEURS), 0);
    foreach ($existants as $o) {
        $usage[$o['rang'] % count(POSTIT_COULEURS)]++;
    }
    $libres = array_keys($usage, min($usage));
    $couleur = $libres[array_rand($libres)];

    $z         = $existants ? max(array_column($existants, 'z')) + 1 : 1;
    $st = db()->prepare('INSERT INTO objectifs (participant_id, texte, couleur, x, y, rotation, z)
                         VALUES (?, ?, ?, ?, ?, ?, ?)');
    $st->execute([
        $participant_id,
        $texte,
        $couleur,
        round($x, 4),
        round($y, 4),
        random_int(-60, 60) / 10,   // légèrement de travers, comme collé à la main
        $z,
    ]);
}

// Couleur de papier d'un post-it (jaune par défaut, avant le premier envoi)
function objectif_papier(?array $objectif): string
{
    return POSTIT_COULEURS[(int)($objectif['couleur'] ?? 0) % count(POSTIT_COULEURS)];
}

// Nettoie la saisie : espaces superflus retirés, longueur bornée. '' si vide.
function objectif_texte_clean(?string $texte): string
{
    $texte = trim(preg_replace('/\s+/u', ' ', (string)$texte));
    return mb_substr($texte, 0, OBJECTIF_MAX);
}
