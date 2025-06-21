<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        .header { padding-bottom: 15px; border-bottom: 1px solid #eee; }
        .field { margin-bottom: 15px; }
        .field-label { font-weight: bold; color: #495057; }
        .footer { margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee; font-size: 0.9em; color: #6c757d; text-align: center; }
    </style>
</head>
<body>
<div class='container'>
    <div class='header'>
        <h2><?= htmlspecialchars($subject ?? 'Novo Contacto do Site') ?></h2>
    </div>
    <div class='content'>
        <div class='field'>
            <span class='field-label'>Nome:</span>
            <p><?= htmlspecialchars($name) ?></p>
        </div>
        <div class='field'>
            <span class='field-label'>E-mail:</span>
            <p><?= htmlspecialchars($email) ?></p>
        </div>
        <?php if (!empty($company)): ?>
            <div class='field'>
                <span class='field-label'>Empresa:</span>
                <p><?= htmlspecialchars($company) ?></p>
            </div>
        <?php endif; ?>
        <div class='field'>
            <span class='field-label'>Mensagem:</span>
            <p><?= nl2br(htmlspecialchars($query)) ?></p>
        </div>
    </div>
    <div class='footer'>
        <p>Enviado em <?= date('d/m/Y H:i:s') ?></p>
    </div>
</div>
</body>
</html>
