-- Estrutura para tabela `anuncios_canais`
DROP TABLE IF EXISTS `anuncios_canais`;
CREATE TABLE `anuncios_canais` (
  `id_anuncio_canal` varchar(30) NOT NULL,
  `canal_venda` varchar(50) NOT NULL,
  `id_anuncio_pai` varchar(20) DEFAULT NULL,
  `sku_produto` varchar(100) DEFAULT NULL,
  `titulo_anuncio` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_categoria` varchar(20) DEFAULT NULL,
  `categoria_anuncio` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `logistic_type` varchar(50) DEFAULT NULL,
  `data_atualizacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
-- Estrutura para tabela `estoque_diario`
DROP TABLE IF EXISTS `estoque_diario`;
CREATE TABLE `estoque_diario` (
  `id` int(11) NOT NULL,
  `data_snapshot` date NOT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `estoque_full_ml` int(11) DEFAULT '0',
  `estoque_geral_tiny` int(11) DEFAULT '0',
  `estoque_direct_tiny` int(11) DEFAULT '0',
  `estoque_total` int(11) GENERATED ALWAYS AS ((`estoque_full_ml` + `estoque_geral_tiny`)) STORED
-- Estrutura para tabela `log_despesas_erp`
DROP TABLE IF EXISTS `log_despesas_erp`;
CREATE TABLE `log_despesas_erp` (
  `id_lancamento_tiny` bigint(20) NOT NULL,
  `data_vencimento` date DEFAULT NULL,
  `valor` decimal(12,2) DEFAULT '0.00',
  `categoria_mapeada` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `historico` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fornecedor` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_insercao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
-- Estrutura para tabela `log_recebiveis_erp`
DROP TABLE IF EXISTS `log_recebiveis_erp`;
CREATE TABLE `log_recebiveis_erp` (
  `id` int(11) UNSIGNED NOT NULL,
  `id_lancamento_tiny` int(11) UNSIGNED NOT NULL,
  `data_recebimento` date DEFAULT NULL,
  `valor_recebido` decimal(10,2) DEFAULT NULL,
  `historico` text,
  `cliente` varchar(255) DEFAULT NULL
-- Estrutura para tabela `mapa_produtos_anuncios`
DROP TABLE IF EXISTS `mapa_produtos_anuncios`;
CREATE TABLE `mapa_produtos_anuncios` (
  `id` int(11) NOT NULL,
  `id_produto_tiny` int(11) NOT NULL,
  `sku` varchar(100) NOT NULL,
  `categoria` varchar(150) DEFAULT NULL,
  `ean` varchar(20) DEFAULT NULL,
  `titulo_produto` text,
  `id_anuncio_canal` varchar(50) NOT NULL,
  `id_anuncio_pai` varchar(50) DEFAULT NULL,
  `plataforma` varchar(50) NOT NULL DEFAULT 'meli_lare',
  `logistic_type` varchar(50) DEFAULT NULL,
  `data_atualizacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
-- Estrutura para tabela `posicionamento_anuncios`
DROP TABLE IF EXISTS `posicionamento_anuncios`;
CREATE TABLE `posicionamento_anuncios` (
  `id` int(11) UNSIGNED NOT NULL,
  `data_verificacao` date NOT NULL,
  `palavra_chave` varchar(255) NOT NULL,
  `id_anuncio_pai` varchar(50) NOT NULL,
  `posicao` int(11) DEFAULT NULL,
  `pagina` int(11) DEFAULT NULL
-- Estrutura para tabela `produtos_catalogo`
DROP TABLE IF EXISTS `produtos_catalogo`;
CREATE TABLE `produtos_catalogo` (
  `sku` varchar(100) NOT NULL,
  `id_produto_tiny` int(11) DEFAULT NULL,
  `nome_produto` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ean` varchar(20) DEFAULT NULL,
  `tipo_produto` varchar(20) DEFAULT NULL COMMENT 'Simples, Pai, Kit',
  `sku_pai` varchar(100) DEFAULT NULL,
  `custo_produto` decimal(10,2) DEFAULT '0.00',
  `data_atualizacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
-- Estrutura para tabela `relatorio_diario`
DROP TABLE IF EXISTS `relatorio_diario`;
CREATE TABLE `relatorio_diario` (
  `id` bigint(20) NOT NULL,
  `data_relatorio` date NOT NULL,
  `id_anuncio` varchar(20) NOT NULL,
  `id_anuncio_variacao` varchar(50) DEFAULT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `titulo_anuncio` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `categoria_anuncio` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `impressoes_ads` int(11) DEFAULT '0',
  `cliques_ads` int(11) DEFAULT '0',
  `custo_ads` decimal(10,2) DEFAULT '0.00',
  `vendas_ads_qtd` int(11) DEFAULT '0',
  `faturamento_ads` decimal(12,2) DEFAULT '0.00',
  `vendas_organicas_qtd` int(11) GENERATED ALWAYS AS ((`vendas_totais_qtd` - `vendas_ads_qtd`)) STORED,
  `visitas_totais` int(11) DEFAULT '0',
  `visitas_organicas_reais` int(11) GENERATED ALWAYS AS ((`visitas_totais` - `cliques_ads`)) STORED,
  `vendas_totais_qtd` int(11) DEFAULT '0',
  `faturamento_total` decimal(12,2) DEFAULT '0.00',
  `tarifa_venda_total` decimal(12,2) DEFAULT '0.00',
  `custo_frete_total` decimal(12,2) DEFAULT '0.00',
  `liquido_recebido_total` decimal(12,2) DEFAULT '0.00',
  `custo_produto_total` decimal(12,2) DEFAULT '0.00'
-- Estrutura para tabela `tarefas_pendentes_catalogo`
DROP TABLE IF EXISTS `tarefas_pendentes_catalogo`;
CREATE TABLE `tarefas_pendentes_catalogo` (
  `id` int(11) NOT NULL,
  `id_produto_tiny` bigint(20) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pendente',
  `data_processamento` datetime DEFAULT NULL,
  `mensagem_erro` text,
  `data_criacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP
-- Estrutura para tabela `tarefas_pendentes_estoque`
DROP TABLE IF EXISTS `tarefas_pendentes_estoque`;
CREATE TABLE `tarefas_pendentes_estoque` (
  `id` int(11) NOT NULL,
  `sku` varchar(100) NOT NULL,
  `id_produto_tiny` int(11) DEFAULT NULL,
  `data_snapshot` date DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pendente',
  `data_processamento` datetime DEFAULT NULL,
  `mensagem_erro` text,
  `data_criacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP
-- Estrutura para tabela `tarefas_pendentes_trafego`
DROP TABLE IF EXISTS `tarefas_pendentes_trafego`;
CREATE TABLE `tarefas_pendentes_trafego` (
  `id` int(11) NOT NULL,
  `id_anuncio` varchar(20) NOT NULL,
  `data_metrica` date NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pendente',
  `data_processamento` datetime DEFAULT NULL,
  `mensagem_erro` text,
  `data_criacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP
-- Estrutura para tabela `tendencias_mercado`
DROP TABLE IF EXISTS `tendencias_mercado`;
CREATE TABLE `tendencias_mercado` (
  `id` int(11) UNSIGNED NOT NULL,
  `data_verificacao` date NOT NULL,
  `id_categoria` varchar(50) NOT NULL,
  `palavra_chave` varchar(255) NOT NULL,
  `posicao_tendencia` int(11) NOT NULL
-- Estrutura para tabela `trafego_diario`
DROP TABLE IF EXISTS `trafego_diario`;
CREATE TABLE `trafego_diario` (
  `id` bigint(20) NOT NULL,
  `id_anuncio` varchar(20) NOT NULL,
  `data_metrica` date NOT NULL,
  `cliques_ads` int(11) DEFAULT '0',
  `impressoes_ads` int(11) UNSIGNED DEFAULT '0',
  `custo_ads` decimal(10,2) UNSIGNED DEFAULT '0.00',
  `vendas_ads_qtd` int(11) UNSIGNED DEFAULT '0',
  `visitas_totais` int(11) DEFAULT '0',
  `vendas_organicas_qtd` int(11) UNSIGNED DEFAULT '0',
  `faturamento_total_ads` decimal(12,2) UNSIGNED DEFAULT '0.00',
  `vendas_totais_qtd` int(11) GENERATED ALWAYS AS ((`vendas_ads_qtd` + `vendas_organicas_qtd`)) STORED,
  `visitas_organicas_reais` int(11) GENERATED ALWAYS AS (greatest(0,(`visitas_totais` - `cliques_ads`))) STORED,
  `conversao_ads` decimal(10,4) GENERATED ALWAYS AS ((case when (`cliques_ads` = 0) then 0 else (`vendas_ads_qtd` / `cliques_ads`) end)) STORED,
  `conversao_organica` decimal(10,4) GENERATED ALWAYS AS ((case when (`visitas_organicas_reais` = 0) then 0 else (`vendas_organicas_qtd` / `visitas_organicas_reais`) end)) STORED,
  `conversao_total` decimal(10,4) GENERATED ALWAYS AS ((case when (`visitas_totais` = 0) then 0 else (`vendas_totais_qtd` / `visitas_totais`) end)) STORED
-- Estrutura para tabela `vendas_financeiro`
DROP TABLE IF EXISTS `vendas_financeiro`;
CREATE TABLE `vendas_financeiro` (
  `id` bigint(20) NOT NULL,
  `id_ordem` bigint(20) UNSIGNED NOT NULL,
  `pack_id` bigint(20) UNSIGNED DEFAULT NULL,
  `id_anuncio` varchar(20) NOT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `id_variacao` bigint(20) DEFAULT NULL,
  `data_venda` datetime NOT NULL,
  `qtd_vendida` int(11) UNSIGNED NOT NULL,
  `preco_unitario` decimal(10,2) NOT NULL,
  `faturamento_bruto_item` decimal(12,2) NOT NULL,
  `tarifa_ml` decimal(10,2) NOT NULL,
  `custo_frete_rateado` decimal(10,2) NOT NULL,
  `liquido_recebido` decimal(12,2) NOT NULL,
  `logistic_type` varchar(50) DEFAULT NULL
-- Estrutura para tabela `anuncios_canais`
DROP TABLE IF EXISTS `anuncios_canais`;
CREATE TABLE `anuncios_canais` (
  `id_anuncio_canal` varchar(30) NOT NULL,
  `canal_venda` varchar(50) NOT NULL,
  `id_anuncio_pai` varchar(20) DEFAULT NULL,
  `sku_produto` varchar(100) DEFAULT NULL,
  `titulo_anuncio` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_categoria` varchar(20) DEFAULT NULL,
  `categoria_anuncio` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `logistic_type` varchar(50) DEFAULT NULL,
  `data_atualizacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
-- Estrutura para tabela `estoque_diario`
DROP TABLE IF EXISTS `estoque_diario`;
CREATE TABLE `estoque_diario` (
  `id` int(11) NOT NULL,
  `data_snapshot` date NOT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `estoque_full_ml` int(11) DEFAULT '0',
  `estoque_geral_tiny` int(11) DEFAULT '0',
  `estoque_direct_tiny` int(11) DEFAULT '0',
  `estoque_total` int(11) GENERATED ALWAYS AS ((`estoque_full_ml` + `estoque_geral_tiny`)) STORED
-- Estrutura para tabela `log_despesas_erp`
DROP TABLE IF EXISTS `log_despesas_erp`;
CREATE TABLE `log_despesas_erp` (
  `id_lancamento_tiny` bigint(20) NOT NULL,
  `data_vencimento` date DEFAULT NULL,
  `valor` decimal(12,2) DEFAULT '0.00',
  `categoria_mapeada` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `historico` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fornecedor` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_insercao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
-- Estrutura para tabela `log_recebiveis_erp`
DROP TABLE IF EXISTS `log_recebiveis_erp`;
CREATE TABLE `log_recebiveis_erp` (
  `id` int(11) UNSIGNED NOT NULL,
  `id_lancamento_tiny` int(11) UNSIGNED NOT NULL,
  `data_recebimento` date DEFAULT NULL,
  `valor_recebido` decimal(10,2) DEFAULT NULL,
  `historico` text,
  `cliente` varchar(255) DEFAULT NULL
-- Estrutura para tabela `mapa_produtos_anuncios`
DROP TABLE IF EXISTS `mapa_produtos_anuncios`;
CREATE TABLE `mapa_produtos_anuncios` (
  `id` int(11) NOT NULL,
  `id_produto_tiny` int(11) NOT NULL,
  `sku` varchar(100) NOT NULL,
  `categoria` varchar(150) DEFAULT NULL,
  `ean` varchar(20) DEFAULT NULL,
  `titulo_produto` text,
  `id_anuncio_canal` varchar(50) NOT NULL,
  `id_anuncio_pai` varchar(50) DEFAULT NULL,
  `plataforma` varchar(50) NOT NULL DEFAULT 'meli_lare',
  `logistic_type` varchar(50) DEFAULT NULL,
  `data_atualizacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
-- Estrutura para tabela `posicionamento_anuncios`
DROP TABLE IF EXISTS `posicionamento_anuncios`;
CREATE TABLE `posicionamento_anuncios` (
  `id` int(11) UNSIGNED NOT NULL,
  `data_verificacao` date NOT NULL,
  `palavra_chave` varchar(255) NOT NULL,
  `id_anuncio_pai` varchar(50) NOT NULL,
  `posicao` int(11) DEFAULT NULL,
  `pagina` int(11) DEFAULT NULL
-- Estrutura para tabela `produtos_catalogo`
DROP TABLE IF EXISTS `produtos_catalogo`;
CREATE TABLE `produtos_catalogo` (
  `sku` varchar(100) NOT NULL,
  `id_produto_tiny` int(11) DEFAULT NULL,
  `nome_produto` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ean` varchar(20) DEFAULT NULL,
  `tipo_produto` varchar(20) DEFAULT NULL COMMENT 'Simples, Pai, Kit',
  `sku_pai` varchar(100) DEFAULT NULL,
  `custo_produto` decimal(10,2) DEFAULT '0.00',
  `data_atualizacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
-- Estrutura para tabela `relatorio_diario`
DROP TABLE IF EXISTS `relatorio_diario`;
CREATE TABLE `relatorio_diario` (
  `id` bigint(20) NOT NULL,
  `data_relatorio` date NOT NULL,
  `id_anuncio` varchar(20) NOT NULL,
  `id_anuncio_variacao` varchar(50) DEFAULT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `titulo_anuncio` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `categoria_anuncio` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `impressoes_ads` int(11) DEFAULT '0',
  `cliques_ads` int(11) DEFAULT '0',
  `custo_ads` decimal(10,2) DEFAULT '0.00',
  `vendas_ads_qtd` int(11) DEFAULT '0',
  `faturamento_ads` decimal(12,2) DEFAULT '0.00',
  `vendas_organicas_qtd` int(11) GENERATED ALWAYS AS ((`vendas_totais_qtd` - `vendas_ads_qtd`)) STORED,
  `visitas_totais` int(11) DEFAULT '0',
  `visitas_organicas_reais` int(11) GENERATED ALWAYS AS ((`visitas_totais` - `cliques_ads`)) STORED,
  `vendas_totais_qtd` int(11) DEFAULT '0',
  `faturamento_total` decimal(12,2) DEFAULT '0.00',
  `tarifa_venda_total` decimal(12,2) DEFAULT '0.00',
  `custo_frete_total` decimal(12,2) DEFAULT '0.00',
  `liquido_recebido_total` decimal(12,2) DEFAULT '0.00',
  `custo_produto_total` decimal(12,2) DEFAULT '0.00'
-- Estrutura para tabela `tarefas_pendentes_catalogo`
DROP TABLE IF EXISTS `tarefas_pendentes_catalogo`;
CREATE TABLE `tarefas_pendentes_catalogo` (
  `id` int(11) NOT NULL,
  `id_produto_tiny` bigint(20) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pendente',
  `data_processamento` datetime DEFAULT NULL,
  `mensagem_erro` text,
  `data_criacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP
-- Estrutura para tabela `tarefas_pendentes_estoque`
DROP TABLE IF EXISTS `tarefas_pendentes_estoque`;
CREATE TABLE `tarefas_pendentes_estoque` (
  `id` int(11) NOT NULL,
  `sku` varchar(100) NOT NULL,
  `id_produto_tiny` int(11) DEFAULT NULL,
  `data_snapshot` date DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pendente',
  `data_processamento` datetime DEFAULT NULL,
  `mensagem_erro` text,
  `data_criacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP
-- Estrutura para tabela `tarefas_pendentes_trafego`
DROP TABLE IF EXISTS `tarefas_pendentes_trafego`;
CREATE TABLE `tarefas_pendentes_trafego` (
  `id` int(11) NOT NULL,
  `id_anuncio` varchar(20) NOT NULL,
  `data_metrica` date NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pendente',
  `data_processamento` datetime DEFAULT NULL,
  `mensagem_erro` text,
  `data_criacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP
-- Estrutura para tabela `tendencias_mercado`
DROP TABLE IF EXISTS `tendencias_mercado`;
CREATE TABLE `tendencias_mercado` (
  `id` int(11) UNSIGNED NOT NULL,
  `data_verificacao` date NOT NULL,
  `id_categoria` varchar(50) NOT NULL,
  `palavra_chave` varchar(255) NOT NULL,
  `posicao_tendencia` int(11) NOT NULL
-- Estrutura para tabela `trafego_diario`
DROP TABLE IF EXISTS `trafego_diario`;
CREATE TABLE `trafego_diario` (
  `id` bigint(20) NOT NULL,
  `id_anuncio` varchar(20) NOT NULL,
  `data_metrica` date NOT NULL,
  `cliques_ads` int(11) DEFAULT '0',
  `impressoes_ads` int(11) UNSIGNED DEFAULT '0',
  `custo_ads` decimal(10,2) UNSIGNED DEFAULT '0.00',
  `vendas_ads_qtd` int(11) UNSIGNED DEFAULT '0',
  `visitas_totais` int(11) DEFAULT '0',
  `vendas_organicas_qtd` int(11) UNSIGNED DEFAULT '0',
  `faturamento_total_ads` decimal(12,2) UNSIGNED DEFAULT '0.00',
  `vendas_totais_qtd` int(11) GENERATED ALWAYS AS ((`vendas_ads_qtd` + `vendas_organicas_qtd`)) STORED,
  `visitas_organicas_reais` int(11) GENERATED ALWAYS AS (greatest(0,(`visitas_totais` - `cliques_ads`))) STORED,
  `conversao_ads` decimal(10,4) GENERATED ALWAYS AS ((case when (`cliques_ads` = 0) then 0 else (`vendas_ads_qtd` / `cliques_ads`) end)) STORED,
  `conversao_organica` decimal(10,4) GENERATED ALWAYS AS ((case when (`visitas_organicas_reais` = 0) then 0 else (`vendas_organicas_qtd` / `visitas_organicas_reais`) end)) STORED,
  `conversao_total` decimal(10,4) GENERATED ALWAYS AS ((case when (`visitas_totais` = 0) then 0 else (`vendas_totais_qtd` / `visitas_totais`) end)) STORED
-- Estrutura para tabela `vendas_financeiro`
DROP TABLE IF EXISTS `vendas_financeiro`;
CREATE TABLE `vendas_financeiro` (
  `id` bigint(20) NOT NULL,
  `id_ordem` bigint(20) UNSIGNED NOT NULL,
  `pack_id` bigint(20) UNSIGNED DEFAULT NULL,
  `id_anuncio` varchar(20) NOT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `id_variacao` bigint(20) DEFAULT NULL,
  `data_venda` datetime NOT NULL,
  `qtd_vendida` int(11) UNSIGNED NOT NULL,
  `preco_unitario` decimal(10,2) NOT NULL,
  `faturamento_bruto_item` decimal(12,2) NOT NULL,
  `tarifa_ml` decimal(10,2) NOT NULL,
  `custo_frete_rateado` decimal(10,2) NOT NULL,
  `liquido_recebido` decimal(12,2) NOT NULL,
  `logistic_type` varchar(50) DEFAULT NULL
