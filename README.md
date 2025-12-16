## GouTecNovo (Dev)

Este repositório agora inclui um ambiente de desenvolvimento com:

- **PHP 8.3 (Apache)**
- **MySQL (8.4)**
- **phpMyAdmin**
- **Painel Admin** (login) para gerenciar o menu do site (dropdowns, ícones, habilitar/desabilitar, etc.)

### Subir o ambiente

Na raiz do projeto:

```bash
docker compose up -d --build
```

### Acessos

- **Site (Apache/PHP)**: `http://localhost:8080`
- **Site (Apache/PHP)**: `http://localhost:8093`
- **phpMyAdmin**: `http://localhost:8094`
  - Host: `db`
  - User: `goutecnovo`
  - Pass: `goutecnovo_pass`
  - DB: `goutecnovo`
- **Painel Admin**: `http://localhost:8093/admin/login.php`
  - Usuário: `admin`
  - Senha: `admin123` (ajuste via env `ADMIN_DEFAULT_PASS` no `docker-compose.yml`)

### Menu dinâmico (partials)

O menu dinâmico está em `partials/header.php` e lê os itens do banco (`menu_items`).

> Observação: as páginas atuais ainda são `.html`. Para usar o header dinâmico em uma página, ela precisa ser servida via PHP (por exemplo `index.php` incluindo `partials/header.php`), ou a inclusão pode ser feita via JS (próximo passo).


