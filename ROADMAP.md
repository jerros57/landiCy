# Pendientes y límites v0.6.0

- El diseño es responsive en flujo, filas y columnas; no admite superposición libre de cualquier bloque por coordenadas absolutas.
- Tipografía y color generales son de bloque; no hay editor tipográfico de documentos completo ni anidación arbitraria ilimitada. Grupos heredados de tarjetas siguen siendo grupos; los patrones nuevos crean bloques independientes.
- Plantillas HTML de cabecera/pie conservan modo de código explícito; no existe conversión visual bidireccional perfecta de cualquier plantilla.
- Importaciones complejas pueden requerir HTML/CSS; no se promete edición gráfica del 100 % de una web ni compatibilidad de backends/plugins WordPress, módulos o CDN.
- JS original solo en vista aislada; ciertas API/redes/formularios/widgets se bloquean deliberadamente. La exportación usa runtime propio. Recursos incrustados aumentan tamaño de la vista interactiva.
- Recuperación por pestaña durante 24 horas e historial en memoria de 50 pasos; conflictos pausados requieren conciliación manual. Sin colaboración en tiempo real ni garantía frente a almacenamiento del navegador deshabilitado.
- Pruebas reales con Edge de escritorio y anchos móvil/tableta; ampliar validación táctil, lectores de pantalla, Safari/Firefox, muchos bloques y proyectos de gran tamaño.
- No hay promesa de equivalencia funcional completa con Google Sites o WordPress. No se modificaron herUX/UX Day ni se publicó en GitHub.
