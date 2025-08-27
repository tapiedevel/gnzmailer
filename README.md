# GnzMailer

GnzMailer es una librería de PHP simple para enviar correos electrónicos usando SMTP o la función `mail()`. Está diseñada para ser fácil de configurar y usar.

## Características

- Envía correos electrónicos vía SMTP o `mail()`.
- Fácil de configurar con un archivo `smtp_config.json`.
- API simple e intuitiva.
- Incluye un configurador web para administrar fácilmente los ajustes.
- Incluye un script de depuración para probar la configuración.
- Incluye un ejemplo simple de un formulario de contacto.

## Requisitos

- PHP 7.0 o superior.
- La extensión `openssl` es requerida para cifrado TLS/SSL.

## Cómo usar

### Configuración

La configuración se almacena en el archivo `smtp_config.json`. Puedes usar el script `configurator.php` para administrar fácilmente los ajustes.

```json
{
    "host": "smtp.ejemplo.com",
    "port": 587,
    "username": "user@ejemplo.com",
    "password": "tu_password",
    "encryption": "tls",
    "from_email": "from@ejemplo.com",
    "from_name": "Nombre",
    "to": "to@ejemplo.com",
    "subject": "asunto por default",
    "body": "Texto por default"
}
```

### Uso Básico

Aquí hay un ejemplo simple de cómo enviar un correo electrónico:

```php
<?php

require_once 'gnzmailer.php';

$mailer = new GnzMailer();

$mailer->addTo('destinatario@ejemplo.com');
$mailer->setSubject('Este es el asunto');
$mailer->setBody('Este es el cuerpo del correo.');

if ($mailer->send()) {
    echo "Correo enviado exitosamente.";
} else {
    echo "Error al enviar el correo: " . $mailer->error;
}

?>
```

## Archivos

- **`gnzmailer.php`**: El archivo principal de la librería.
- **`index.php`**: Un ejemplo simple de un formulario de contacto que usa GnzMailer.
- **`debug_smtp.php`**: Un script para probar la configuración SMTP.
- **`configurator.php`**: Un configurador web para administrar los ajustes en `smtp_config.json`.
- **`smtp_config.json`**: El archivo de configuración.