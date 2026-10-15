# ⭐ Estelar · Prospección por voz

Sistema web para registrar contactos de prospección de **Perú (+51)** y **Colombia (+57)** dictándolos por voz, con reportes mensuales.

> Dicta: **«51 954 016 836, interesado, rubro educación»** → revisa la vista previa → **Enter** → guardado.

**Despliegue en Hostinger paso a paso:** ver [DESPLIEGUE.md](DESPLIEGUE.md). Los datos de la base de datos van en `config/database.php`.

## Stack
PHP 8.2+ puro con PDO · MySQL 8 / MariaDB 10.6+ (utf8mb4) · HTML + CSS + JavaScript vanilla · Web Speech API · Chart.js (CDN) · Zona horaria America/Bogota.

## Funciones
- **Dictado por voz:** una frase por registro. Se elige la mejor de 3 alternativas del reconocedor y se muestra el texto en vivo mientras hablas.
- **Parser** en el navegador (`assets/parser.js`) y en el servidor (`src/Parser.php`), con la misma lógica:
  - números en palabras, dígitos o mezclados ("5 uno 9…"); decenas, centenas, «doble» y «triple»;
  - estados: interesado, medio interesado, reunión y no interesado;
  - rubro con «rubro», «sector» o «giro», tolerante a tildes y a plural/singular;
  - nota opcional al final, con la palabra «nota …».
- **Validación por país:** celular y fijo de Perú y Colombia, con el motivo exacto del error. Si el número llega sin código pero el formato es inequívoco, sugiere el país y pide confirmación.
- **Duplicados:** un número no se registra dos veces. Se muestra quién lo registró, cuándo, su estado, su rubro y el historial, y se ofrece **Actualizar**.
- **Reportes:** resumen y comparación con el mes anterior, Perú vs Colombia, tabla por rubro, evolución de 12 meses con gráfico y detalle paginado con botones de llamar y WhatsApp. Exporta CSV (UTF-8 con BOM).
- **Administración:** usuarios (crear, desactivar, cambiar rol o contraseña) y rubros (crear, renombrar, fusionar duplicados con sugerencias automáticas).

## Roles
| | Asesor | Admin |
|---|---|---|
| Dictar contactos | ✅ | ✅ |
| Ver contactos y reportes | Solo los suyos | Todos, con filtro por asesor |
| Usuarios y rubros | — | ✅ |

## Seguridad
Sentencias preparadas PDO · `password_hash` · cookies `Secure`/`HttpOnly`/`SameSite=Lax` y regeneración del ID de sesión · token CSRF en todos los POST, más verificación de origen · `htmlspecialchars` en toda la salida y DOM seguro en JS (sin `innerHTML` con datos) · Content-Security-Policy estricta · bloqueo temporal tras 5 intentos de login fallidos (por correo + IP, y por IP) · HTTPS forzado (PHP + `.htaccess`) · errores solo al log fuera de `public_html` · credenciales solo en `config/database.php`, fuera de `public_html`.

## Estructura
```
config/database.php (datos de la BD)   config/config.php   install/schema.sql   install/install.php
src/  bootstrap · Database · Parser · Telefono · Auth · Csrf · Http · Vista
      ContactoRepository · RubroRepository · ReporteRepository · UsuarioRepository
tests/ parser_test.php · parser_test.js · casos_parser.json
public_html/ index · login · logout · reportes · rubros · usuarios
             api/ guardar · verificar · listar · reporte_mensual · exportar
             assets/ parser.js · app.js · reportes.js · comun.js · styles.css · logo.svg
             .htaccess · .user.ini
```

## Pruebas
```bash
php tests/parser_test.php
node tests/parser_test.js
```
