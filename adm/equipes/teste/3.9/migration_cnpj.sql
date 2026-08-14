-- Adiciona a coluna cnpj em clientes_web_login
-- Execute este script no banco de dados antes de usar a nova funcionalidade

ALTER TABLE clientes_web_login
  ADD COLUMN cnpj VARCHAR(18) NULL DEFAULT NULL
  AFTER nome_cliente;
