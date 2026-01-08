# 📧 Emailit API WordPress Plugin

<p align="center">
  <img src="https://img.shields.io/badge/version-1.1.0-blue.svg" alt="Version 1.1.0">
  <img src="https://img.shields.io/badge/WordPress-5.0%2B-green.svg" alt="WordPress 5.0+">
  <img src="https://img.shields.io/badge/PHP-7.4%2B-purple.svg" alt="PHP 7.4+">
  <img src="https://img.shields.io/badge/License-GPL--2.0-orange.svg" alt="License GPL-2.0">
</p>

<p align="center">
  <strong>Plugin de WordPress de nivel experto que reemplaza wp_mail() para enviar correos a través de la API de EmailIT</strong>
</p>

<p align="center">
  Desarrollado con ❤️ por <a href="https://orralasystems.com">Orrala Systems</a>
</p>

---

## ✨ Características

- 🔄 **Reemplazo automático de wp_mail()** - Todos los correos de WordPress se envían a través de EmailIT
- 🔑 **Autenticación Bearer** - Conexión segura usando API Key
- ⚙️ **Panel de configuración completo** - Configura fácilmente desde el admin de WordPress
- 📊 **Sistema de logs** - Registro de los últimos 100+ correos con estado
- 🧪 **Email de prueba** - Verifica la configuración con un clic
- 🔛 **Toggle on/off** - Habilita/deshabilita sin desactivar el plugin
- 📎 **Soporte para adjuntos** - Envía archivos adjuntos sin problemas
- 🛡️ **Seguro** - Nonces, sanitización y validación en todo el código

---

## 📋 Requisitos

| Requisito | Versión |
|-----------|---------|
| WordPress | 5.0+ |
| PHP | 7.4+ |
| Cuenta EmailIT | Activa con API Key |

---

## 🚀 Instalación

### Método Manual

1. Descarga o clona este repositorio
2. Copia la carpeta `emailit-mailer` a `/wp-content/plugins/`
3. Activa el plugin en **Plugins → Plugins instalados**
4. Configura en **Configuración → EmailIT Mailer**

### Desde GitHub

```bash
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/OrralaSystem/emailit-mailer.git
```

---

## ⚙️ Configuración

### 1. Obtener API Key

1. Inicia sesión en [EmailIT](https://emailit.com)
2. Ve al panel de credenciales
3. Crea una nueva credencial de tipo "API"
4. Copia la API Key generada

### 2. Configurar el Plugin

1. Ve a **Configuración → EmailIT Mailer**
2. Habilita el plugin con el toggle "Estado del Plugin"
3. Ingresa tu API Key
4. Configura el email y nombre del remitente
5. Opcionalmente configura Reply-To
6. Guarda los cambios

### 3. Verificar Configuración

1. En la sección "Enviar Email de Prueba"
2. Ingresa un email de destino
3. Haz clic en "Enviar Email de Prueba"
4. Verifica que llegue el correo

---

## 📁 Estructura del Plugin

```
emailit-mailer/
├── 📄 emailit-mailer.php        # Archivo principal
├── 📁 includes/
│   ├── class-emailit-settings.php   # Gestión de configuraciones
│   ├── class-emailit-api.php        # Cliente de la API
│   ├── class-emailit-logger.php     # Sistema de logs
│   ├── class-emailit-mailer.php     # Reemplazo de wp_mail
│   └── class-emailit-admin.php      # Páginas de admin
├── 📁 assets/
│   ├── 📁 css/
│   │   └── admin.css                # Estilos del admin
│   └── 📁 js/
│       └── admin.js                 # JavaScript del admin
├── 📄 uninstall.php                 # Limpieza al desinstalar
└── 📄 readme.txt                    # Documentación WordPress
```

---

## 🔧 Opciones de Configuración

| Opción | Descripción |
|--------|-------------|
| **Habilitar Plugin** | Toggle para activar/desactivar el envío vía EmailIT |
| **API Key** | Clave de autenticación de EmailIT |
| **Email Remitente** | Dirección desde la cual se envían los correos |
| **Nombre Remitente** | Nombre que aparece como remitente |
| **Forzar Remitente** | Ignora el remitente de otros plugins |
| **Reply-To** | Dirección para respuestas |
| **Habilitar Logs** | Activa el registro de correos |
| **Días de Retención** | Tiempo que se conservan los logs |
| **Máximo Entradas** | Límite de logs a almacenar |

---

## 📊 Panel de Logs

El plugin incluye un panel de logs accesible desde **Configuración → EmailIT Logs** donde puedes:

- ✅ Ver los últimos correos enviados
- ❌ Identificar envíos fallidos
- 🔍 Filtrar por estado (enviado/fallido)
- 🔎 Buscar por email o asunto
- 🗑️ Limpiar logs manualmente

---

## 🔒 Seguridad

El plugin implementa las mejores prácticas de seguridad de WordPress:

- **Sanitización**: Todas las entradas se sanitizan con funciones de WordPress
- **Escapado**: Todas las salidas se escapan apropiadamente
- **Nonces**: Verificación en formularios y peticiones AJAX
- **Capabilities**: Solo usuarios con `manage_options` pueden configurar
- **Prepared Statements**: Uso de `$wpdb->prepare()` en todas las consultas

---

## 📝 Changelog

### 1.1.0
- ➕ Añadido toggle para habilitar/deshabilitar el plugin
- 🔄 Cuando está deshabilitado, WordPress usa su método nativo
- 🎨 Mejoras en la interfaz de usuario

### 1.0.0
- 🎉 Versión inicial
- 📧 Integración con EmailIT API
- ⚙️ Panel de configuración completo
- 📊 Sistema de logs
- 🧪 Email de prueba

---

## 🤝 Contribuir

Las contribuciones son bienvenidas. Por favor:

1. Haz fork del repositorio
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

---

## 📄 Licencia

Este proyecto está bajo la Licencia GPL-2.0. Ver el archivo [LICENSE](LICENSE) para más detalles.

---

## 🔗 Enlaces

- [EmailIT](https://emailit.com) - Servicio de envío de correos
- [Documentación API](https://docs.emailit.com) - Documentación de la API
- [Orrala Systems](https://orralasystems.com) - Desarrolladores del plugin

---

<p align="center">
  <strong>Emailit API WordPress Plugin</strong><br>
  © 2025 Orrala Systems. Todos los derechos reservados.
</p>
