# Plan de Auditoría de Seguridad

## Cuándo auditar

Ejecutar auditoría después de estos cambios:

| Disparador | Ejemplo |
|---|---|
| Nuevo componente | Agregar un servicio nuevo al stack |
| Nueva dependencia | Agregar paquete npm, imagen Docker, o librería |
| Cambio en autenticación | Modificar login, API keys, o sesiones |
| Exposición de APIs | Abrir un nuevo endpoint al exterior |
| Cambio en infraestructura | Modificar Docker, redes, puertos, o deploy |
| Antes de producción | Previo a migrar a VPS, dominio, o Tunnel |
| Periódico | Cada 2 semanas o al retomar el proyecto |

## Cómo auditar

Pedir en OpenCode: **"Ejecutá la auditoría de seguridad"**

Esto corre el skill `code-security-auditor` que:
1. Escanea dependencias, scripts, configs, y datos sensibles
2. Genera reporte en `docs/SecurityAudit-Report.md`
3. Clasifica hallazgos en 🔴 crítico, 🟠 sospechoso, 🟡 observación

## Post-auditoría

- Si hay **🔴 críticos**: no deployar hasta resolver
- Si hay **🟠 sospechosos**: evaluar si aplicar mitigación
- Si hay **🟡 observaciones**: documentar como deuda técnica
- Actualizar `docs/EstadoProyecto.md` con los hallazgos
- Los hallazgos resueltos se marcan como `✅` en el reporte

## Checklist pre-deploy

Antes de cualquier deploy a producción (VPS, Tunnel, dominio):

- [ ] Auditoría de seguridad ejecutada y sin 🔴
- [ ] Credenciales movidas a variables de entorno o secrets
- [ ] APIs internas con autenticación
- [ ] cookies.txt removido del repositorio
- [ ] HTTPS configurado
- [ ] Rate limiting en endpoints públicos
- [ ] Headers de seguridad: CSP, X-Frame-Options, CORS restringido
- [ ] Versiones de imágenes Docker pinneadas

## Relacionado

- [[SecurityAudit-Report]] — Último reporte generado
- [[README|Volver al inicio]]
