<?php
/**
 * Configurador para GnzMailer.
 * Permite modificar el archivo smtp_config.json a través de un formulario web.
 */

$configFile = 'smtp_config.json';
$config = [];
$message = '';

// Cargar la configuración actual
if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true);
}

// Si se envió el formulario para guardar o probar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Asignar los valores del POST a la configuración
    $currentConfig = [
        'host' => isset($_POST['host']) ? $_POST['host'] : '',
        'port' => isset($_POST['port']) ? (int)$_POST['port'] : 587,
        'username' => isset($_POST['username']) ? $_POST['username'] : '',
        'password' => isset($_POST['password']) ? $_POST['password'] : '',
        'encryption' => isset($_POST['encryption']) ? $_POST['encryption'] : '',
        'from_email' => isset($_POST['from_email']) ? $_POST['from_email'] : '',
        'from_name' => isset($_POST['from_name']) ? $_POST['from_name'] : '',
        'to' => isset($_POST['to']) ? $_POST['to'] : '',
        'cc' => isset($_POST['cc']) ? array_map('trim', explode(',', $_POST['cc'])) : [],
        'bcc' => isset($_POST['bcc']) ? array_map('trim', explode(',', $_POST['bcc'])) : [],
        'subject' => isset($_POST['subject']) ? $_POST['subject'] : '',
        'body' => isset($_POST['body']) ? $_POST['body'] : '',
        'attachments' => isset($config['attachments']) ? $config['attachments'] : [], // Mantener adjuntos guardados
    ];

    $action = isset($_POST['action']) ? $_POST['action'] : 'save';

    if ($action === 'save') {
        // Guardar la configuración en el archivo JSON
        if (file_put_contents($configFile, json_encode($currentConfig, JSON_PRETTY_PRINT))) {
            $message = '<div class="result success">Configuración guardada correctamente.</div>';
            $config = $currentConfig; // Actualizar la configuración en la página
        } else {
            $message = '<div class="result error">Error al guardar la configuración.</div>';
        }
    } elseif ($action === 'test') {
        header('Content-Type: application/json');
        require_once 'gnzmailer.php';
        
        $mailer = new GnzMailer();
        
        $mailer->mailer = 'smtp';
        $mailer->smtp_host = $currentConfig['host'];
        $mailer->smtp_port = $currentConfig['port'];
        $mailer->smtp_user = $currentConfig['username'];
        $mailer->smtp_pass = $currentConfig['password'];
        $mailer->smtp_encryption = $currentConfig['encryption'];

        $mailer->setFrom($currentConfig['from_email'], $currentConfig['from_name']);
        $mailer->addTo($currentConfig['to']);
        
        foreach ($currentConfig['cc'] as $cc) {
            if(!empty($cc)) $mailer->addCC($cc);
        }
        foreach ($currentConfig['bcc'] as $bcc) {
            if(!empty($bcc)) $mailer->addBCC($bcc);
        }

        $mailer->setSubject('Prueba de Configuración de GnzMailer');
        $mailer->setBody('<h1>¡Prueba Exitosa!</h1><p>Si recibes este correo, la configuración proporcionada en el panel es correcta.</p>');

        // Manejar adjuntos subidos para la prueba
        if (isset($_FILES['attachments'])) {
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            foreach ($_FILES['attachments']['tmp_name'] as $key => $tmpName) {
                if (!empty($tmpName) && $_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                    $filePath = $uploadDir . basename($_FILES['attachments']['name'][$key]);
                    if (move_uploaded_file($tmpName, $filePath)) {
                        $mailer->addAttachment($filePath);
                    }
                }
            }
        }


        if ($mailer->send()) {
            echo json_encode(['status' => 'success', 'message' => '¡Correo de prueba enviado con éxito a ' . htmlspecialchars($currentConfig['to']) . '!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error al enviar el correo de prueba. Causa probable: ' . $mailer->error]);
        }
        
        // Limpiar adjuntos de prueba
        foreach ($mailer->attachments as $attachment) {
            unlink($attachment);
        }

        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurador de GnzMailer</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 40px; background-color: #f0f2f5; color: #333; }
        .container { max-width: 800px; margin: 0 auto; background: #fff; padding: 20px 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #1d4ed8; text-align: center; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 15px; }
        .full-width { grid-column: 1 / -1; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="number"], input[type="password"], input[type="file"], select, textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        textarea { resize: vertical; min-height: 120px; }
        .button-group { display: flex; gap: 10px; }
        button { background-color: #2563eb; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; width: 100%; font-size: 16px; font-weight: bold; }
        button:hover { background-color: #1d4ed8; }
        button#test-button { background-color: #64748b; }
        button#test-button:hover { background-color: #475569; }
        .result { margin-bottom: 20px; padding: 15px; border-radius: 4px; text-align: center; font-weight: bold; }
        .result.success { background-color: #dcfce7; color: #166534; }
        .result.error { background-color: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

<div class="container">
    <h1>Configurador de GnzMailer</h1>
    <div id="message-area"><?php echo $message; ?></div>
    <form id="config-form" method="POST" enctype="multipart/form-data">
        <div class="form-grid">
            <div class="form-group">
                <label for="host">Host SMTP:</label>
                <input type="text" id="host" name="host" value="<?php echo htmlspecialchars(isset($config['host']) ? $config['host'] : ''); ?>">
            </div>
            <div class="form-group">
                <label for="port">Puerto:</label>
                <input type="number" id="port" name="port" value="<?php echo htmlspecialchars(isset($config['port']) ? $config['port'] : 587); ?>">
            </div>
            <div class="form-group">
                <label for="username">Usuario:</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars(isset($config['username']) ? $config['username'] : ''); ?>">
            </div>
            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" value="<?php echo htmlspecialchars(isset($config['password']) ? $config['password'] : ''); ?>">
            </div>
            <div class="form-group">
                <label for="encryption">Cifrado:</label>
                <select id="encryption" name="encryption">
                    <option value="" <?php echo empty($config['encryption']) ? 'selected' : ''; ?>>Ninguno</option>
                    <option value="tls" <?php echo (isset($config['encryption']) && $config['encryption'] === 'tls') ? 'selected' : ''; ?>>TLS</option>
                    <option value="ssl" <?php echo (isset($config['encryption']) && $config['encryption'] === 'ssl') ? 'selected' : ''; ?>>SSL</option>
                </select>
            </div>
            <div class="form-group">
                <label for="from_email">Email Remitente:</label>
                <input type="text" id="from_email" name="from_email" value="<?php echo htmlspecialchars(isset($config['from_email']) ? $config['from_email'] : ''); ?>">
            </div>
            <div class="form-group">
                <label for="from_name">Nombre Remitente:</label>
                <input type="text" id="from_name" name="from_name" value="<?php echo htmlspecialchars(isset($config['from_name']) ? $config['from_name'] : ''); ?>">
            </div>
            <div class="form-group">
                <label for="to">Para (por defecto):</label>
                <input type="text" id="to" name="to" value="<?php echo htmlspecialchars(isset($config['to']) ? $config['to'] : ''); ?>">
            </div>
            <div class="form-group">
                <label for="cc">CC (emails separados por coma):</label>
                <input type="text" id="cc" name="cc" value="<?php echo htmlspecialchars(isset($config['cc']) ? implode(', ', $config['cc']) : ''); ?>">
            </div>
            <div class="form-group">
                <label for="bcc">CCO (emails separados por coma):</label>
                <input type="text" id="bcc" name="bcc" value="<?php echo htmlspecialchars(isset($config['bcc']) ? implode(', ', $config['bcc']) : ''); ?>">
            </div>
            <div class="form-group full-width">
                <label for="subject">Asunto (por defecto):</label>
                <input type="text" id="subject" name="subject" value="<?php echo htmlspecialchars(isset($config['subject']) ? $config['subject'] : ''); ?>">
            </div>
            <div class="form-group full-width">
                <label for="body">Cuerpo del Mensaje (por defecto):</label>
                <textarea id="body" name="body"><?php echo htmlspecialchars(isset($config['body']) ? $config['body'] : ''); ?></textarea>
            </div>
            <div class="form-group full-width">
                <label for="attachments">Adjuntos:</label>
                <input type="file" id="attachments" name="attachments[]" multiple>
            </div>
        </div>
        <div class="button-group">
            <button type="submit" name="action" value="save">Guardar Configuración</button>
            <button type="button" id="test-button">Probar Envío</button>
        </div>
    </form>
</div>

<script>
document.getElementById('test-button').addEventListener('click', function() {
    const form = document.getElementById('config-form');
    const formData = new FormData(form);
    formData.append('action', 'test');

    const messageArea = document.getElementById('message-area');
    const testButton = document.getElementById('test-button');

    messageArea.innerHTML = '';
    testButton.disabled = true;
    testButton.textContent = 'Enviando prueba...';

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        let resultClass = data.status === 'success' ? 'success' : 'error';
        messageArea.innerHTML = '<div class="result ' + resultClass + '">' + data.message + '</div>';
    })
    .catch(error => {
        console.error('Error:', error);
        messageArea.innerHTML = '<div class="result error">Error de comunicación con el servidor.</div>';
    })
    .finally(() => {
        testButton.disabled = false;
        testButton.textContent = 'Probar Envío';
    });
});
</script>

</body>
</html>