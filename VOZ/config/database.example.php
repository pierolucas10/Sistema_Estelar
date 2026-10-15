<?php
/**
 * ============================================================
 *  ESTELAR · DATOS DE CONEXIÓN (edita SOLO este archivo)
 * ============================================================
 *  En Hostinger: hPanel → Bases de datos → Bases de datos MySQL.
 *  Ahí ves el nombre de la base, el usuario y el host.
 *  La contraseña es la que pusiste al crear la base de datos.
 *
 *  Este archivo está en la carpeta config/, FUERA de public_html,
 *  así que nadie puede verlo desde internet.
 * ============================================================
 */

return [

    // ---------- BASE DE DATOS ----------
    'db_host'    => 'localhost',               // En Hostinger casi siempre es localhost
    'db_puerto'  => 3306,
    'db_nombre'  => 'u123456789_estelar',      // Nombre de la base (empieza con u + números)
    'db_usuario' => 'u123456789_estelar',      // Usuario de la base
    'db_clave'   => 'PON_AQUI_TU_CONTRASEÑA',  // Contraseña del usuario de la base

    // ---------- SITIO ----------
    'url_sitio'  => 'https://tudominio.com/VOZ',  // Opcional: la carpeta se detecta sola

    // ---------- OPCIONES (puedes dejarlas así) ----------
    'nombre_app'         => 'Estelar',
    'zona_horaria'       => 'America/Bogota',  // Perú y Colombia: UTC-5
    'idioma_voz'         => 'es-CO',           // es-CO o es-PE
    'detras_de_proxy'    => false,             // true solo si usas Cloudflare con proxy
    'sesion_minutos'     => 480,               // Cierre de sesión por inactividad
    'login_intentos'     => 5,                 // Intentos fallidos antes de bloquear
    'login_bloqueo_min'  => 15,                // Minutos de bloqueo
    'csv_separador'      => ',',               // "," o ";" según tu Excel

    // ---------- INGRESO DESDE ESTELAR (sin segundo login) ----------
    // Clave compartida: debe ser IDÉNTICA a VOZ_SSO_SECRET del .env de Laravel.
    // Vacía = el ingreso desde Estelar queda desactivado (solo login normal).
    'sso_secreto'        => '',
    // Ruta de Estelar que firma el ingreso (botón «Entrar con Estelar» del login).
    'sso_url_estelar'    => 'https://estelarsoftwarempresarial.com/leads/voz/entrar',
    // Sitio que puede mostrar VOZ dentro de un iframe (módulo de Leads).
    'sso_origen_estelar' => 'https://estelarsoftwarempresarial.com',

];
