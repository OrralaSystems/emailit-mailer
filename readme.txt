=== Emailit API WordPress Plugin by Orrala Systems ===
Contributors: orralasystems
Tags: email, smtp, emailit, mail, wp_mail
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Reemplaza wp_mail() para enviar correos a través de la API de EmailIT con autenticación por API Key.

== Description ==

**Emailit API WordPress Plugin** es un plugin de nivel experto que reemplaza la función nativa `wp_mail()` de WordPress para enviar todos los correos electrónicos a través del servicio de EmailIT, mejorando significativamente la entregabilidad de sus emails.

= Características Principales =

* **Integración con EmailIT API** - Utiliza la API oficial de EmailIT con autenticación Bearer (API Key)
* **Reemplazo transparente de wp_mail()** - Todos los correos de WordPress se envían automáticamente a través de EmailIT
* **Panel de configuración completo** - Configure fácilmente su API Key, remitente, y opciones de logging
* **Forzar remitente** - Opción para forzar que todos los correos usen una dirección de remitente específica
* **Sistema de logs** - Registro de los últimos 100+ correos enviados con estado (enviado/fallido)
* **Email de prueba** - Herramienta integrada para verificar la configuración
* **Limpieza automática** - Los logs se limpian automáticamente según la configuración de retención
* **Estadísticas** - Visualice estadísticas de envío en el panel de administración

= Requisitos =

* WordPress 5.0 o superior
* PHP 7.4 o superior
* Cuenta activa en EmailIT con API Key

= Configuración =

1. Instale y active el plugin
2. Vaya a **Configuración → EmailIT Mailer**
3. Ingrese su API Key de EmailIT
4. Configure el email y nombre del remitente
5. Envíe un email de prueba para verificar la configuración

== Installation ==

1. Suba la carpeta `emailit-mailer` al directorio `/wp-content/plugins/`
2. Active el plugin a través del menú 'Plugins' en WordPress
3. Configure el plugin en **Configuración → EmailIT Mailer**

== Frequently Asked Questions ==

= ¿Dónde obtengo mi API Key de EmailIT? =

Puede obtener su API Key desde el panel de control de EmailIT en [emailit.com](https://emailit.com). Cree una nueva credencial de tipo "API" y copie la clave generada.

= ¿Qué pasa si no configuro el plugin? =

Si el plugin está activo pero no tiene una API Key configurada, los correos no se enviarán y se mostrará un error en el log.

= ¿Puedo ver los correos enviados? =

Sí, vaya a **Configuración → EmailIT Logs** para ver el historial de correos enviados con su estado.

= ¿Los adjuntos funcionan? =

Sí, el plugin soporta adjuntos de archivos. Los archivos se codifican en base64 y se envían a través de la API.

== Screenshots ==

1. Panel de configuración principal
2. Estadísticas de envío
3. Registro de correos enviados
4. Email de prueba exitoso

== Changelog ==

= 1.1.0 =
* Añadido toggle para habilitar/deshabilitar el plugin sin desactivarlo
* Cuando el plugin está deshabilitado, WordPress usa su método de envío nativo
* Mejoras en la interfaz de usuario del panel de configuración

= 1.0.0 =
* Versión inicial del plugin
* Integración completa con EmailIT API
* Panel de configuración con todas las opciones
* Sistema de logs con paginación
* Herramienta de email de prueba
* Limpieza automática de logs

== Upgrade Notice ==

= 1.0.0 =
Primera versión del plugin. ¡Disfrútelo!

== Credits ==

Desarrollado por [Orrala Systems](https://orralasystems.com)

Este plugin utiliza la [API de EmailIT](https://docs.emailit.com/) para el envío de correos electrónicos.
