-- Corrigir encoding dos artigos do blog
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Atualizar artigo 3 (Streaming de Áudio)
UPDATE blog_posts SET 
  title = 'Streaming de Áudio com Sonic Panel: Solução Completa para Rádios Online',
  content = '<p>O <strong>Sonic Panel</strong> é uma das soluções mais completas para quem deseja criar e gerenciar uma estação de rádio online profissional.</p>
   
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
   <p>Nossos servidores são otimizados para streaming, com largura de banda generosa, baixa latência e suporte técnico especializado. Oferecemos instalação do Sonic Panel e configuração completa para sua rádio online!</p>'
WHERE id = 3;

-- Atualizar artigo 4 (VPS e VPS Gamer)
UPDATE blog_posts SET 
  title = 'VPS e VPS Gamer: Entenda as Diferenças e Escolha o Ideal',
  content = '<p>Servidores VPS (Virtual Private Server) são uma excelente opção intermediária entre hospedagem compartilhada e servidores dedicados. Mas quando se trata de jogos, o <strong>VPS Gamer</strong> oferece recursos específicos que fazem toda a diferença.</p>
   
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
   
   <p>Na GouTec, oferecemos ambos os tipos de VPS com configuração flexível para atender exatamente suas necessidades!</p>'
WHERE id = 4;

