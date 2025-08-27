<?php
/**
 * Ejemplo de uso de GnzMailer.
 * Este archivo actúa como frontend (formulario) y backend (procesador de envío).
 */

// Cargar la configuración
$config = [];
if (file_exists('smtp_config.json')) {
    $config = json_decode(file_get_contents('smtp_config.json'), true);
}

// Bloque de Backend: Procesar la solicitud de envío de correo.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // Usar datos del JSON como remitente
    $fromEmail = isset($config['from_email']) ? $config['from_email'] : 'no-reply@example.com';
    $fromName = isset($config['from_name']) ? $config['from_name'] : 'GnzMailer Test';

    // Obtener datos del POST
    $to = isset($_POST['to']) ? $_POST['to'] : '';
    $cc = isset($_POST['cc']) ? array_map('trim', explode(',', $_POST['cc'])) : [];
    $bcc = isset($_POST['bcc']) ? array_map('trim', explode(',', $_POST['bcc'])) : [];
    $subject = isset($_POST['subject']) ? $_POST['subject'] : '';
    $message = isset($_POST['message']) ? $_POST['message'] : '';

    if (empty($to) || empty($subject) || empty($message)) {
        echo json_encode(['status' => 'error', 'message' => 'Por favor, completa todos los campos.']);
        exit;
    }

    require_once 'gnzmailer.php';
    $mailer = new GnzMailer();

    $mailer->setFrom($fromEmail, $fromName);
    $mailer->addTo($to);

    foreach ($cc as $cc_email) {
        if(!empty($cc_email)) $mailer->addCC($cc_email);
    }
    foreach ($bcc as $bcc_email) {
        if(!empty($bcc_email)) $mailer->addBCC($bcc_email);
    }

    $mailer->setSubject($subject);
    $mailer->setBody("<p>" . nl2br(htmlspecialchars($message)) . "</p>");

    // Manejar adjuntos
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
        echo json_encode(['status' => 'success', 'message' => "Correo enviado correctamente a {$to}"]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al enviar el correo. Causa probable: ' . $mailer->error]);
    }
    
    // Limpiar adjuntos
    foreach ($mailer->attachments as $attachment) {
        unlink($attachment);
    }

    exit; // Terminar el script después de la respuesta AJAX.
}

// Bloque de Frontend: Mostrar el formulario HTML.
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GnzMailer - Test de Envío</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 40px; background-color: #f0f2f5; color: #333; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; padding: 20px 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #1d4ed8; text-align: center; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="email"], input[type="text"], input[type="file"], textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        textarea { resize: vertical; min-height: 120px; }
        button { background-color: #2563eb; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; width: 100%; font-size: 16px; font-weight: bold; }
        button:hover { background-color: #1d4ed8; }
        .result { margin-top: 20px; padding: 15px; border-radius: 4px; text-align: center; font-weight: bold; }
        .result.success { background-color: #dcfce7; color: #166534; }
        .result.error { background-color: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

<div class="container">
    <h1>GnzMailer Test</h1>
    <form id="mail-form" enctype="multipart/form-data">
        <div class="form-group">
            <label for="to">Para:</label>
            <input type="email" id="to" name="to" value="<?php echo htmlspecialchars(isset($config['to']) ? $config['to'] : ''); ?>" required>
        </div>
        <div class="form-group">
            <label for="cc">CC (emails separados por coma):</label>
            <input type="text" id="cc" name="cc" value="<?php echo htmlspecialchars(isset($config['cc']) ? implode(', ', $config['cc']) : ''); ?>">
        </div>
        <div class="form-group">
            <label for="bcc">CCO (emails separados por coma):</label>
            <input type="text" id="bcc" name="bcc" value="<?php echo htmlspecialchars(isset($config['bcc']) ? implode(', ', $config['bcc']) : ''); ?>">
        </div>
        <div class="form-group">
            <label for="subject">Asunto:</label>
            <input type="text" id="subject" name="subject" value="<?php echo htmlspecialchars(isset($config['subject']) ? $config['subject'] : ''); ?>" required>
        </div>
        <div class="form-group">
            <label for="message">Mensaje:</label>
            <textarea id="message" name="message" required><?php echo htmlspecialchars(isset($config['body']) ? $config['body'] : ''); ?></textarea>
        </div>
        <div class="form-group">
            <label for="attachments">Adjuntos:</label>
            <input type="file" id="attachments" name="attachments[]" multiple>
        </div>
        <button type="submit">Enviar Correo</button>
    </form>
    <div id="result-message" class="result" style="display: none;"></div>
</div>

<script>
    document.getElementById('mail-form').addEventListener('submit', function(event) {
        event.preventDefault();

        const form = event.target;
        const formData = new FormData(form);
        const resultDiv = document.getElementById('result-message');
        const submitButton = form.querySelector('button');

        resultDiv.style.display = 'none';
        submitButton.disabled = true;
        submitButton.textContent = 'Enviando...';

        fetch(window.location.href, { // Enviar al propio archivo index.php
            method: 'POST',
            body: formData
        })
        .then(response => {
            // Clonamos la respuesta para poder leerla como JSON y como texto si falla
            const responseClone = response.clone();
            return response.json().catch(() => {
                // Si .json() falla, leemos la respuesta como texto y la mostramos.
                return responseClone.text().then(text => {
                    throw new Error('<b>Error:</b> La respuesta del servidor no es JSON. Esto usualmente indica un error de PHP. <br><br><b>Respuesta del Servidor:</b><pre>' + text + '</pre>');
                });
            });
        })
        .then(data => {
            resultDiv.textContent = data.message;
            resultDiv.className = 'result ' + data.status;
            resultDiv.style.display = 'block';

            if (data.status === 'success') {
                form.reset();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            resultDiv.innerHTML = error.message; // Usamos innerHTML para renderizar el HTML del error.
            resultDiv.className = 'result error';
            resultDiv.style.display = 'block';
        })
        .finally(() => {
            submitButton.disabled = false;
            submitButton.textContent = 'Enviar Correo';
        });
    });
</script>

</body>
</html>