<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';
require_admin();

try {
    // Verificar se o campo já existe
    $stmt = db()->query("SHOW COLUMNS FROM feedback_items LIKE 'show_brand_image'");
    $exists = $stmt->fetch();
    
    if (!$exists) {
        // Adicionar o campo
        db()->exec("ALTER TABLE feedback_items ADD COLUMN show_brand_image TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Exibir imagem da marca (1=sim, 0=não)' AFTER brand_image");
        $message = "Campo 'show_brand_image' adicionado com sucesso!";
        $success = true;
    } else {
        $message = "Campo 'show_brand_image' já existe.";
        $success = true;
    }
} catch (Throwable $e) {
    $message = "Erro: " . $e->getMessage();
    $success = false;
}

require_once __DIR__ . '/partials/layout_start.php';
?>

<div class="alert <?= $success ? 'alert-success' : 'alert-danger' ?>">
    <?= h($message) ?>
</div>

<a href="/admin/feedback.php" class="btn btn-primary">Voltar para Feedbacks</a>

<?php require_once __DIR__ . '/partials/layout_end.php'; ?>

