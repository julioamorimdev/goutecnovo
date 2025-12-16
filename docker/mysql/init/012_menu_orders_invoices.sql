-- Adicionar itens de menu para Pedidos e Faturas (se não existirem)
INSERT IGNORE INTO menu_items (label, url, icon_class, sort_order, is_enabled, parent_id, open_new_tab)
VALUES
  ('Pedidos', '/admin/orders.php', 'las la-shopping-cart', 100, 1, NULL, 0),
  ('Faturas', '/admin/invoices.php', 'las la-file-invoice', 110, 1, NULL, 0);
