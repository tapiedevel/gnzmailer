<?php
// Activar la visualización de todos los errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<pre>"; // Usar <pre> para un formato más legible

echo "Iniciando script de depuración de GnzMailer...\n";

$configFile = 'smtp_config.json';
echo "Verificando archivo de configuración: {$configFile}...\n";

if (!file_exists($configFile)) {
    die("ERROR: El archivo de configuración '{$configFile}' no existe.");
}

echo "Archivo de configuración encontrado.
";

$config = json_decode(file_get_contents($configFile), true);

require_once 'gnzmailer.php';
echo "Clase GnzMailer cargada.
";

$mailer = new GnzMailer();

if (isset($config['to'])) {
    $mailer->addTo($config['to']);
}

if ($mailer->mailer !== 'smtp') {
    die("ERROR: GnzMailer no se configuró para usar SMTP. Revisa el constructor y el archivo JSON.");
}

echo "GnzMailer inicializado en modo SMTP.\n";

echo "Estableciendo datos del correo desde smtp_config.json...\n";

echo "Intentando enviar correo...\n";

try {
    if ($mailer->send()) {
        echo "\nÉXITO: El método send() de GnzMailer devolvió true.\n";
        echo "El correo debería haber sido enviado. Revisa la bandeja de entrada del destinatario.\n";
    } else {
        echo "\nFALLO: El método send() de GnzMailer devolvió false.\n";
        echo "Revisa los logs de error de PHP para ver los mensajes detallados que se registraron con error_log().\n";
    }
} catch (Exception $e) {
    echo "\nEXCEPCIÓN CAPTURADA: " . $e->getMessage() . "\n";
}

echo "\nScript de depuración finalizado.\n";
echo "</pre>";

?>
