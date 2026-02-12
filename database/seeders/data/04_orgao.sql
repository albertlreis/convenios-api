SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

INSERT INTO `orgao` (`id`, `sigla`, `nome`, `codigo_sigplan`) VALUES
  (1, 'SESPA', 'Secretaria de Estado de Saúde Pública', 2),
  (2, 'SEOP', 'Secretaria de Estado de Obras Públicas', 7),
  (3, 'SEINFRA', 'Secretaria de Estado de Infraestrutura e Logística', 53),
  (4, 'SEDUC', 'Secretaria de Estado de Educação', 20);

  SET FOREIGN_KEY_CHECKS = 1;