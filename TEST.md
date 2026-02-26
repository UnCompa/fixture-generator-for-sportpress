# Plan de Pruebas - Fixture Generator for SportsPress

Este documento detalla los escenarios de prueba para validar todas las funcionalidades del plugin.

---

## 1. Navegación e Interfaz Principal

### Escenario 1.1: Acceso al Generador Avanzado

- **Acción**: Ir a `SportsPress > Fixture Generator`.
- **Resultado Esperado**: Se debe mostrar el selector de torneos con el mensaje "-- Choose a Tournament --".
- **Verificación**: El botón "Generate All Fixtures" y el de "Create New Group" deben estar ocultos hasta seleccionar un torneo.

### Escenario 1.2: Selección de Torneo

- **Acción**: Seleccionar un torneo existente del dropdown.
- **Resultado Esperado**: Se deben cargar dinámicamente las tarjetas de los grupos (si existen) y mostrarse los botones de acción global al final.

---

## 2. Gestión de Grupos (Desde Torneos)

### Escenario 2.1: Crear Primer Grupo

- **Acción**: En la página principal del plugin, seleccionar un torneo y hacer clic en "Create New Group".
- **Resultado Esperado**: Debe aparecer un formulario. Ingresar nombre "Grupo A", seleccionar 4 equipos y guardar.
- **Verificación**: Debe aparecer una nueva tarjeta en la cuadrícula con los equipos seleccionados.

### Escenario 2.2: Intentar crear grupo sin equipos

- **Acción**: Intentar guardar un grupo sin marcar ningún equipo.
- **Resultado Esperado**: El sistema debe impedir la creación o mostrar una alerta de que se requieren equipos.

---

## 3. Generador Rápido (Desde League Tables)

### Escenario 3.1: Acceso desde Edición de Tabla

- **Acción**: Ir a `SportsPress > League Tables`, editar una tabla. Ver el metabox "Quick Fixture Generator".
- **Resultado Esperado**: El metabox debe mostrar el botón principal de generación.
- **Verificación**: Al hacer clic, debe abrirse el Modal con todas las opciones (Algoritmo, Fecha, Intervalo, etc.).

### Escenario 3.2: Botón de Envío y Cancelación

- **Acción**: Abrir el modal, hacer clic en la "X" o en el botón "Cancel".
- **Resultado Esperado**: El modal debe cerrarse y no realizar ninguna acción.

---

## 4. Algoritmos de Generación

### Escenario 4.1: Round Robin (Ida y Vuelta)

- **Acción**: En un grupo de 4 equipos, elegir "Round Robin (Ida y Vuelta)".
- **Resultado Esperado**: El sistema debe generar 6 jornadas (3 ida + 3 vuelta).
- **Verificación**: Verificar en el registro (debug.log) o en el metabox "Associated Events" que existan los 12 partidos totales.

### Escenario 4.2: Round Robin (Solo Ida)

- **Acción**: Usar "Round Robin (Solo Ida)".
- **Resultado Esperado**: Debe generar 3 jornadas para un grupo de 4.

### Escenario 4.3: Playoffs - Seeded (Top 4/8)

- **Acción**: Elegir "Playoffs - Single Elimination" en una tabla con resultados previos.
- **Resultado Esperado**: El sistema debe emparejar al 1º vs 4º y 2º vs 3º automáticamente.
- **Verificación**: Los títulos de los eventos creados deben reflejar el prefijo configurado (ej: "Cuartos 1").

---

## 5. Configuración Avanzada (Advanced Settings)

### Escenario 5.1: Días Permitidos

- **Acción**: Marcar solo "Sábado (S)" y "Domingo (S)". Establecer intervalo de 1 día.
- **Resultado Esperado**: Si el partido 1 es el Sábado, el partido 2 debe ser el Domingo, y el partido 3 debe saltar hasta el siguiente Sábado.

### Escenario 5.2: Rotación de Horarios

- **Acción**: Ingresar "14:00, 16:00, 18:00" en el campo de horarios.
- **Resultado Esperado**: En una misma jornada con 3 partidos, el primero debe ser a las 14:00, el segundo a las 16:00 y el tercero a las 18:00.

### Escenario 5.3: Exclusión de Fechas

- **Acción**: Poner una fecha específica (YYYY-MM-DD) en el campo "Exclude Dates" que coincida con el calendario.
- **Resultado Esperado**: El sistema debe saltar esa fecha y programar el partido el siguiente día permitido.

---

## 6. Casos de Borde y Errores

### Escenario 6.1: Número Impar de Equipos (Descansos)

- **Acción**: Generar Fixtures para un grupo con 3 o 5 equipos.
- **Resultado Esperado**: El sistema debe generar las jornadas correctamente.
- **Verificación**: En el `debug.log` debe aparecer el mensaje "Team X rests in [Jornada]".

### Escenario 6.2: Teams Metadata Faltante

- **Acción**: Intentar generar fixtures en una tabla que no tiene equipos asignados en la base de datos.
- **Resultado Esperado**: El sistema debe devolver un error "No teams found in this group".

### Escenario 6.3: Balance de Localía

- **Acción**: Generar con y sin el checkbox "Balance Localía".
- **Resultado Esperado**: Con el checkbox, los equipos deben alternar entre jugar en casa y fuera.

---

## 7. Persistencia y Logs

### Escenario 7.1: Historial de Generación

- **Acción**: Después de generar, revisar el metabox "Fixture Generation History" en la parte inferior.
- **Resultado Esperado**: Debe aparecer una nueva fila con la fecha, el algoritmo usado y el número de eventos creados.
- **Verificación**: Al hacer clic en los IDs de los eventos (#...), deben abrirse correctamente en una pestaña nueva.
