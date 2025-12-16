-- Tabela para funções administrativas (roles)
CREATE TABLE IF NOT EXISTS admin_roles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL COMMENT 'Nome da função',
  description TEXT NULL COMMENT 'Descrição da função',
  permissions JSON NOT NULL COMMENT 'Permissões em JSON',
  is_system TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Função do sistema (não pode ser excluída)',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_role_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adicionar coluna role_id na tabela admin_users (se não existir)
-- ALTER TABLE admin_users ADD COLUMN role_id BIGINT UNSIGNED NULL AFTER id;
-- ALTER TABLE admin_users ADD CONSTRAINT fk_admin_role FOREIGN KEY (role_id) REFERENCES admin_roles(id) ON DELETE SET NULL;

-- Função padrão: Super Admin (todas as permissões)
INSERT IGNORE INTO admin_roles (id, name, description, permissions, is_system) VALUES
(1, 'Super Admin', 'Acesso total ao sistema', '{"*": true}', 1);

