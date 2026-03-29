# 📱 Guía de Instalación — Módulo custom_events (Drupal 10)

Sigue esta guía paso a paso para instalar y ejecutar el proyecto
en Ubuntu 24.x desde cero. Cada paso debe ejecutarse en orden.

---

## ✅ Requisitos previos

| Herramienta   | Versión mínima |
|---------------|----------------|
| Ubuntu        | 24.x           |
| PHP           | 8.4+           |
| MySQL         | 8.0+           |
| Apache        | 2.4+           |
| Composer      | 2.x            |

---

## PASO 1 — Actualizar el sistema
```bash
sudo apt update && sudo apt upgrade -y
```

✔ Espera a que termine. Puede tardar varios minutos.

---

## PASO 2 — Instalar PHP 8.4 y extensiones
```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.4 php8.4-cli php8.4-fpm php8.4-mysql \
  php8.4-gd php8.4-mbstring php8.4-xml php8.4-curl \
  php8.4-zip php8.4-intl php8.4-opcache
```

Verificar que quedó instalado:
```bash
php -v
```
✔ Debe mostrar `PHP 8.4.x`

---

## PASO 3 — Instalar MySQL
```bash
sudo apt install -y mysql-server
sudo systemctl start mysql
sudo systemctl enable mysql
```

Verificar que está corriendo:
```bash
sudo systemctl status mysql
```
✔ Debe mostrar `active (running)`

---

## PASO 4 — Crear la base de datos
```bash
sudo mysql -u root
```

Dentro de MySQL, ejecuta estos comandos uno por uno:
```sql
CREATE DATABASE drupal_events CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'drupal_user'@'localhost' IDENTIFIED BY 'Drupal123!';
GRANT ALL PRIVILEGES ON drupal_events.* TO 'drupal_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

✔ Después del EXIT regresarás a la terminal normal.

---

## PASO 5 — Instalar Apache
```bash
sudo apt install -y apache2
sudo systemctl start apache2
sudo systemctl enable apache2
```

Habilitar módulos necesarios:
```bash
sudo a2enmod rewrite proxy_fcgi setenvif
sudo apt install php8.4-fpm -y
sudo a2enconf php8.4-fpm
sudo systemctl reload apache2
```

---

## PASO 6 — Instalar Composer
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer
```

Verificar:
```bash
composer --version
```
✔ Debe mostrar `Composer version 2.x.x`

---

## PASO 7 — Crear el proyecto Drupal
```bash
cd /var/www
sudo chown -R $USER:$USER /var/www
composer create-project drupal/recommended-project:^10 drupal-events
cd drupal-events
composer require drush/drush
```

✔ Esto puede tardar varios minutos dependiendo de la conexión a internet.

Verificar Drush:
```bash
vendor/bin/drush --version
```
✔ Debe mostrar `Drush Commandline Tool 13.x.x`

---

## PASO 8 — Configurar Apache para Drupal
```bash
sudo nano /etc/apache2/sites-available/drupal-events.conf
```

Pegar exactamente este contenido:
```apache
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot /var/www/drupal-events/web

    <Directory /var/www/drupal-events/web>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/drupal-events-error.log
    CustomLog ${APACHE_LOG_DIR}/drupal-events-access.log combined
</VirtualHost>
```

Guardar con `Ctrl+O`, Enter, luego cerrar con `Ctrl+X`.

Activar el sitio:
```bash
sudo a2dissite 000-default.conf
sudo a2ensite drupal-events.conf
sudo systemctl restart apache2
```

---

## PASO 9 — Preparar Drupal
```bash
cd /var/www/drupal-events
sudo chown -R $USER:www-data /var/www/drupal-events
sudo chmod -R 755 /var/www/drupal-events
cp web/sites/default/default.settings.php web/sites/default/settings.php
chmod 666 web/sites/default/settings.php
mkdir -p web/sites/default/files
chmod 777 web/sites/default/files
```

---

## PASO 10 — Instalar Drupal
```bash
cd /var/www/drupal-events
vendor/bin/drush site:install standard \
  --db-url='mysql://drupal_user:Drupal123!@localhost/drupal_events' \
  --site-name="Eventos Móvil Éxito" \
  --account-name="admin" \
  --account-pass="Admin123!" \
  --account-mail="admin@movilexito.com" \
  --locale=es \
  -y
```

✔ Cuando termine debe mostrar `[success] Installation complete.`

