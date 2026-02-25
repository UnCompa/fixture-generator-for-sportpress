# Implementation Guide

## 🎯 Features del Sistema

### **1. Integración con Menú de SportsPress**

- Submenú "Fixture Generator" dentro del menú principal de SportsPress
- Acceso rápido desde el dashboard de administración
- Permisos basados en capacidades de SportsPress (`manage_sportpress`)

---

### **2. Gestión de Torneos (Tournaments)**

**Feature 2.1: Selector de Torneo**

- Dropdown para elegir torneos existentes de SportsPress
- Detección automática de grupos (league tables) dentro del torneo
- Visualización de estructura del torneo (grupos detectados)

**Feature 2.2: Detección de Equipos por Grupo**

- Leer automáticamente los equipos asignados a cada League Table del torneo
- Validar que todos los grupos tengan equipos asignados
- Alerta si hay grupos incompletos

**Feature 2.3: Configuración por Grupo del Torneo**

- Permitir diferentes algoritmos por cada grupo del torneo:
  - Grupo A: Round Robin Ida y Vuelta
  - Grupo B: Round Robin Solo Ida
  - Grupo C: Emparejamiento Aleatorio
- Configuración de fechas de inicio por grupo (o global)
- Días entre jornadas configurable por grupo

**Feature 2.4: Generación Masiva**

- Botón "Generar Fixtures de Todo el Torneo"
- Proceso en background para torneos grandes (AJAX con progress bar)
- Preview antes de confirmar la creación

---

### **3. Gestión Manual de Grupos (League Tables Individuales)**

**Feature 3.1: Acceso desde League Table**

- Botón "Generar Fixtures" dentro de la edición de cada League Table
- Detectar automáticamente equipos vinculados a la tabla

**Feature 3.2: Configuración Rápida**

- Selector de algoritmo:
  - **Ida** (Single Round Robin): Todos contra todos, una ronda
  - **Ida y Vuelta** (Double Round Robin): Dos rondas, invirtiendo localías
  - **Vuelta e Ida** (Double Round Robin alternativo): Empieza con visitantes
  - **Aleatorio** (Random): Emparejamientos aleatorios sin repetir
  - **Knockout**: Eliminación directa (para fases finales)
- Fecha de inicio del grupo
- Frecuencia de partidos (cada 7 días, cada 3 días, etc.)
- Horarios predeterminados (15:00, 18:00, 20:30)

**Feature 3.3: Opciones Avanzadas de Fútbol**

- **Evitar localía seguida**: Ningún equipo juega 2+ partidos seguidos de local/visitante
- **Equilibrar descanso**: Distribuir días de descanso equitativamente
- **Derbis separados**: Evitar que equipos de la misma ciudad jueguen la misma jornada (opcional)
- **Fechas FIFA bloqueadas**: Reservar fechas internacionales

---

### **4. Algoritmos de Generación**

**Feature 4.1: Round Robin (Circle Method)**

- Algoritmo matemático perfecto para ligas
- Soporte para número impar de equipos (añade "descanso")
- Rotación de equipos para distribución equitativa

**Feature 4.2: Random con Restricciones**

- Emparejamiento aleatorio pero válido (sin repeticiones)
- Opción de "semilla" para reproducir sorteos
- Validación de que todos jueguen contra todos (si aplica)

**Feature 4.3: Knockout (Eliminación)**

- Generación de brackets automática
- Cálculo de byes para potencias de 2
- Pre-visualización del cuadro

---

### **5. Sistema de Fechas y Horarios**

**Feature 5.1: Calendario Inteligente**

- Selección de fecha de inicio por grupo
- Días de la semana permitidos (solo sábados y domingos, etc.)
- Exclusión de fechas específicas (feriados, eventos especiales)

**Feature 5.2: Múltiples Horarios**

- Asignar franjas horarias por jornada
- Rotación de horarios para variedad
- Horarios fijos por equipo (equipo X siempre juega domingos 18:00)

**Feature 5.3: Asignación de Canchas**

