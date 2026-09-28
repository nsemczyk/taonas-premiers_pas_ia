<?php
// Briques de l'accueil, partagées par toutes les formations : chaque
// formations/<slug>/accueil.php les assemble dans l'ordre de sa journée.
// Aucune sortie : ces fonctions rendent du HTML, à afficher avec echo / <?=.
require_once __DIR__ . '/etapes.php';

// Bouton « Copier » + confirmation + texte dépliable. Le script de copie est
// dans index.php ; $id doit être unique sur la page.
function texte_a_copier(string $id, string $bouton, string $confirmation, string $texte, string $style = ''): string
{
    return '<button class="btn btn-gold"' . ($style !== '' ? ' style="' . e($style) . '"' : '')
         . ' data-copy="' . e($id) . '">' . e($bouton) . '</button>' . "\n"
         . '    <div class="copied-note" id="note-' . e($id) . '">' . e($confirmation) . '</div>' . "\n"
         . '    <details class="doc">' . "\n"
         . '      <summary>Voir le texte</summary>' . "\n"
         . '      <pre id="txt-' . e($id) . '">' . e($texte) . '</pre>' . "\n"
         . '    </details>';
}

// Les quiz actifs de la formation courante, derrière l'étape $cle
function bloc_quiz(string $cle = 'quiz'): string
{
    if (!etape_ouverte($cle)) {
        return carte_verrouillee($cle);
    }
    $st = db()->prepare('SELECT slug, titre FROM quizzes WHERE actif = 1 AND formation = ? ORDER BY id');
    $st->execute([formation_slug()]);
    $quizzes = $st->fetchAll();

    $h = '<section class="card">' . "\n"
       . '    <h2>Les quiz</h2>' . "\n"
       . '    <p class="lead">Touchez un quiz pour commencer. Votre prénom suffit.</p>' . "\n";
    foreach ($quizzes as $q) {
        $h .= '      <a class="btn btn-primary" href="' . e(avec_f('quiz.php?slug=' . $q['slug'])) . '">' . "\n"
            . '        ' . e($q['titre']) . "\n"
            . '      </a>' . "\n";
    }
    if (!$quizzes) {
        $h .= '      <p class="lead">Aucun quiz ouvert pour le moment.</p>' . "\n";
    }
    return $h . '  </section>';
}

// Lien vers le questionnaire de satisfaction, derrière l'étape $cle
function bloc_avis(string $cle = 'avis'): string
{
    if (!etape_ouverte($cle)) {
        return carte_verrouillee($cle);
    }
    return '<section class="card">' . "\n"
         . '    <h2>Votre avis</h2>' . "\n"
         . '    <p class="lead">En fin de journée : 2 minutes, anonyme, pour améliorer la prochaine session.</p>' . "\n"
         . '    <a class="btn btn-ghost" href="' . e(avec_f('satisfaction.php')) . '">Donner mon avis sur la journée</a>' . "\n"
         . '  </section>';
}
