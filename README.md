# Sistema de Egresados — I.E. Dinamarca

## Guía de Instalación y Configuración

> **Nota para repositorio público:** este proyecto no debe publicarse con contraseñas reales,
> fotos de usuarios, bases oficiales de egresados ni archivos de configuración de producción.
> Use únicamente datos ficticios o plantillas.

---

## ESTRUCTURA DE ARCHIVOS

```
egresados/
├── index.php                   ← Login del sistema
├── registro.php                ← Registro de egresados
├── dashboard.php               ← Panel del egresado
├── perfil.php                  ← Perfil completo del egresado
├── logout.php                  ← Cierre de sesión
│
├── config/
│   ├── db.php                  ← Conexión a base de datos
│   └── funciones.php           ← Funciones compartidas
│
├── css/
│   └── egresados.css           ← Estilos del sistema
│
├── includes/
│   ├── header.php              ← Cabecera común
│   └── footer.php              ← Pie de página común
│
├── admin/                      ← Panel Rectoría
│   ├── index.php               ← Dashboard rectoría
│   ├── egresados.php           ← Gestión de egresados
│   ├── estadisticas.php        ← Estadísticas + exportar CSV
│   ├── cargar_base.php         ← Carga base oficial CSV
│   └── usuarios.php            ← Gestión de usuarios
│
├── comite/                     ← Panel Comité
│   ├── index.php               ← Dashboard comité
│   ├── egresados.php           ← Consulta y seguimiento
│   └── estadisticas.php        ← Estadísticas básicas
│
├── uploads/
│   └── fotos/                  ← Fotos de perfil (debe tener permisos de escritura)
│
└── egresados_schema.sql        ← Script de base de datos
```

---

## PASO 1 — BASE DE DATOS

1. Abra **phpMyAdmin** o su cliente MySQL.
2. Cree la base de datos ejecutando el archivo `egresados_schema.sql`:

   ```sql
   SOURCE /ruta/egresados_schema.sql;
   ```

   O impórtelo desde phpMyAdmin → Importar.

3. Esto crea:
   - Todas las tablas del sistema
   - 2 usuarios de prueba (rectoría y comité)
   - 5 egresados de prueba en la base oficial

---

## PASO 2 — CONFIGURACIÓN DE CONEXIÓN

Abra el archivo `config/db.php` y configure las credenciales de su entorno local o servidor:

```php
define('DB_HOST', 'localhost');      // Servidor MySQL
define('DB_NAME', 'dinamarca_egresados');  // Nombre de la base
define('DB_USER', 'usuario_local');  // Cambie por su usuario
define('DB_PASS', 'clave_local');    // Cambie por su contraseña
```

No publique credenciales reales en el repositorio.

---

## PASO 3 — SUBIR AL SERVIDOR

1. Suba la carpeta `egresados/` al servidor web dentro del directorio de la página actual.
   - Si la página está en `/public_html/`, suba a `/public_html/egresados/`
   - La URL resultante sería: `https://dinamarca.edu.co/egresados/`

2. Asegúrese de que la carpeta `uploads/fotos/` tenga **permisos de escritura (755 o 775)**:
   ```bash
   chmod 775 egresados/uploads/fotos/
   ```

---

## PASO 4 — INTEGRAR EN EL SITIO ACTUAL

Para agregar un enlace en el menú de la página actual, busque el archivo de navegación
del sitio (probablemente `index.php` o el menú principal) y agregue:

```html
<a href="/egresados/">Egresados</a>
```

---

## ACCESO INICIAL

El repositorio público no documenta contraseñas de acceso. Después de importar la base de
datos, cree usuarios administrativos para su entorno o actualice la contraseña de los
usuarios de prueba con un hash bcrypt generado localmente.

Ejemplo para generar un hash:

```bash
php -r "echo password_hash('CAMBIE_ESTA_CLAVE', PASSWORD_BCRYPT, ['cost' => 12]);"
```

Cambie cualquier usuario o contraseña de prueba antes de desplegar el sistema.

---

---

## CÓMO CARGAR LA BASE OFICIAL DE EGRESADOS

1. Ingrese como **Rectoría**.
2. Vaya a **Cargar Base** en el menú.
3. Prepare un archivo CSV con estas columnas:
   ```
   documento,nombres,apellidos,fecha_nacimiento,anno_graduacion
   1001234567,Juan Camilo,García Ríos,2000-03-15,2018
   ```
4. Suba el archivo. El sistema validará e importará los registros.

No suba archivos CSV reales ni información personal de egresados al repositorio público.

---

## ROLES Y PERMISOS

| Función                        | Rectoría | Comité | Egresado |
| ------------------------------ | -------- | ------ | -------- |
| Ver todos los egresados        | ✔        | ✔      | ✖        |
| Editar su propio perfil        | ✔        | ✔      | ✔        |
| Cambiar estado de perfiles     | ✔        | ✔\*    | ✖        |
| Eliminar usuarios              | ✔        | ✖      | ✖        |
| Ver estadísticas               | ✔        | ✔      | ✖        |
| Exportar CSV                   | ✔        | ✖      | ✖        |
| Cargar base oficial            | ✔        | ✖      | ✖        |
| Gestionar usuarios del sistema | ✔        | ✖      | ✖        |
| Agregar notas de seguimiento   | ✔        | ✔      | ✖        |

\*El comité puede cambiar a: pendiente, verificado, destacado (no puede poner inactivo).

---

## REQUISITOS DEL SERVIDOR

- PHP 7.4 o superior (recomendado PHP 8.x)
- MySQL 5.7 o superior / MariaDB 10.3+
- Extensión PDO_MySQL habilitada
- Extensión GD para manejo de imágenes (opcional)
- Mod_rewrite de Apache (opcional)

---

## SEGURIDAD

El sistema incluye:

- Protección CSRF en todos los formularios
- Contraseñas hasheadas con bcrypt (cost 12)
- Sesiones seguras con httponly
- Sanitización de todas las entradas
- Validación de identidad en 2 pasos para registro
- Log de todas las acciones administrativas
- Separación estricta de roles y permisos

---

## AUTOR

migue2509

## SOPORTE

Desarrollado para I.E. Dinamarca — Medellín, Colombia.
