# Guía de despliegue en Hostinger · Estelar

> ⚠️ El micrófono **solo funciona con HTTPS**. Hostinger da el SSL gratis.

## Dónde va cada cosa en Hostinger

```
/home/u123456789/domains/tudominio.com/     ← carpeta del dominio (NO pública)
├── config/
│   ├── config.php        (no se toca)
│   └── database.php      ← AQUÍ PONES LOS DATOS DE LA BASE DE DATOS
├── src/  install/  tests/
├── logs/  storage/
└── public_html/          ← lo único visible en internet
    ├── index.php, login.php, reportes.php…
    ├── api/  assets/
    └── .htaccess  .user.ini
```

`config/`, `src/` e `install/` van **al lado de `public_html`, no dentro**. Así nadie puede ver la contraseña desde internet.

---

## Paso 1 · Crear la base de datos
1. hPanel → **Bases de datos** → **Administración**.
2. Crea una base nueva: escribe un nombre (ej. `estelar`), un usuario (ej. `estelar`) y una contraseña.
3. Hostinger les agrega un prefijo, por ejemplo `u123456789_estelar`. **Anota el nombre, el usuario y la contraseña.**

## Paso 2 · Crear las tablas
1. En la misma pantalla, junto a tu base, presiona **Ingresar a phpMyAdmin**.
2. Pestaña **Importar** → elige el archivo `install/schema.sql` → **Importar**.
3. Se crean las tablas y los 10 rubros iniciales.

## Paso 3 · Subir los archivos
1. hPanel → **Archivos** → **Administrador de archivos**.
2. Entra a `domains/tudominio.com/`.
3. Sube el proyecto en un `.zip` y **extráelo ahí mismo**:
   - las carpetas `config`, `src`, `install`, `tests`, `logs` y `storage` quedan en `domains/tudominio.com/`;
   - el contenido de `public_html` queda dentro de `domains/tudominio.com/public_html/`.
4. Si `public_html` ya tenía un `index.html` o `default.php` de Hostinger, **bórralo**.

## Paso 4 · Poner tus datos en `config/database.php`
1. En el Administrador de archivos abre `domains/tudominio.com/config/database.php` → clic derecho → **Editar**.
2. Cambia solo estas líneas:
   ```php
   'db_host'    => 'localhost',
   'db_nombre'  => 'u123456789_estelar',
   'db_usuario' => 'u123456789_estelar',
   'db_clave'   => 'tu-contraseña',
   'url_sitio'  => 'https://tudominio.com/VOZ',   // opcional, la carpeta se detecta sola
   ```
3. **Guardar.**

## Paso 5 · Versión de PHP
hPanel → **Avanzado** → **Configuración de PHP** → elige **PHP 8.2** o **8.3**.

## Paso 6 · SSL (HTTPS)
hPanel → **Seguridad** → **SSL** → revisa que tu dominio diga **Activo**. Si no, presiona **Instalar SSL** (es gratis). El `.htaccess` ya manda todo de `http://` a `https://`.

## Paso 7 · Crear el usuario administrador
**Si tu plan tiene SSH** (hPanel → **Avanzado** → **Acceso SSH**), conéctate y ejecuta:
```bash
cd domains/tudominio.com
php install/install.php --solo-admin
```
Te pide nombre, correo y contraseña (la contraseña no se ve mientras escribes).

**Si no tienes SSH**, usa un Cron Job una sola vez:
1. En el Administrador de archivos, crea `domains/tudominio.com/clave.txt` con la contraseña del admin adentro.
2. hPanel → **Avanzado** → **Cron Jobs** → **Personalizado** → pega este comando, con tus datos:
   ```
   /usr/bin/php /home/u123456789/domains/tudominio.com/install/install.php --solo-admin --nombre="Tu Nombre" --email=tu@correo.com --password-file=/home/u123456789/domains/tudominio.com/clave.txt
   ```
   Elige **cada minuto** y guarda.
3. Espera 1 o 2 minutos y **borra el Cron Job y el archivo `clave.txt`**.

## Paso 8 · Probar
1. Abre `https://tudominio.com` → aparece el login → entra con el admin.
2. Comprueba que estas direcciones den error (403/404), porque son privadas:
   - `https://tudominio.com/../config/database.php`
   - `https://tudominio.com/.htaccess`
3. En **Usuarios**, crea las cuentas de los asesores.

## Paso 9 · Probar el micrófono (Chrome o Edge)
1. Toca el **botón dorado del micrófono** → **Permitir** cuando Chrome pregunte.
2. Di: «**cinco uno nueve cinco cuatro cero uno seis ocho tres seis, interesado, rubro educación**».
3. Aparece la vista previa con Perú, `+51 954 016 836`, Interesado y Educación → **Enter** para guardar.

Si no funciona:
- Toca el candado 🔒 junto a la dirección → **Micrófono: Permitir** → recarga la página.
- La dirección debe empezar con **https://**.
- Firefox y Safari no permiten el dictado: usa la caja de texto o **Llenar formulario manual**.

---

## Si algo falla
- **Página en blanco o «Algo salió mal»:** revisa `domains/tudominio.com/logs/php-error.log`. Casi siempre es un dato mal escrito en `config/database.php`.
- **«Access denied for user»:** el usuario o la contraseña de la base están mal en `config/database.php`.
- **«url_sitio debe empezar con https://»:** corrige `'url_sitio'`.
- **Usuario bloqueado por intentos:** el bloqueo dura 15 minutos.

## Respaldo
hPanel → **Archivos** → **Copias de seguridad**. Hostinger respalda automáticamente; también puedes descargar la base desde phpMyAdmin → **Exportar**.
