-- Seed/Schema inicial - Módulo Político + Demografia (PA)
-- Gerado em 2026-02-11 00:00:00
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Drops em ordem segura (dependências)
DROP TABLE IF EXISTS `demografia_municipio`;
DROP TABLE IF EXISTS `mandato_prefeito`;
DROP TABLE IF EXISTS `prefeito`;
DROP TABLE IF EXISTS `partido`;
DROP TABLE IF EXISTS `municipio`;
DROP TABLE IF EXISTS `regiao_integracao`;

-- ----------------------------
-- Table structure for regiao_integracao
-- ----------------------------
CREATE TABLE `regiao_integracao` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `legacy_id` bigint UNSIGNED NULL DEFAULT NULL,
  `descricao` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_regiao_descricao` (`descricao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for municipio
-- ----------------------------
CREATE TABLE `municipio` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `legacy_id` bigint UNSIGNED NULL DEFAULT NULL,
  `regiao_id` bigint UNSIGNED NULL DEFAULT NULL,
  `nome` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `uf` char(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PA',
  `codigo_ibge` char(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `codigo_tse` int NULL DEFAULT NULL,
  `codigo_sigplan` int NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_municipio_nome_uf` (`nome`,`uf`),
  UNIQUE KEY `uq_municipio_ibge_uf` (`codigo_ibge`,`uf`),
  UNIQUE KEY `uq_municipio_tse_uf` (`codigo_tse`,`uf`),
  KEY `idx_municipio_nome` (`nome`),
  KEY `idx_municipio_ibge` (`codigo_ibge`),
  KEY `idx_municipio_regiao` (`regiao_id`),
  CONSTRAINT `fk_municipio_regiao` FOREIGN KEY (`regiao_id`) REFERENCES `regiao_integracao` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for partido
-- ----------------------------
CREATE TABLE `partido` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `legacy_id` bigint UNSIGNED NULL DEFAULT NULL,
  `numero` smallint NULL DEFAULT NULL,
  `sigla` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_partido_sigla` (`sigla`),
  UNIQUE KEY `uq_partido_numero` (`numero`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for prefeito
-- ----------------------------
CREATE TABLE `prefeito` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `legacy_id` bigint UNSIGNED NULL DEFAULT NULL,
  `nome_completo` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome_urna` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `dt_nascimento` date NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_prefeito_nome` (`nome_completo`),
  KEY `idx_prefeito_urna` (`nome_urna`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for mandato_prefeito
-- ----------------------------
CREATE TABLE `mandato_prefeito` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `legacy_id` bigint UNSIGNED NULL DEFAULT NULL,
  `municipio_id` bigint UNSIGNED NOT NULL,
  `prefeito_id` bigint UNSIGNED NOT NULL,
  `partido_id` bigint UNSIGNED NULL DEFAULT NULL,
  `ano_eleicao` smallint NOT NULL,
  `cd_eleicao` int NOT NULL,
  `dt_eleicao` date NOT NULL,
  `nr_turno` tinyint NOT NULL,
  `nr_candidato` int NULL DEFAULT NULL,
  `mandato_inicio` date NOT NULL,
  `mandato_fim` date NOT NULL,
  `mandato_consecutivo` tinyint NULL DEFAULT NULL,
  `reeleito` tinyint(1) NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_mandato_eleicao` (`municipio_id`,`ano_eleicao`,`dt_eleicao`,`nr_turno`),
  KEY `idx_mandato_municipio` (`municipio_id`,`mandato_inicio`,`mandato_fim`),
  KEY `idx_mandato_prefeito` (`prefeito_id`,`mandato_inicio`),
  KEY `fk_mandato_partido` (`partido_id`),
  CONSTRAINT `fk_mandato_municipio` FOREIGN KEY (`municipio_id`) REFERENCES `municipio` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_mandato_partido` FOREIGN KEY (`partido_id`) REFERENCES `partido` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_mandato_prefeito` FOREIGN KEY (`prefeito_id`) REFERENCES `prefeito` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for demografia_municipio
-- ----------------------------
CREATE TABLE `demografia_municipio` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `municipio_id` bigint UNSIGNED NOT NULL,
  `ano_ref` smallint NOT NULL,
  `populacao` int NOT NULL,
  `eleitores` int NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_demografia_municipio_ano` (`municipio_id`,`ano_ref`),
  KEY `idx_demografia_ano` (`ano_ref`),
  CONSTRAINT `fk_demografia_municipio` FOREIGN KEY (`municipio_id`) REFERENCES `municipio` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

SET FOREIGN_KEY_CHECKS = 1;
