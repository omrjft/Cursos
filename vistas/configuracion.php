<?php
// Vista: Configuración del Sistema (Superadmin)
// Secciones simuladas: Mantenimiento, Categorías de Cursos, Copias de Seguridad.
?>
<section>
    <div style="margin-bottom: 24px;">
        <h2 style="font-size: 26px; font-weight: 900; color: var(--color-charcoal); letter-spacing: -0.01em;">Configuración del Sistema</h2>
        <p style="color: var(--color-pencil-gray); font-size: 15px; margin-top: 4px;">
            Ajustes globales de la plataforma, mantenimiento técnico y seguridad institucional.
        </p>
    </div>

    <div style="display: flex; flex-direction: column; gap: 24px; max-width: 900px;">

        <!-- 1. Sección: Mantenimiento -->
        <article class="summary-card" style="display: block; padding: 24px;">
            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 14px;">
                <div>
                    <h3 style="font-size: 18px; font-weight: 800; color: var(--color-charcoal); margin-bottom: 4px;">Modo de Mantenimiento</h3>
                    <p style="font-size: 14px; color: var(--color-pencil-gray);">
                        Activar el estado de mantenimiento suspenderá el acceso a alumnos y ponentes mientras se realizan actualizaciones.
                    </p>
                </div>
                <span style="font-size: 11px; font-weight: 800; text-transform: uppercase; background-color: var(--color-storybook-green); color: #2e7a00; padding: 4px 10px; border-radius: 999px;">
                    Operativo
                </span>
            </div>

            <div style="display: flex; align-items: center; justify-content: space-between; border-top: 1px solid var(--color-border-gray); padding-top: 16px; margin-top: 10px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 14px; font-weight: 700; color: var(--color-charcoal);">
                    <input type="checkbox" style="width: 18px; height: 18px; accent-color: var(--color-eager-green);">
                    Habilitar pantalla de mantenimiento
                </label>
                <button type="button" class="action-btn" onclick="alert('Estado de mantenimiento actualizado.');">
                    Guardar Cambios
                </button>
            </div>
        </article>

        <!-- 2. Sección: Categorías de Cursos -->
        <article class="summary-card" style="display: block; padding: 24px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                <div>
                    <h3 style="font-size: 18px; font-weight: 800; color: var(--color-charcoal); margin-bottom: 4px;">Categorías de Cursos</h3>
                    <p style="font-size: 14px; color: var(--color-pencil-gray);">
                        Administra las áreas temáticas en las que se clasifican las asignaturas de la plataforma.
                    </p>
                </div>
                <button type="button" class="welcome-btn" onclick="alert('Modal para agregar categoría.');" style="font-size: 12px; padding: 8px 14px;">
                    + Nueva Categoría
                </button>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Categoría</th>
                        <th>Cursos Activos</th>
                        <th>Estado</th>
                        <th style="text-align: right;">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="font-weight: 700;">Desarrollo Web y Software</td>
                        <td>12</td>
                        <td><span style="font-size: 11px; font-weight: 800; background-color: var(--color-storybook-green); color: #2e7a00; padding: 2px 8px; border-radius: 999px;">Activa</span></td>
                        <td style="text-align: right;"><button type="button" class="action-btn">Editar</button></td>
                    </tr>
                    <tr>
                        <td style="font-weight: 700;">Ciencia de Datos e Inteligencia Artificial</td>
                        <td>7</td>
                        <td><span style="font-size: 11px; font-weight: 800; background-color: var(--color-storybook-green); color: #2e7a00; padding: 2px 8px; border-radius: 999px;">Activa</span></td>
                        <td style="text-align: right;"><button type="button" class="action-btn">Editar</button></td>
                    </tr>
                    <tr>
                        <td style="font-weight: 700;">Bases de Datos y Arquitectura</td>
                        <td>5</td>
                        <td><span style="font-size: 11px; font-weight: 800; background-color: var(--color-storybook-green); color: #2e7a00; padding: 2px 8px; border-radius: 999px;">Activa</span></td>
                        <td style="text-align: right;"><button type="button" class="action-btn">Editar</button></td>
                    </tr>
                </tbody>
            </table>
        </article>

        <!-- 3. Sección: Copias de Seguridad (Backups) -->
        <article class="summary-card" style="display: block; padding: 24px;">
            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 14px;">
                <div>
                    <h3 style="font-size: 18px; font-weight: 800; color: var(--color-charcoal); margin-bottom: 4px;">Copias de Seguridad (Backups)</h3>
                    <p style="font-size: 14px; color: var(--color-pencil-gray);">
                        Generación y descarga de respaldos de la base de datos MySQL y ficheros de entrega.
                    </p>
                </div>
                <button type="button" class="action-btn" onclick="alert('Iniciando volcado completo de la base de datos...');" style="border-color: var(--color-spark-blue); color: var(--color-spark-blue);">
                    Crear Respaldo Ahora
                </button>
            </div>

            <div style="background-color: var(--color-bg-light); border: 1px solid var(--color-border-gray); border-radius: var(--radius-btn); padding: 14px 18px;">
                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 13px;">
                    <div>
                        <strong style="color: var(--color-charcoal);">backup_cursos_20261025.sql.gz</strong>
                        <div style="color: var(--color-pencil-gray);">Generado el 25/10/2026 a las 04:00 AM &bull; Tamaño: 24.8 MB</div>
                    </div>
                    <button type="button" class="action-btn" onclick="alert('Descargando copia de seguridad...');">
                        Descargar
                    </button>
                </div>
            </div>
        </article>

    </div>
</section>

