#!/bin/bash

# Script para configurar SSL autoassinado para goutec.com.br
# Use este script apenas se não conseguir obter um certificado Let's Encrypt

set -e

DOMAIN="goutec.com.br"
NGINX_CONFIG="/etc/nginx/sites-available/goutec.com.br"
SSL_DIR="/etc/nginx/ssl/goutec.com.br"

echo "=========================================="
echo "Configurando SSL autoassinado para $DOMAIN"
echo "=========================================="

# Criar diretório para certificados
mkdir -p $SSL_DIR

# Gerar certificado autoassinado
echo ""
echo "Gerando certificado autoassinado..."
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout $SSL_DIR/privkey.pem \
    -out $SSL_DIR/fullchain.pem \
    -subj "/C=BR/ST=State/L=City/O=Goutec/CN=$DOMAIN"

# Definir permissões
chmod 600 $SSL_DIR/privkey.pem
chmod 644 $SSL_DIR/fullchain.pem

# Atualizar configuração do nginx para HTTPS
echo ""
echo "Atualizando configuração do nginx para HTTPS..."

cat > $NGINX_CONFIG << 'EOF'
# Configuração HTTPS para goutec.com.br (SSL autoassinado)

# Redirecionar HTTP para HTTPS
server {
    listen 80;
    listen [::]:80;
    server_name goutec.com.br www.goutec.com.br;

    # Redirecionar todo o tráfego para HTTPS
    location / {
        return 301 https://$server_name$request_uri;
    }
}

# Configuração HTTPS
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name goutec.com.br www.goutec.com.br;

    # Certificados SSL autoassinados
    ssl_certificate /etc/nginx/ssl/goutec.com.br/fullchain.pem;
    ssl_certificate_key /etc/nginx/ssl/goutec.com.br/privkey.pem;

    # Configurações SSL
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
    location / {
        proxy_pass http://localhost:8093;
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
    access_log /var/log/nginx/goutec.com.br.access.log;
    error_log /var/log/nginx/goutec.com.br.error.log;
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
echo "SSL autoassinado configurado com sucesso!"
echo "=========================================="
echo ""
echo "AVISO: Este é um certificado autoassinado."
echo "Os navegadores mostrarão um aviso de segurança."
echo "Para um certificado válido, use o script setup-ssl.sh"
echo ""

