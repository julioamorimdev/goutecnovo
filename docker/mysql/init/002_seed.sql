SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Usuário admin padrão (senha será gerada no primeiro login se não existir)
-- Nota: por padrão criamos admin/admin123 (hash gerado em runtime pelo app se estiver vazio).
INSERT IGNORE INTO admin_users (id, username, password_hash, is_active)
VALUES (1, 'admin', '', 1);

-- Seed inicial do menu (baseado no partial atual). Ajuste/expanda via painel admin.
INSERT IGNORE INTO menu_items (id, parent_id, label, url, icon_class, description, badge_text, badge_class, dropdown_layout, custom_html, is_enabled, open_new_tab, sort_order)
VALUES
  (1, NULL, 'Início', 'index.html', NULL, NULL, NULL, NULL, 'default', NULL, 1, 0, 10),
  (2, NULL, 'Hospedagem', '#', NULL, NULL, NULL, NULL, 'xl', NULL, 1, 0, 20),
  (3, 2, 'WordPress', 'wp-hosting.html', 'lab la-wordpress fs-3 text-primary', 'Hospedagem WordPress otimizada para máxima performance e segurança do seu site.', 'Novo!', 'flex-shrink-0 badge bg-primary-subtle text-primary-emphasis fw-bold py-1', 'default', NULL, 1, 0, 10),
  (4, 2, 'Plesk', 'shared-hosting.html', 'las la-server fs-3 text-primary', 'Hospedagem com painel Plesk, ideal para gerenciar múltiplos sites com facilidade e segurança.', NULL, NULL, 'default', NULL, 1, 0, 20),
  (5, 2, 'cPanel', 'cpanel-hosting.html', 'las la-cog fs-3 text-primary', 'Hospedagem com cPanel, o painel de controle mais popular e completo do mercado.', NULL, NULL, 'default', NULL, 1, 0, 30),
  (6, 2, 'Cloud Hosting', 'cloud-hosting.html', 'las la-cloud fs-3 text-primary', 'Hospedagem em nuvem com alta disponibilidade, escalabilidade e performance superior.', NULL, NULL, 'default', NULL, 1, 0, 40),
  (7, 2, 'Banco de Dados', '#', 'las la-database fs-3 text-primary', 'Serviços de banco de dados gerenciados com alta performance e segurança para suas aplicações.', NULL, NULL, 'default', NULL, 1, 0, 50),
  (8, 2, 'WebRádio (SonicPanel)', '#', 'las la-broadcast-tower fs-3 text-primary', 'Hospedagem para web rádio com painel SonicPanel, ideal para transmissões de áudio online.', NULL, NULL, 'default', NULL, 1, 0, 60),
  (9, NULL, 'Revenda', '#', NULL, NULL, NULL, NULL, 'default', NULL, 1, 0, 30),
  (10, 9, 'Revenda cPanel', 'reseller-hosting.html', 'las la-store fs-3 text-primary', 'Revenda de hospedagem com cPanel, ideal para criar seu próprio negócio de hospedagem.', NULL, NULL, 'default', NULL, 1, 0, 10),
  (11, 9, 'Revenda Plesk', 'web-hosting.html', 'las la-server fs-3 text-primary', 'Revenda de hospedagem com Plesk, perfeita para revendedores que buscam flexibilidade.', NULL, NULL, 'default', NULL, 1, 0, 20),
  (12, NULL, 'E-mail', '#', NULL, NULL, NULL, NULL, 'default', NULL, 1, 0, 40),
  (13, 12, 'E-mail Profissional', 'email-hosting.html', 'las la-envelope fs-3 text-primary', 'E-mail corporativo profissional com seu próprio domínio e recursos avançados de segurança.', NULL, NULL, 'default', NULL, 1, 0, 10),
  (14, 12, 'E-mail Marketing', '#', 'las la-bullhorn fs-3 text-primary', 'Solução completa para campanhas de e-mail marketing com alta taxa de entrega.', NULL, NULL, 'default', NULL, 1, 0, 20),
  (15, NULL, 'Servidores', '#', NULL, NULL, NULL, NULL, 'default', NULL, 1, 0, 50),
  (16, 15, 'VPS', 'vps-server.html', 'las la-server fs-3 text-primary', 'Servidor Virtual Privado com recursos dedicados e total controle sobre o ambiente.', NULL, NULL, 'default', NULL, 1, 0, 10),
  (17, 15, 'VPS Gamer', 'game-server.html', 'las la-gamepad fs-3 text-primary', 'VPS otimizado para jogos com baixa latência e alta performance para servidores de jogos.', NULL, NULL, 'default', NULL, 1, 0, 20),
  (18, NULL, 'Domínio', '#', NULL, NULL, NULL, NULL, 'mega', NULL, 1, 0, 60),
  (19, 18, 'Buscar Nome de Domínio', 'domain-page.html', 'las la-search fs-3 text-primary', 'Verifique a disponibilidade do domínio desejado e encontre o nome perfeito para seu site.', NULL, NULL, 'default', NULL, 1, 0, 10),
  (20, 18, 'Transferir Domínio', 'transfer-domain.html', 'las la-random fs-3 text-primary', 'Transfira seu domínio para nossa plataforma de forma rápida e segura.', NULL, NULL, 'default', NULL, 1, 0, 20),
  (21, 18, 'Registro de Domínio', 'domain-registration.html', 'las la-globe fs-3 text-primary', 'Registre seu domínio com os melhores preços e extensões disponíveis.', NULL, NULL, 'default', NULL, 1, 0, 30),
  (22, 18, 'Pesquisar Transferência', 'transfer-domain-search.html', 'las la-check-circle fs-3 text-primary', 'Verifique se seu domínio está elegível para transferência.', NULL, NULL, 'default', NULL, 1, 0, 40),
  (23, NULL, 'Empresa', '#', NULL, NULL, NULL, NULL, 'default', NULL, 1, 0, 70),
  (24, 23, 'Sobre a Empresa', 'about-us.html', 'las la-info-circle fs-3 text-primary', 'Conheça mais sobre nossa empresa e nossa missão de oferecer os melhores serviços de hospedagem.', NULL, NULL, 'default', NULL, 1, 0, 10),
  (25, 23, 'Página de Contato', 'contact.html', 'las la-phone fs-3 text-primary', 'Entre em contato conosco para tirar suas dúvidas ou solicitar suporte.', NULL, NULL, 'default', NULL, 1, 0, 20),
  (26, 23, 'Blog', 'blog.html', 'las la-blog fs-3 text-primary', 'Acesse nosso blog com dicas, tutoriais e novidades sobre hospedagem e tecnologia.', NULL, NULL, 'default', NULL, 1, 0, 30),
  (27, 23, 'Listagem de Blog', 'blog-listing.html', 'las la-list fs-3 text-primary', 'Explore todos os artigos e posts do nosso blog em uma única página.', NULL, NULL, 'default', NULL, 1, 0, 40),
  (28, NULL, 'Recursos', '#', NULL, NULL, NULL, NULL, 'mega', NULL, 1, 0, 80),
  (29, 28, 'Proteção DDoS', 'ddos.html', 'las la-shield-alt fs-3 text-primary', 'Proteção avançada contra ataques DDoS para manter seu site sempre online e seguro.', NULL, NULL, 'default', NULL, 1, 0, 10),
  (30, 28, 'Sistema Operacional', 'operating-system.html', 'las la-desktop fs-3 text-primary', 'Escolha entre diferentes sistemas operacionais para seu servidor.', NULL, NULL, 'default', NULL, 1, 0, 20),
  (31, 28, 'Premium Network', 'premium-network.html', 'las la-network-wired fs-3 text-primary', 'Rede premium com alta performance e baixa latência para seus serviços.', NULL, NULL, 'default', NULL, 1, 0, 30),
  (32, 28, 'SSL', 'ssl-page.html', 'las la-lock fs-3 text-primary', 'Certificados SSL para proteger seu site e aumentar a confiança dos visitantes.', NULL, NULL, 'default', NULL, 1, 0, 40),
  (33, 28, 'Data Center', 'server-page.html', 'las la-server fs-3 text-primary', 'Infraestrutura de datacenter com alta disponibilidade e segurança.', NULL, NULL, 'default', NULL, 1, 0, 50),
  (34, 28, 'Painel de Controle', 'control-panel.html', 'las la-cog fs-3 text-primary', 'Gerencie seus serviços com um painel completo e fácil de usar.', NULL, NULL, 'default', NULL, 1, 0, 60),
  (35, 28, 'Suporte', 'support-page.html', 'las la-headset fs-3 text-primary', 'Suporte especializado para ajudar você quando precisar.', NULL, NULL, 'default', NULL, 1, 0, 70),
  (999, NULL, 'Área do Cliente', 'contact.html', NULL, NULL, NULL, NULL, 'default', '<li class=\"nav-item\"><a href=\"contact.html\" class=\"link btn btn-sm btn-dark hover:bg-dark hover:border-dark fw-medium rounded-pill\">Área do Cliente</a></li>', 1, 0, 999);