Permisos finales:
```bash
chmod 444 web/sites/default/settings.php
sudo chown -R $USER:www-data web/sites/default/files
sudo chmod -R 775 web/sites/default/files
```

Verificar en el navegador: **http://localhost**
- Si carga Drupal, el paso fue exitoso ✔

---

## PASO 11 — Instalar el tema hijo movilexito

El tema hijo es necesario para que el módulo ocupe el 100% del
ancho de la pantalla correctamente.

### 11.1 Crear la estructura del tema
```bash
mkdir -p /var/www/drupal-events/web/themes/custom/movilexito/css
```

### 11.2 Crear el archivo info del tema
```bash
nano /var/www/drupal-events/web/themes/custom/movilexito/movilexito.info.yml
```

Pegar:
```yaml
name: 'Móvil Éxito'
type: theme
description: 'Tema hijo de Olivero para Móvil Éxito'
core_version_requirement: ^10 || ^11
base theme: olivero
libraries:
  - movilexito/global
regions:
  header: Header
  content: Content
  footer: Footer
```

Guardar con `Ctrl+O`, Enter, `Ctrl+X`.

### 11.3 Crear el archivo de bibliotecas
```bash
nano /var/www/drupal-events/web/themes/custom/movilexito/movilexito.libraries.yml
```

Pegar:
```yaml
global:
  version: '1.0.0'
  css:
    theme:
      css/movilexito.css: {}
```

Guardar con `Ctrl+O`, Enter, `Ctrl+X`.

### 11.4 Crear el CSS del tema
```bash
nano /var/www/drupal-events/web/themes/custom/movilexito/css/movilexito.css
```

Copiar el contenido del archivo `movilexito.css` del repositorio.

Guardar con `Ctrl+O`, Enter, `Ctrl+X`.

### 11.5 Activar el tema
```bash
cd /var/www/drupal-events
vendor/bin/drush theme:enable movilexito -y
vendor/bin/drush config:set system.theme default movilexito -y
```

---

## PASO 12 — Instalar el módulo custom_events

### 12.1 Crear el directorio de módulos personalizados
```bash
mkdir -p /var/www/drupal-events/web/modules/custom
```

### 12.2 Copiar el módulo

Si tienes el módulo en un directorio local:
```bash
cp -r /ruta/a/custom_events \
  /var/www/drupal-events/web/modules/custom/
```

Si lo clonas desde GitHub:
```bash
cd /var/www/drupal-events/web/modules/custom
git clone <url-del-repositorio> custom_events
```

### 12.3 Habilitar el módulo
```bash
cd /var/www/drupal-events
vendor/bin/drush en custom_events -y
```

✔ Debe mostrar `[success] Module custom_events has been installed.`

### 12.4 Corregir permisos y limpiar caché
```bash
sudo chown -R $USER:www-data web/sites/default/files
sudo chmod -R 775 web/sites/default/files
vendor/bin/drush cr
```

✔ Debe mostrar `[success] Cache rebuild complete.`

### 12.5 Verificar que las tablas se crearon
```bash
mysql -u drupal_user -pDrupal123! drupal_events \
  -e "SHOW TABLES LIKE 'custom_event%';"
```

✔ Debe mostrar:
```
custom_event_registrations
custom_events
```

---

## PASO 13 — Configurar la página de inicio
```bash
cd /var/www/drupal-events
vendor/bin/drush config:set system.site page.front /eventos -y
vendor/bin/drush cr
```

✔ Ahora `http://localhost` redirige a `/eventos`

---

## PASO 14 — Crear usuarios de prueba
```bash
cd /var/www/drupal-events

# Usuario 1
vendor/bin/drush user:create usuario1 \
  --mail="usuario1@test.com" \
  --password="Test123!"

# Usuario 2 (opcional)
vendor/bin/drush user:create usuario2 \
  --mail="usuario2@test.com" \
  --password="Test123!"
```

---

## PASO 15 — Crear eventos de prueba

Inicia sesión como admin en `http://localhost/admin/eventos/crear`
- Usuario: `admin`
- Contraseña: `Admin123!`

Crea al menos 2 eventos con fechas futuras.

---

## PASO 16 — Flujo de prueba completo

Sigue estos pasos para verificar que todo funciona:

**Como administrador:**
1. Entrar a `http://localhost/admin/eventos/crear`
2. Crear un evento con título, descripción, país y fecha futura
3. Verificar que aparece en `http://localhost/eventos`

