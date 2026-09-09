<?php
// Export PDF de toutes les évaluations à froid : une réponse par page.
// La mise en page vit dans eval-froide-pdf.php, partagée avec la notification
// par mail qui n'en tire qu'une page.

require __DIR__ . '/config.php';

// Accès réservé à l'animateur
if (!hash_equals(CLE_ANIMATEUR, (string)($_GET['cle'] ?? ''))) {
    http_response_code(403);
    exit('Accès réservé. Ajoutez ?cle=... à l\'adresse.');
}

try {
    require __DIR__ . '/eval-froide-pdf.php';   // -> dates, questions, FPDF
} catch (RuntimeException $e) {
    http_response_code(500);
    exit($e->getMessage());
}

try {
    $reponses = db()->query(
        'SELECT r.*, t.libelle FROM eval_froide r
         JOIN eval_froide_tokens t ON t.id = r.token_id
         ORDER BY r.created_at'
    )->fetchAll();
} catch (PDOException $e) {
    http_response_code(500);
    exit('Tables absentes : jouez migration-eval-froide.sql.');
}

if (!$reponses) {
    http_response_code(404);
    exit('Aucune réponse à exporter pour le moment.');
}

ef_pdf_document($reponses)->Output('D', 'evaluations-a-froid.pdf');
