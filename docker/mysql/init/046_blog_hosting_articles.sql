-- Apagar todos os artigos existentes
DELETE FROM blog_posts;

-- Resetar o auto_increment
ALTER TABLE blog_posts AUTO_INCREMENT = 1;

-- Garantir UTF-8
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET CHARACTER SET utf8mb4;
SET character_set_connection=utf8mb4;

-- Inserir 4 novos artigos sobre hosting
INSERT INTO blog_posts (id, image, title, author, published_date, url, content, is_featured, sort_order, is_enabled)
VALUES
  (1, 'assets/img/blog-1.png', 'Guia Completo: Hospedagem cPanel vs Plesk - Qual Escolher?', 'Equipe GouTec', CURDATE(), '/central/blog.php?id=1', 
   '<p>Quando se trata de escolher um painel de controle para sua hospedagem, duas opções se destacam no mercado: <strong>cPanel</strong> e <strong>Plesk</strong>. Ambos são líderes em suas respectivas áreas, mas atendem a necessidades diferentes.</p>
   
   <h3>O que é cPanel?</h3>
   <p>O cPanel é o painel de controle mais popular para servidores Linux. Com uma interface intuitiva e amigável, ele permite gerenciar facilmente:</p>
   <ul>
     <li>Domínios e subdomínios</li>
     <li>Bancos de dados MySQL</li>
     <li>Contas de e-mail</li>
     <li>Arquivos via File Manager</li>
     <li>Instalação de aplicativos (WordPress, Joomla, etc.)</li>
   </ul>
   
   <h3>O que é Plesk?</h3>
   <p>O Plesk é um painel de controle multiplataforma que funciona tanto em Linux quanto em Windows. É especialmente valorizado por:</p>
   <ul>
     <li>Suporte nativo a .NET e ASP.NET</li>
     <li>Gerenciamento de múltiplos servidores</li>
     <li>Interface moderna e responsiva</li>
     <li>Ferramentas avançadas de segurança</li>
   </ul>
   
   <h3>Qual Escolher?</h3>
   <p>Escolha <strong>cPanel</strong> se você precisa de uma solução Linux tradicional, amplamente suportada e com vasta documentação. Escolha <strong>Plesk</strong> se você trabalha com tecnologias Microsoft ou precisa de um painel multiplataforma.</p>
   
   <p>Na GouTec, oferecemos ambos os painéis para que você escolha a melhor opção para seu projeto!</p>', 
   1, 10, 1),

  (2, 'assets/img/blog-2.png', 'Hospedagem de Revenda: Como Começar seu Próprio Negócio de Hosting', 'Equipe GouTec', CURDATE(), '/central/blog.php?id=2',
   '<p>A <strong>hospedagem de revenda</strong> é uma excelente oportunidade para empreendedores que desejam entrar no mercado de hospedagem sem grandes investimentos em infraestrutura.</p>
   
   <h3>O que é Hospedagem de Revenda?</h3>
   <p>É um modelo de negócio onde você adquire recursos de hospedagem de um provedor maior e os revende para seus próprios clientes, criando sua própria marca e estabelecendo seus preços.</p>
   
   <h3>Vantagens da Revenda</h3>
   <ul>
     <li><strong>Baixo investimento inicial:</strong> Não precisa comprar servidores</li>
     <li><strong>Suporte técnico incluído:</strong> O provedor cuida da infraestrutura</li>
     <li><strong>Escalabilidade:</strong> Aumente recursos conforme sua base de clientes cresce</li>
     <li><strong>Painel WHM:</strong> Controle total sobre as contas de seus clientes</li>
     <li><strong>Marca própria:</strong> Revenda com sua identidade visual</li>
   </ul>
   
   <h3>Como Começar</h3>
   <ol>
     <li>Escolha um plano de revenda adequado ao seu público</li>
     <li>Configure sua marca e identidade visual</li>
     <li>Estabeleça seus preços e pacotes</li>
     <li>Configure o painel WHM para gerenciar clientes</li>
     <li>Desenvolva estratégias de marketing e vendas</li>
   </ol>
   
   <p>A GouTec oferece planos de revenda completos com WHM/cPanel, suporte técnico 24/7 e recursos ilimitados para você começar seu negócio hoje mesmo!</p>',
   0, 20, 1),

  (3, 'assets/img/blog-3.png', 'Streaming de Áudio com Sonic Panel: Solução Completa para Rádios Online', 'Equipe GouTec', CURDATE(), '/central/blog.php?id=3',
   '<p>O <strong>Sonic Panel</strong> é uma das soluções mais completas para quem deseja criar e gerenciar uma estação de rádio online profissional.</p>
   
   <h3>O que é Sonic Panel?</h3>
   <p>É um painel de controle desenvolvido especificamente para streaming de áudio, permitindo gerenciar playlists, programação, DJs e muito mais de forma intuitiva e profissional.</p>
   
   <h3>Principais Funcionalidades</h3>
   <ul>
     <li><strong>Gerenciamento de Playlists:</strong> Organize sua música de forma eficiente</li>
     <li><strong>Programação Automática:</strong> Configure horários e sequências de reprodução</li>
     <li><strong>Painel para DJs:</strong> Interface dedicada para apresentadores</li>
     <li><strong>Estatísticas em Tempo Real:</strong> Acompanhe ouvintes e reproduções</li>
     <li><strong>Integração com Shoutcast/Icecast:</strong> Compatível com os principais servidores de streaming</li>
   </ul>
   
   <h3>Requisitos de Hospedagem</h3>
   <p>Para uma rádio online funcionar perfeitamente, você precisa de:</p>
   <ul>
     <li>Servidor dedicado ou VPS com recursos adequados</li>
     <li>Largura de banda suficiente para o número de ouvintes</li>
     <li>Baixa latência para transmissão em tempo real</li>
     <li>Suporte a Shoutcast ou Icecast</li>
   </ul>
   
   <h3>Por que Escolher a GouTec?</h3>
   <p>Nossos servidores são otimizados para streaming, com largura de banda generosa, baixa latência e suporte técnico especializado. Oferecemos instalação do Sonic Panel e configuração completa para sua rádio online!</p>',
   0, 30, 1),

  (4, 'assets/img/blog-4.png', 'VPS e VPS Gamer: Entenda as Diferenças e Escolha o Ideal', 'Equipe GouTec', CURDATE(), '/central/blog.php?id=4',
   '<p>Servidores VPS (Virtual Private Server) são uma excelente opção intermediária entre hospedagem compartilhada e servidores dedicados. Mas quando se trata de jogos, o <strong>VPS Gamer</strong> oferece recursos específicos que fazem toda a diferença.</p>
   
   <h3>O que é um VPS Tradicional?</h3>
   <p>Um VPS é um servidor virtualizado que oferece recursos dedicados (CPU, RAM, disco) dentro de um servidor físico compartilhado. É ideal para:</p>
   <ul>
     <li>Aplicações que precisam de mais recursos</li>
     <li>Controle total do ambiente (root access)</li>
     <li>Instalação de software personalizado</li>
     <li>Projetos que precisam de escalabilidade</li>
   </ul>
   
   <h3>O que é um VPS Gamer?</h3>
   <p>Um VPS Gamer é otimizado especificamente para servidores de jogos, oferecendo:</p>
   <ul>
     <li><strong>CPU de alta performance:</strong> Processadores dedicados para jogos</li>
     <li><strong>SSD NVMe:</strong> Armazenamento ultra-rápido para carregamento rápido</li>
     <li><strong>Baixa latência:</strong> Conexão otimizada para reduzir ping</li>
     <li><strong>DDoS Protection:</strong> Proteção contra ataques comuns em servidores de jogos</li>
     <li><strong>Localização estratégica:</strong> Datacenters próximos aos jogadores</li>
   </ul>
   
   <h3>Qual Escolher?</h3>
   <p><strong>Escolha VPS tradicional</strong> se você precisa de um servidor para aplicações web, desenvolvimento, ou projetos que não são relacionados a jogos.</p>
   <p><strong>Escolha VPS Gamer</strong> se você vai hospedar servidores de Minecraft, Counter-Strike, Team Fortress 2, ou qualquer outro jogo que exija baixa latência e alta performance.</p>
   
   <h3>Jogos Suportados</h3>
   <p>Nossos VPS Gamers suportam os principais jogos: Minecraft, CS:GO, CS2, Team Fortress 2, Rust, ARK, Valheim, e muitos outros!</p>
   
   <p>Na GouTec, oferecemos ambos os tipos de VPS com configuração flexível para atender exatamente suas necessidades!</p>',
   0, 40, 1);

