# Instrucciones UI: Mejoras al Perfil de Superadmin (`dashboard.php`)

El objetivo de esta fase es refinar la interfaz del Superadmin para que refleje privilegios de control global, eliminando elementos que se solapan con los roles de Docente o Alumno.

## 1. Menú de Navegación Lateral (Sidebar)
*   **Renombrar:** Cambiar "Mis Cursos" por "Gestión Global de Cursos".
*   **Separar Elementos:** Dividir la pestaña actual en dos enlaces distintos: 
    *   "Mi Perfil" (Datos personales).
    *   "Configuración del Sistema" (Ajustes globales de la plataforma).

## 2. Vista Dashboard 
*   **Llamado a la Acción (CTA):** Reemplazar el botón "Explorar Cursos ->"[cite: 14] por un menú o botones de accesos rápidos administrativos (ej. "+ Añadir Nuevo Usuario", "Ver Reportes Globales").

## 3. Vista Gestión de Usuarios 
*   **Acción Principal:** Añadir un botón visible de "+ Nuevo Usuario" en la parte superior para permitir el registro manual de personal.
*   **Filtros de Búsqueda:** Debido al alto volumen de usuarios (ej. 1,248)[cite: 15], añadir una estructura de filtros (formularios HTML inactivos):
    *   Barra de búsqueda por nombre/email.
    *   Selector (`<select>`) para filtrar por Rol.
    *   Selector (`<select>`) para filtrar por Estado (Activo/Inactivo).

## 4. Vista Gestión Global de Cursos 
*   **Reestructuración de Tabla:** Eliminar las métricas de docente ("Tareas pendientes", "Alumnos inscritos")[cite: 16, 17]. 
*   **Nuevas Columnas:** Mostrar Título, Docente Asignado, Estado (Borrador, Publicado, Suspendido), y Fecha de Creación.
*   **Nuevas Acciones:** Cambiar el botón "Ver detalle"[cite: 16, 17] por acciones de administración (ej. "Aprobar", "Suspender", "Reasignar Docente").

## 5. Vista Configuración del Sistema 
*   **Nueva Interfaz:** Crear una vista básica con secciones simuladas para opciones generales de la plataforma (ej. "Mantenimiento", "Categorías de Cursos", "Copias de Seguridad").