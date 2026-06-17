# Easy!Appointments

> **⚠️ RETIRADO — Reemplazado por [[TuAhoraScheduler]] (15 Jun 2026)**
> Este componente fue eliminado del stack. El contenido se mantiene como referencia histórica.

Motor de reservas open source (PHP/MySQL) con API REST.

## Configuración

- Puerto: `8080`
- Base de datos: MySQL 8.0
- Imagen Docker: `alextselegidis/easyappointments`
- Sin `X-Frame-Options` para permitir iframe embed (Dockerfile custom)

## API

| Endpoint | Método | Descripción |
|---|---|---|
| `/index.php/api/v1/appointments` | GET | Listar turnos |
| `/index.php/api/v1/appointments/:id` | GET | Turno por ID |
| `/index.php/api/v1/appointments` | POST | Crear turno |
| `/index.php/api/v1/appointments/:id` | DELETE | Eliminar turno |
| `/index.php/api/v1/customers?q=` | GET | Buscar cliente por teléfono |
| `/index.php/api/v1/services` | GET | Listar servicios |
| `/index.php/api/v1/providers` | GET | Listar proveedores |

Autenticación: Basic Auth.

## Servicios (Salón de uñas)

| Servicio | Duración | Precio |
|---|---|---|
| Manicura simple | 45 min | $8.000 |
| Manicura semipermanente | 60 min | $12.000 |
| Pedicura simple | 60 min | $10.000 |
| Kapping | 90 min | $18.000 |
| Nail Art (diseño) | 30 min | $5.000 |
| Combo mani+pedi | 90 min | $16.000 |

## Relacionado

- [[README|Volver al inicio]]
- [[DockerCompose]] — Stack completo
