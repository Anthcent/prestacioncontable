# Guía de Ejecución Rápida del Sistema DISTAL

El sistema incluye un script automatizado (`iniciar.bat`) diseñado para levantar tanto el backend, el frontend y la base de datos con un solo clic. Ya no es necesario mover carpetas a `htdocs` ni encender servicios manualmente si solo deseas ejecutar y probar el sistema desde esta carpeta.

## Pasos para iniciar el sistema:

1. **Ubica el archivo**: En esta misma carpeta, busca el archivo llamado `iniciar.bat`.
2. **Ejecútalo**: Da doble clic sobre `iniciar.bat`.
3. **¿Qué sucede automáticamente?**:
   - **Base de Datos (MySQL)**: El script verificará si la base de datos de XAMPP está encendida. Si está apagada, la encenderá automáticamente en segundo plano.
   - **Backend/Frontend (PHP)**: Levantará un servidor web local dedicado para el sistema utilizando el núcleo de PHP, apuntando directamente a esta carpeta. Se abrirá una pequeña ventana minimizada llamada "Servidor PHP DISTAL".
   - **Navegador**: Finalmente, abrirá su navegador web predeterminado directamente en la dirección del sistema (`http://localhost:8000/`).

## ¿Cómo apagar el sistema?

Para detener el sistema por completo de forma segura:
1. Cierra la ventana negra principal que dice "SISTEMA EN EJECUCIÓN".
2. Cierra la segunda ventana negra (minimizada) que dice "Servidor PHP DISTAL".

## Solución de Problemas Frecuentes

- **"El puerto 8000 está en uso"**: Si al abrir el navegador la página no carga, es posible que el puerto 8000 esté ocupado. Edita el archivo `iniciar.bat` y cambia el número `8000` por `8080`.
- **"No se encontró XAMPP"**: El script requiere que XAMPP esté instalado en la ruta `C:\xampp` para poder utilizar el motor de base de datos MySQL y el intérprete de PHP.
