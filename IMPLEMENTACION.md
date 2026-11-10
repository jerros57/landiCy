# v0.6.3 — papelera de borradores

DraftTrash conserva un marcador privado por proyecto y bloqueos de operaciones; no elimina páginas, archivos o revisiones. El panel filtra activos/papelera. Nuevas acciones draft-trash/draft-restore solo para superadministrador, confirmadas por POST/CSRF/checkbox/token. Publicación y borrado coordinados mediante bloqueo por proyecto. Se rechazan proyectos con alguna versión publicada y accesos a proyectos en papelera. No se modifica el sitio original importado.

Límite: se borra de la lista un proyecto completo sin publicación, de forma recuperable; no se descartan cambios de una página publicada ni se vacía permanentemente la papelera. Ningún borrador real se borra como parte de instalar esta función.
