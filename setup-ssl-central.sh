#!/bin/bash

# Script para configurar SSL com certbot para central.goutec.com.br

set -e

DOMAIN="central.goutec.com.br"
EMAIL="admin@goutec.com.br"
NGINX_CONFIG="/etc/nginx/sites-available/central.goutec.com.br"

echo "=========================================="
echo "Configurando SSL para $DOMAIN"
echo "=========================================="

# Verificar se o domínio está apontando para este servidor
echo "Verificando se o domínio está apontando para este servidor..."
DOMAIN_IP=$(dig +short $DOMAIN | tail -n1)
SERVER_IP=$(curl -s ifconfig.me || curl -s ipinfo.io/ip)

echo "IP do domínio: $DOMAIN_IP"
echo "IP do servidor: $SERVER_IP"

if [ "$DOMAIN_IP" != "$SERVER_IP" ]; then
    echo "AVISO: O domínio pode não estar apontando para este servidor!"
    echo "Continuando mesmo assim..."
fi

# Obter certificado SSL com certbot
echo ""
echo "Obtendo certificado SSL com certbot..."
certbot certonly --nginx \
    -d $DOMAIN \
    --non-interactive \
    --agree-tos \
    --email $EMAIL \
    --keep-until-expiring

# Atualizar configuração do nginx para HTTPS
echo ""
echo "Atualizando configuração do nginx para HTTPS..."

cat > $NGINX_CONFIG << 'EOF'
# Configuração HTTPS para central.goutec.com.br

# Redirecionar HTTP para HTTPS
server {
    listen 80;
    listen [::]:80;
    server_name central.goutec.com.br;

    # Permitir acesso ao .well-known para renovação do certificado
    location ~ /.well-known/acme-challenge {
        allow all;
        root /var/www/html;
    }

    # Redirecionar todo o resto para HTTPS
    location / {
        return 301 https://$server_name$request_uri;
    }
}

# Configuração HTTPS
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name central.goutec.com.br;

    # Certificados SSL
    ssl_certificate /etc/letsencrypt/live/central.goutec.com.br/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/central.goutec.com.br/privkey.pem;

    # Configurações SSL modernas
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers 'ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384';
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;
    ssl_session_tickets off;

    # Headers de segurança
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Proxy reverso para o container Docker na porta 8093
    # Apontando para o diretório /central
    location / {
        proxy_pass http://localhost:8093/central/;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Port $server_port;
        
        # Timeouts
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
        
        # Buffer settings
        proxy_buffering on;
        proxy_buffer_size 4k;
        proxy_buffers 8 4k;
        proxy_busy_buffers_size 8k;
    }

    # Logs
    access_log /var/log/nginx/central.goutec.com.br.access.log;
    error_log /var/log/nginx/central.goutec.com.br.error.log;
}
EOF

# Testar configuração do nginx
echo ""
echo "Testando configuração do nginx..."
nginx -t

# Recarregar nginx
echo ""
echo "Recarregando nginx..."
systemctl reload nginx

echo ""
echo "=========================================="
echo "SSL configurado com sucesso!"
echo "=========================================="
echo ""
echo "Certificado SSL instalado em:"
echo "  /etc/letsencrypt/live/central.goutec.com.br/"
echo ""
echo "A renovação automática está configurada via cron."
echo "Para testar a renovação manualmente, execute:"
echo "  certbot renew --dry-run"
echo ""

