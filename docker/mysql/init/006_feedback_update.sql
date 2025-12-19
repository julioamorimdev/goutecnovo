-- Atualização: Adicionar novos feedbacks reais
-- Execute este script para adicionar os novos depoimentos

-- Garantir UTF-8
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Inserir novos feedbacks (usando INSERT IGNORE para evitar duplicatas)
INSERT IGNORE INTO feedback_items (id, brand_image, title, text, person_name, person_role, person_image, sort_order, is_enabled)
VALUES
  (5, '/assets/img/feedback-brand-1.png', 'Empresa ótima para quem está querendo abrir um mecanismo complexo', 'Empresa ótima para quem está querendo abrir um mecanismo complexo, além do suporte e atenção que os mesmos tem ao cliente, simplesmente serviço deles é impecável, você acompanha de perto a operação e a execução de tudo que você pediu para fazerem, Recomendo demais!', 'Kauã Skierzynski', 'Cliente', 'assets/img/user-img-1.png', 50, 1),
  
  (6, '/assets/img/feedback-brand-2.png', 'A Goutec superou nossas expectativas', 'A Goutec superou nossas expectativas ao entregar nosso Front End promocional. A programação realizada para interligação do nosso banco de dados à geração de e-mails automáticos, com regras pré-definidas, funcionaram de maneira impecável. Parabéns por cumprir os prazos de forma exemplar! 👏🕒 Super recomendo!', 'Jéssica Galdino', 'Ecommercializando', 'assets/img/user-img-3.png', 60, 1),
  
  (7, '/assets/img/feedback-brand-3.png', 'Os serviços da Goutec foram incríveis', 'Os serviços da Goutec foram incríveis! Sua solução de distribuição de cupons da sorte para a campanha de 9 anos da Exclusiva Colchões foi brilhante e eficaz. Parabéns! 🚀👏', 'Exclusiva Colchões', 'Cliente', 'assets/img/user-img-4.png', 70, 1),
  
  (8, '/assets/img/feedback-brand-1.png', 'A Goutec demonstrou competência técnica e profissionalismo', 'A Goutec demonstrou não apenas competência técnica, mas também um forte compromisso com a ética e o profissionalismo durante todo o nosso projeto. Sua abordagem transparente e diligente é um exemplo a ser seguido na indústria de desenvolvimento de software. 🎯', 'MaxLar', 'Cliente', 'assets/img/user-img-5.png', 80, 1),
  
  (9, '/assets/img/feedback-brand-2.png', 'Excelente profissional, atendimento especializado', 'Excelente profissional, atendimento especializado, desenvolvimento de sistemas com IA.', 'Colombo Engenharia', 'Cliente', 'assets/img/user-img-1.png', 90, 1);

