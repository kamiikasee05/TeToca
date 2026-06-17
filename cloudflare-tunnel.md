# Cloudflare Tunnel — Puesta en Produccion

## 1. Registrar dominio .com.ar

### Requisitos
- Titular con DNI argentino (persona fisica o juridica)
- Un proveedor NIC-habilitado: Dattatec, DonWeb, Neo, etc.

### Pasos
1. Verificar disponibilidad en https://nic.ar
2. Comprar el dominio con el proveedor elegido (ej: `tuahora.com.ar`)
3. El costo aproximado es $3.000-$5.000 ARS/anual (Junio 2026)
4. Configurar los DNS del proveedor para apuntar a Cloudflare (paso 2)

---

## 2. Configurar Cloudflare como DNS

1. Crear cuenta gratuita en https://dash.cloudflare.com
2. Agregar el dominio (`tuahora.com.ar`)
3. Cloudflare escanea los registros DNS existentes
4. Reemplazar los nameservers del proveedor por los de Cloudflare:
   - `dave.ns.cloudflare.com`
   - `julissa.ns.cloudflare.com`
   (los nombres reales aparecen en el dashboard al agregar el dominio)
5. Esperar la propagacion (2-48 horas, tipicamente < 1 hora)
6. Verificar en el dashboard que el dominio pase a "Active"

---

## 3. Instalar cloudflared

### En Windows (PC de desarrollo)
```powershell
# Descargar el binario
curl.exe -L -o cloudflared.exe https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe

# Mover a una carpeta en PATH
Move-Item -LiteralPath .\cloudflared.exe -Destination "C:\tools\cloudflared.exe"

# Verificar
& "C:\tools\cloudflared.exe" --version
```

### En Linux (VPS o miniserver)
```bash
curl -L https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64.deb -o cloudflared.deb
sudo dpkg -i cloudflared.deb
cloudflared --version
```

---

## 4. Autenticar y crear el Tunnel

```bash
# Autenticar con Cloudflare (abre el navegador)
cloudflared tunnel login

# Crear el tunnel (reemplazar por nombre deseado)
cloudflared tunnel create tuahora-prod
```

Esto genera un archivo JSON con la credencial del tunnel en
`~/.cloudflared/<tunnel-id>.json`.

---

## 5. Configurar DNS

```bash
# booking.tudominio.com -> localhost:8080 (Easy!Appointments)
cloudflared tunnel route dns tuahora-prod booking.tuahora.com.ar

# admin.tudominio.com -> localhost:5678 (n8n)
cloudflared tunnel route dns tuahora-prod admin.tuahora.com.ar
```

---

## 6. Archivo de configuracion (config.yml)

Crear `C:\Users\tu-usuario\.cloudflared\config.yml`:

```yaml
tunnel: <tunnel-id>
credentials-file: C:\Users\tu-usuario\.cloudflared\<tunnel-id>.json

ingress:
  # Easy!Appointments - booking publico
  - hostname: booking.tuahora.com.ar
    service: http://localhost:8080
    originRequest:
      noTLSVerify: true

  # n8n - admin
  - hostname: admin.tuahora.com.ar
    service: http://localhost:5678
    originRequest:
      noTLSVerify: true

  # Rechazar todo lo demas
  - service: http_status:404
```

---

## 7. Iniciar el Tunnel

### Como servicio de Windows (recomendado)
```powershell
cloudflared service install
net start cloudflared
```

### Manual (para pruebas)
```powershell
cloudflared tunnel run tuahora-prod
```

### Como contenedor Docker (alternativa para VPS)
```yaml
services:
  cloudflared:
    image: cloudflare/cloudflared:latest
    container_name: cloudflared
    restart: unless-stopped
    command: tunnel --no-autoupdate run --token <tunnel-token>
    # O usando archivo de configuracion:
    # command: tunnel --config /etc/cloudflared/config.yml run
    volumes:
      - ./cloudflared:/etc/cloudflared
```

---

## 8. Forzar HTTPS

En Cloudflare Dashboard -> dominio -> SSL/TLS:

1. **SSL/TLS encryption mode**: "Full (strict)"
2. **Always Use HTTPS**: ON
3. **Automatic HTTPS Rewrites**: ON
4. **Minimum TLS Version**: 1.2

En la seccion **Edge Certificates**:
- **Always Use HTTPS**: activado

Con `Full (strict)`, Cloudflare cifra el trafico entre el cliente y el edge,
y tambien entre el edge y tu servidor (aunque el origen sea HTTP plano,
Cloudflare confia en el tunnel porque sabe que es tuyo).

---

## 9. Reglas WAF basicas

### 9.1 Bloquear trafico no Argentino

En Cloudflare Dashboard -> Security -> WAF -> Custom rules:

**Rule 1 - Bloquear fuera de Argentina**
- Field: `IP Country`
- Operator: `is not in`
- Value: `AR`
- Action: `Block`

**Opcional: permitir solo Argentina + algunos paises vecinos**
- Value: `AR, CL, UY, BR, PY, BO`

### 9.2 Rate limiting

**Rule 2 - Rate limit por IP**
- Field: `IP Source Address`
- Max requests: `100`
- Time window: `60 seconds`
- Action: `Block for 10 minutes`
- Path: `/index.php/api/`

Esto protege la API contra ataques de fuerza bruta.

**Rule 3 - Rate limit booking page**
- Field: `IP Source Address`
- Max requests: `30`
- Time window: `60 seconds`
- Action: `Block for 5 minutes`
- Path: `/index.php/appointments/`

---

## 10. Verificacion final

```powershell
# Verificar que el tunnel responde
curl.exe -I https://booking.tuahora.com.ar
# Deberia responder 200 OK o 302

curl.exe -I https://admin.tuahora.com.ar
# Deberia responder 200 OK o 302

# Verificar que http redirige a https
curl.exe -I http://booking.tuahora.com.ar
# Deberia responder 301/302 a https
```

---

## 11. Costos asociados

| Concepto | Costo |
|---|---|
| Dominio .com.ar (anual) | ~$3.000-$5.000 ARS |
| Cloudflare (plan gratis) | $0 USD |
| Tunnel (sin limite de ancho de banda) | $0 USD |
| VPS (ej: Hetzner CX22) | ~6 EUR/mes |
| **Total mensual estimado** | **~6-8 EUR + dominio** |

---

## Notas importantes

- cloudflared NO necesita abrir puertos en el router/firewall
- El tunnel sostiene una conexion WebSocket saliente hacia Cloudflare
- Si la PC de desarrollo se apaga, el tunnel cae (usar VPS para produccion real)
- Para produccion real, se recomienda ejecutar cloudflared como servicio de Windows o en un VPS con systemd
- Los certificados SSL son administrados automaticamente por Cloudflare
