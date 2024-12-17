# users_validtion versión 1.0.0 16-12-2024


#### **Descripción del script**
Este script ofrece una solución para validar usuarios de Zajuna LMS con una base de datos local. Asegura la sincronización de datos y actualiza los estados de los usuarios en función de los resultados de la validación.

El script consta de un **controlador** (`UsersValidationController`) que maneja la lógica de validación y un **modelo** (`usersModel`) que interactúa con la base de datos.

---

#### **Características**
- Valida usuarios según condiciones específicas:
  - `LMS_ESTADO = 2`
  - `OPERATION = 'U'`
  - `LMS_ID IS NOT NULL`
- Llama a la API de Moodle (Zajuna) para validar los detalles de los usuarios.
- Actualiza los estados de los usuarios en la base de datos basándose en los resultados de la validación:
  - Estado `P` para usuarios válidos.
  - Estado `6767` para usuarios con discrepancias.
- Registra los resultados de la validación para auditoría y depuración.

---

#### **Estructura de Archivos**
- **Controlador**
  - `UsersValidationController`: Gestiona el proceso de validación de usuarios e interactúa con la API de Zajuna.
- **Modelo**
  - `usersModel`: Administra las operaciones de la base de datos, como recuperar y actualizar registros de usuarios.

---

#### **Requisitos Previos**
1. **Variables de Entorno**:
   Configure las siguientes variables en su entorno:
   ```
   HOST_REPLICA: Host de la base de datos
   DB_REPLICA: Nombre de la base de datos
   PORT_REPLICA: Puerto de la base de datos
   USER_REPLICA: Usuario de la base de datos
   PASS_REPLICA: Contraseña de la base de datos
   WSTOKEN: Token de autenticación para la API de Zajuna
   API_ZAJUNA: URL base de la API de Zajuna
   ```

2. **Dependencias**:
   Instale las dependencias del proyecto utilizando Composer:
   ```bash
   composer install
   ```

---

#### **Uso del Script**
1. **Ejecución**:
   Ejecute el script desde la línea de comandos:
   ```bash
   php path/to/UsersValidationController.php
   ```

2. **Flujo del Script**:
   - Recupera los usuarios que cumplen con las condiciones especificadas desde la base de datos.
   - Llama a la API de Zajuna para validar la información de cada usuario.
   - Actualiza los estados de los usuarios en la base de datos según los resultados:
     - Estado `P` si los datos coinciden.
     - Estado `6767` si los datos no coinciden.
     - Estado `1` si el usuario no se encuentra en Zajuna.

3. **Mensajes de Consola**:
   El script genera mensajes en la consola para informar sobre el progreso y los resultados de la validación.

---

#### **Estructura del Código**
1. **Controlador**: `UsersValidationController`
   - **Métodos**:
     - `usersValidation`: Gestiona la validación de usuarios.
     - `requestToZajuna`: Realiza peticiones a la API de Zajuna.

2. **Modelo**: `usersModel`
   - **Métodos**:
     - `getusers`: Recupera los usuarios desde la base de datos según las condiciones especificadas.
     - `updateException`: Actualiza el estado de los usuarios en la base de datos.

---

#### **Registro de Logs**
Los eventos importantes (informativos, de advertencia o errores) se registran utilizando el modelo `logModel`. Los registros se almacenan en el sistema de logs definido por el proyecto.

---

#### **Notas Adicionales**
- Asegúrese de tener acceso a la base de datos y a la API de Zajuna.
- El script utiliza pausas (comentadas por defecto) para evitar sobrecargar la API. Ajuste según sea necesario.
- Este script está optimizado para PostgreSQL como base de datos.
