# Configuração SSL para goutec.com.br

Este documento explica como configurar SSL para o domínio goutec.com.br que aponta para o projeto goutecnovo na porta 8093.

## Pré-requisitos

1. O domínio `goutec.com.br` deve estar apontando para o IP deste servidor
2. O nginx deve estar instalado e funcionando
3. O certbot deve estar instalado (já está instalado)
4. O projeto goutecnovo deve estar rodando na porta 8093

## Opção 1: Certificado Let's Encrypt (Recomendado)

O certificado Let's Encrypt é gratuito, válido e renovado automaticamente.

### Passos:

1. **Verificar se o domínio está apontando para o servidor:**
   ```bash
   dig +short goutec.com.br
   curl ifconfig.me
   ```
   Os IPs devem ser iguais.

2. **Executar o script de configuração:**
   ```bash
   cd /var/www/goutecnovo
   sudo ./setup-ssl.sh
   ```

3. **O script irá:**
   - Obter um certificado SSL válido do Let's Encrypt
   - Configurar o nginx para HTTPS
   - Configurar redirecionamento HTTP → HTTPS
   - Configurar headers de segurança

4. **Renovação automática:**
   A renovação automática já está configurada via `certbot.timer` que roda duas vezes por dia.
   
   Para testar a renovação manualmente:
   ```bash
   sudo certbot renew --dry-run
   ```

## Opção 2: Certificado Autoassinado

Use apenas se não conseguir obter um certificado Let's Encrypt (ex: domínio não está apontando corretamente).

**AVISO:** Certificados autoassinados mostrarão um aviso de segurança nos navegadores.

### Passos:

1. **Executar o script de certificado autoassinado:**
   ```bash
   cd /var/www/goutecnovo
   sudo ./setup-ssl-selfsigned.sh
   ```

2. **Renovação manual:**
   Como é autoassinado, você precisará renovar manualmente antes de expirar (365 dias):
   ```bash
   sudo ./setup-ssl-selfsigned.sh
   ```

## Verificação

Após configurar, verifique:

1. **Acesse o site:**
   - HTTP: http://goutec.com.br (deve redirecionar para HTTPS)
   - HTTPS: https://goutec.com.br

2. **Verificar certificado:**
   ```bash
   openssl s_client -connect goutec.com.br:443 -servername goutec.com.br
   ```

3. **Verificar logs do nginx:**
   ```bash
   tail -f /var/log/nginx/goutec.com.br.access.log
   tail -f /var/log/nginx/goutec.com.br.error.log
   ```

## Estrutura de Arquivos

- Configuração do nginx: `/etc/nginx/sites-available/goutec.com.br`
- Certificados Let's Encrypt: `/etc/letsencrypt/live/goutec.com.br/`
- Certificados autoassinados: `/etc/nginx/ssl/goutec.com.br/`
- Logs: `/var/log/nginx/goutec.com.br.*.log`

## Troubleshooting

### Erro: "Domain not pointing to this server"
- Verifique se o DNS do domínio está apontando corretamente
- Aguarde a propagação do DNS (pode levar até 48 horas)

### Erro: "Port 80 is already in use"
- Verifique se há outro serviço usando a porta 80
- Pare temporariamente outros serviços se necessário

### Certificado não renova automaticamente
- Verifique o status do timer: `systemctl status certbot.timer`
- Teste renovação manual: `certbot renew --dry-run`

### Nginx não recarrega
- Verifique a sintaxe: `nginx -t`
- Verifique os logs: `journalctl -u nginx -n 50`

## Suporte

Para mais informações sobre certbot:
- Documentação: https://certbot.eff.org/
- Comandos úteis: `certbot --help`