- Asignar venue/cancha automáticamente según equipo local
- Detección de conflictos de cancha (doble uso)

---

### **6. Preview y Validación**

**Feature 6.1: Vista Previa de Fixtures**

- Tabla visual antes de crear eventos
- Mostrar jornadas, enfrentamientos, fechas
- Indicadores de problemas (descanso insuficiente, etc.)

**Feature 6.2: Validaciones**

- Número mínimo de equipos (2 para knockout, 3 para round robin)
- Equipos duplicados detectados
- Fechas inválidas
- Conflictos con fixtures existentes

**Feature 6.3: Comparador de Fixtures**

- Comparar nueva generación con fixtures existentes
- Detectar superposiciones
- Opción de reemplazar o mantener existentes

---

### **7. Creación de Eventos en SportsPress**

**Feature 7.1: Integración Nativa**

- Crear posts tipo `sp_event` automáticamente
- Asignar metadatos correctos:
  - `sp_league` (liga del torneo)
  - `sp_season` (temporada)
  - `sp_team` (array de equipos)
  - `sp_date`, `sp_time`
  - `sp_venue` (cancha)

**Feature 7.2: Estado de Eventos**

- Crear como "Draft" para revisión manual
- Opción de "Publicar inmediatamente"
- Programar publicación automática

**Feature 7.3: Títulos Automáticos**

- Formato configurable: "Equipo A vs Equipo B - Jornada X"
- Incluir nombre del grupo: "Grupo A: Equipo A vs Equipo B"

---

### **8. Gestión Post-Generación**

## Phase 4: Advanced Features (Seeded Playoffs & Scheduling) ✅

- [x] **Seeded Playoff Algorithm**: 1 vs 8, 2 vs 7, etc., based on table standings.
- [x] **Rotation of times**: Multiple match times per day.
- [x] **Date Exclusions**: Blacklist specific dates.
- [x] **Custom Round Prefixes**: Define "Jornada", "Ronda", "Fecha", etc.
- [x] **Shuffle Teams**: Option to randomize order for Round Robin.
- [x] **Automatic Venue Assignment**: Linked to Home team.
- [x] **Auto-generation by League/Season**: Context-aware team selection.

**Feature 8.3: Exportación/Importación**

- Exportar fixtures a CSV/Excel
- Importar fixtures desde archivo (para edición externa)
- Plantillas de importación

---

### **9. Fases de Torneo Avanzadas**

**Feature 9.1: Fase de Grupos → Eliminación**

- Configurar cuántos pasan de cada grupo (1os, 2os, mejores terceros)
- Generar automáticamente bracket de eliminación con clasificados
- Pre-visualización del cuadro completo

**Feature 9.2: Tercer Lugar**

- Opción de partido por 3er lugar en knockout
- Configuración de final y semifinales

**Feature 9.3: Repesca/Repechaje**

- Configurar partidos de repesca entre grupos
- Mejores terceros vs segundos, etc.

---

### **10. Configuración Global y Herramientas**

**Feature 10.1: Ajustes del Plugin**

- Formato de fecha por defecto
- Zona horaria predeterminada
- Permisos de usuario (quién puede generar)

**Feature 10.2: Log de Actividad**

- Registro de quién generó qué y cuándo
- Posibilidad de rollback a generación anterior

**Feature 10.3: Duplicador de Torneos**

- Clonar estructura de torneo con nuevos equipos
- Útil para temporadas consecutivas

---

## 📋 Resumen de User Flows

| Escenario            | Flujo                                                                                                                                           |
| -------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------- |
| **Torneo existente** | SportsPress → Fixture Generator → Seleccionar Torneo → Detectar Grupos → Configurar algoritmo por grupo → Generar Todo → Preview → Crear Events |
| **Grupo individual** | SportsPress → League Tables → Editar Grupo → Botón "Generar Fixtures" → Configurar → Preview → Crear Events                                     |
| **Fase final**       | Torneo generado → Seleccionar clasificados → Configurar bracket → Generar Knockout → Unir con fase de grupos                                    |
