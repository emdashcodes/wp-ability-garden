# Cloudflare Tunnel Setup

This project uses Cloudflare Tunnel to expose the local WordPress environment to the internet with a stable URL. This is required for Jetpack connection and external webhook testing.

**Public URL:** https://ability-garden.emdashcodes.dev

## Prerequisites

- Domain managed by Cloudflare DNS (`emdashcodes.dev`)
- `cloudflared` CLI installed (`brew install cloudflared`)

## Quick Start

### Start the tunnel

```bash
cloudflared tunnel run ability-garden
```

Or run in background:

```bash
cloudflared tunnel run ability-garden &
```

### Stop background tunnel

```bash
pkill -f "cloudflared tunnel run"
```

## Tunnel Details

| Property | Value |
|----------|-------|
| Tunnel Name | `ability-garden` |
| Tunnel ID | `31afc3d0-e333-4b95-8d08-f1e85088e7ad` |
| Hostname | `ability-garden.emdashcodes.dev` |
| Local Service | `http://localhost:8888` |

## Configuration

Config file: `~/.cloudflared/config.yml`

```yaml
tunnel: ability-garden
credentials-file: /Users/emdash/.cloudflared/31afc3d0-e333-4b95-8d08-f1e85088e7ad.json

ingress:
  - hostname: ability-garden.emdashcodes.dev
    service: http://localhost:8888
  - service: http_status:404
```

## Switching Between Local and Public URLs

### Use public URL (for Jetpack, webhooks)

```bash
npx wp-env run cli wp option update siteurl 'https://ability-garden.emdashcodes.dev'
npx wp-env run cli wp option update home 'https://ability-garden.emdashcodes.dev'
```

### Use local URL (faster development)

```bash
npx wp-env run cli wp option update siteurl 'http://localhost:8888'
npx wp-env run cli wp option update home 'http://localhost:8888'
```

## Install as System Service (Optional)

To have the tunnel start automatically on boot:

```bash
sudo cloudflared service install
sudo launchctl start com.cloudflare.cloudflared
```

To uninstall:

```bash
sudo cloudflared service uninstall
```

## Troubleshooting

### Check tunnel status

```bash
cloudflared tunnel info ability-garden
```

### List active connections

```bash
cloudflared tunnel list
```

### View logs

```bash
cloudflared tunnel run ability-garden --loglevel debug
```

## Initial Setup (One-time)

If recreating this tunnel from scratch:

```bash
# 1. Login to Cloudflare
cloudflared login

# 2. Create tunnel
cloudflared tunnel create ability-garden

# 3. Route DNS
cloudflared tunnel route dns ability-garden ability-garden.emdashcodes.dev

# 4. Create config (see Configuration section above)

# 5. Start tunnel
cloudflared tunnel run ability-garden
```
