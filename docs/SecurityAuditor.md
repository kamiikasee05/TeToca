# Code Security Auditor

Skill de OpenCode para auditoría de seguridad del proyecto.

## Instalación

Skill instalada en `.opencode/skills/code-security-auditor/`

## Qué analiza

- **Dependencias**: paquetes maliciosos, typosquatting, scripts en instalación
- **Código**: inyección de comandos, ejecución dinámica, payloads ofuscados
- **Filesystem**: accesos a rutas sensibles (`.ssh`, `.aws`, `/etc`)
- **Red**: IPs/dominios hardcodeados, exfiltración de datos
- **Ofuscación**: código minificado, anti-debugging, imports dinámicos

## Uso

Pedile a OpenCode: "Auditá la seguridad del proyecto" o "Ejecutá el code-security-auditor"

## Relacionado

- [[README|Volver al inicio]]
