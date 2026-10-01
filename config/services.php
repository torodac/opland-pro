<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],


    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model'   => env('ANTHROPIC_MODEL', 'claude-opus-5-5'),
        // Modelo aparte para leer documentos (facturas). Es una tarea mecanica -- transcribir
        // importes de un PDF -- donde Haiku acierta lo mismo que Opus siendo el doble de rapido
        // y ~5,5 veces mas barato (medido sobre tres facturas: normal, con dos tipos de IVA y
        // en formato extranjero). Si algun dia se detecta que falla con facturas dificiles,
        // se sube a claude-sonnet-5-5 cambiando solo esta linea.
        'model_documentos' => env('ANTHROPIC_MODEL_DOCUMENTOS', 'claude-haiku-4-5'),
    ],

    'powerbi' => [
        'username'      => env('POWERBI_USERNAME'),
        'password'      => env('POWERBI_PASSWORD'),
        'client_id'     => env('POWERBI_CLIENT_ID'),
        'client_secret' => env('POWERBI_CLIENT_SECRET'),
    ],

    // Notificaciones push de la PWA. Estas claves se leían con env() directamente desde el
    // comando y el controlador, y eso deja de funcionar en cuanto se cachea la configuración:
    // env() devuelve null porque el .env ya no se carga. Pasó en producción entre el 20 y el 24
    // de septiembre de 2026 (ver 3.108 en DOC_TECNICO.md).
    'webpush' => [
        'subject'     => env('VAPID_SUBJECT'),
        'public_key'  => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],

    'breezeway' => [
        'client_id'     => env('BREEZEWAY_CLIENT_ID'),
        'client_secret' => env('BREEZEWAY_CLIENT_SECRET'),
    ],

    // Icnea. Dos juegos distintos de credenciales porque son dos interfaces:
    //   - api_key + owner_id → servicios REST (reservas y sus importes)
    //   - usr + pwd          → servicio SOAP del catálogo de alojamientos
    // Estaban escritas a mano en cuatro ficheros del repositorio; rotarlas obligaba a
    // desplegar código. Ver 3.119 en DOC_TECNICO.md.
    'icnea' => [
        'api_key'  => env('ICNEA_API_KEY'),
        'owner_id' => env('ICNEA_OWNER_ID'),
        'usr'      => env('ICNEA_USR'),
        'pwd'      => env('ICNEA_PWD'),
        'lang'     => env('ICNEA_LANG', 'es'),
    ],

    // Asesoría fiscal a la que se envían las facturas de opland, emitidas y recibidas.
    'asesoria' => [
        'email' => env('ASESORIA_EMAIL', 'santiago@srltaxlegal.com'),
    ],

    // Destinatarios y remitentes que también se leían con env() fuera de config.
    'correo' => [
        'informe_fallos_to' => env('MAIL_INFORME_FALLOS_TO', 'trodriguez@opland.es'),
        'nf_from'           => env('MAIL_NF_FROM_ADDRESS', 'naturefitness@opland.es'),
    ],

];