**Como usuario registrado:**
1. Cerrar sesión
2. Entrar a `http://localhost/user/login`
3. Iniciar sesión con `usuario1` / `Test123!`
4. Verificar que redirige a `http://localhost/eventos`
5. Hacer clic en **"¡Regístrame!"** en cualquier evento
6. Verificar el mensaje de confirmación sin recargar la página
7. Verificar que el contador de inscritos aumentó
8. Intentar hacer clic de nuevo en el mismo evento
9. Verificar que dice **"✓ Inscrito"** y no permite inscribirse dos veces

---

## ⚠️ Solución de problemas frecuentes

| Problema | Causa | Solución |
|----------|-------|----------|
| Error 500 en el sitio | Error en el código PHP | `vendor/bin/drush watchdog:show --count=5 --severity=3` |
| Las rutas dan 404 | Caché desactualizada | `vendor/bin/drush cr` |
| Error al limpiar caché (Permission denied) | Permisos incorrectos | `sudo chown -R $USER:www-data web/sites/default/files && sudo chmod -R 775 web/sites/default/files` |
| Las tablas no se crearon | Error en instalación | `vendor/bin/drush pmu custom_events -y && vendor/bin/drush en custom_events -y` |
| CSS o JS no actualiza | Caché del navegador | `vendor/bin/drush cr` + `Ctrl+Shift+R` en el navegador |
| Los países no cargan | API restcountries.com no disponible | El módulo usa lista de países de respaldo automáticamente |
| El sitio no redirige a /eventos | Página de inicio no configurada | `vendor/bin/drush config:set system.site page.front /eventos -y` |
| MySQL pide contraseña con Warning | Es solo una advertencia | No afecta el funcionamiento |

---

## 📋 Credenciales del proyecto

| Rol | Usuario | Contraseña |
|-----|---------|------------|
| Administrador Drupal | admin | Admin123! |
| Usuario de prueba 1 | usuario1 | Test123! |
| Usuario de prueba 2 | usuario2 | Test123! |
| Base de datos | drupal_user | Drupal123! |

---

## 🔗 URLs importantes

| Descripción | URL |
|-------------|-----|
| Página principal / Listado eventos | http://localhost/eventos |
| Crear evento (solo admin) | http://localhost/admin/eventos/crear |
| Detalle de evento | http://localhost/eventos/{id} |
| Iniciar sesión | http://localhost/user/login |
| Panel administración | http://localhost/admin |

## Decisiones técnicas

### Tablas propias vs nodos de Drupal
Se usaron tablas personalizadas en lugar del sistema de contenidos
de Drupal porque la relación usuario-evento requiere una tabla de
cruce con índice UNIQUE para garantizar que un usuario no se inscriba
dos veces. El sistema de nodos no implementa esto nativamente sin
módulos adicionales.

### Servicio centralizado (EventService)
Toda la lógica de negocio está en un único servicio inyectable.
Esto permite reutilizar la lógica desde el formulario y desde la
API REST sin duplicar código, y facilita el testing unitario.

### Dos controladores separados
- `EventController` devuelve render arrays de Drupal para páginas HTML
- `EventApiController` devuelve JsonResponse para peticiones AJAX

Esta separación respeta el contrato de Drupal y evita mezclar
respuestas HTML con JSON en el mismo controlador.

### Caché de países (24 horas)
La API restcountries.com devuelve ~250 países. Llamarla en cada
request sería ineficiente. Se usa la Cache API de Drupal con TTL
de 24 horas y fallback automático a lista reducida si la API falla.

### AJAX sin recargar la página
La inscripción usa `fetch()` nativo del navegador con el patrón
`Drupal.behaviors` para garantizar compatibilidad con BigPipe y
cargas AJAX de Drupal. `once()` previene duplicar event listeners.

### Tema hijo movilexito
Olivero usa la variable CSS `--max-width` para limitar el ancho
del contenido. El tema hijo sobrescribe esta variable a `100%`
para que el módulo ocupe todo el ancho disponible sin modificar
el core de Drupal.

### Identidad visual Móvil Éxito
Colores extraídos de movilexito.com:
- **Morado corporativo**: `#5B2D8E`
- **Amarillo acento**: `#FFD700`
- **Tipografía**: Nunito (sans-serif con personalidad similar
  a la tipografía usada por el Grupo Éxito)
- **Botones**: Bordes completamente redondeados (border-radius: 50px)
  siguiendo el estilo de los CTAs de la marca
