-- Tabela para feedbacks/testimonials
CREATE TABLE IF NOT EXISTS feedback_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  brand_image VARCHAR(255) NOT NULL COMMENT 'Imagem do logo da empresa',
  title VARCHAR(255) NOT NULL COMMENT 'Título do feedback',
  text TEXT NOT NULL COMMENT 'Texto do feedback',
  person_name VARCHAR(160) NOT NULL COMMENT 'Nome da pessoa',
  person_role VARCHAR(160) NOT NULL COMMENT 'Cargo/empresa da pessoa',
  person_image VARCHAR(255) NOT NULL COMMENT 'Foto da pessoa',
  sort_order INT NOT NULL DEFAULT 0,
  is_enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_feedback_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed inicial de feedbacks (baseado no index.html atual)
INSERT IGNORE INTO feedback_items (id, brand_image, title, text, person_name, person_role, person_image, sort_order, is_enabled)
VALUES
  (1, '/assets/img/feedback-brand-1.png', 'O melhor designer criativo recomendado.', 'O melhor serviço de hospedagem que já experimentei. Recomendo!', 'João da Silva', 'Digital Marketing Director', 'assets/img/user-img-1.png', 10, 1),
  (2, '/assets/img/feedback-brand-2.png', 'O melhor designer criativo recomendado.', 'O melhor serviço de hospedagem que já experimentei. Recomendo!', 'Lola Ross', 'Digital Marketing Director', 'assets/img/user-img-3.png', 20, 1),
  (3, '/assets/img/feedback-brand-3.png', 'O melhor designer criativo recomendado.', 'O melhor serviço de hospedagem que já experimentei. Recomendo!', 'Maria Oliveira', 'Digital Marketing Director', 'assets/img/user-img-4.png', 30, 1),
  (4, '/assets/img/feedback-brand-3.png', 'O melhor designer criativo recomendado.', 'O melhor serviço de hospedagem que já experimentei. Recomendo!', 'Lola Ross', 'Digital Marketing Director', 'assets/img/user-img-5.png', 40, 1);
