# Guía de Instalación del Sistema DISTAL

El sistema cuenta con un script automatizado para facilitar su instalación en cualquier computadora con sistema operativo Windows.

## Requisitos Previos

Antes de ejecutar la instalación, asegúrese de cumplir con los siguientes requisitos en la computadora de destino:

1. **Servidor Local (XAMPP)**: Descargar e instalar XAMPP (incluye Apache, PHP y MySQL/MariaDB).
   - Se debe instalar en la ruta por defecto: `C:\xampp`
2. **Iniciar Servicios**: Abrir el "XAMPP Control Panel" y presionar el botón "Start" en los módulos de **Apache** y **MySQL**. Ambos deben estar marcados en color verde.

## Proceso de Instalación Paso a Paso

1. **Copiar la carpeta del sistema**: Copie toda la carpeta del proyecto (que contiene este archivo y el archivo `instalar.bat`) a la computadora donde desea instalar el sistema. No importa si está en el Escritorio, Documentos o una memoria USB, siempre y cuando la carpeta se mantenga completa.
2. **Ejecutar el instalador**: Dentro de la carpeta, ubique el archivo llamado `instalar.bat` y dé doble clic sobre él.
3. **Seguir las instrucciones en pantalla**:
   - El instalador abrirá una ventana negra (Consola de comandos).
   - Le pedirá que verifique que Apache y MySQL estén corriendo. Si es así, presione cualquier tecla para continuar.
   - El script automáticamente:
     - Creará una carpeta en `C:\xampp\htdocs\sistema_distal`.
     - Copiará todos los archivos necesarios del sistema a esa ruta.
     - Se conectará a MySQL y creará la base de datos `sistema_distal`.
     - Importará las tablas necesarias (empleados y prestaciones) automáticamente usando el archivo `sql/setup.sql`.
4. **Finalización**: Una vez terminado, el instalador le mostrará un mensaje de éxito. Ya puede cerrar la ventana.

## Acceder al Sistema

Después de una instalación exitosa, puede acceder al sistema en cualquier momento abriendo su navegador web de preferencia (Chrome, Firefox, Edge) e ingresando a la siguiente dirección URL:

`http://localhost/sistema_distal`

## Notas Adicionales y Solución de Problemas

- **Error de base de datos**: Si el script lanza un error al importar la base de datos, asegúrese de que el módulo **MySQL** en XAMPP esté corriendo y no tenga contraseña para el usuario `root` (la configuración por defecto de XAMPP es sin contraseña).
- **Error de ruta de XAMPP**: Si el script no encuentra XAMPP, es porque no fue instalado en `C:\xampp`. Si lo tiene en otra ubicación, deberá editar el archivo `instalar.bat` haciendo clic derecho > "Editar" y cambiar la línea que dice `set "XAMPP_DIR=C:\xampp"` por la ruta correcta.
