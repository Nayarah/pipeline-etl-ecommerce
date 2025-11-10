-- Estrutura para view `vw_dados_consolidados_diarios`
DROP TABLE IF EXISTS `vw_dados_consolidados_diarios`;
DROP VIEW IF EXISTS `vw_dados_consolidados_diarios`;
CREATE ALGORITHM=UNDEFINED DEFINER=`lareof17`@`localhost` SQL SECURITY DEFINER VIEW `vw_dados_consolidados_diarios`  AS SELECT `transacoes_consolidadas`.`data` AS `data`, sum(`transacoes_consolidadas`.`faturamento_total`) AS `faturamento_total`, sum(`transacoes_consolidadas`.`custo_produto_total`) AS `custo_produto_total`, sum(`transacoes_consolidadas`.`tarifa_venda_total`) AS `tarifa_venda_total`, sum(`transacoes_consolidadas`.`custo_frete_total`) AS `custo_frete_total`, sum(`transacoes_consolidadas`.`custo_ads`) AS `custo_ads`, sum(`transacoes_consolidadas`.`despesas_fixas`) AS `despesas_fixas`, sum(`transacoes_consolidadas`.`despesas_variaveis`) AS `despesas_variaveis` FROM (select `relatorio_diario`.`data_relatorio` AS `data`,`relatorio_diario`.`faturamento_total` AS `faturamento_total`,`relatorio_diario`.`custo_produto_total` AS `custo_produto_total`,`relatorio_diario`.`tarifa_venda_total` AS `tarifa_venda_total`,`relatorio_diario`.`custo_frete_total` AS `custo_frete_total`,`relatorio_diario`.`custo_ads` AS `custo_ads`,0 AS `despesas_fixas`,0 AS `despesas_variaveis` from `relatorio_diario` union all select `log_despesas_erp`.`data_vencimento` AS `data`,0 AS `faturamento_total`,0 AS `custo_produto_total`,0 AS `tarifa_venda_total`,0 AS `custo_frete_total`,0 AS `custo_ads`,(case when (`log_despesas_erp`.`categoria_mapeada` in ('Água','Aluguéis e condomínio','Contabilidade','Energia','Internet','Monitoramento e segurança','Pagamento de mão de obra e funcionários','Parcelamentos','Segurança e Monitoramento','Serviço de mão de obra de terceiros','Sistemas','serviços financeiros')) then `log_despesas_erp`.`valor` else 0 end) AS `despesas_fixas`,(case when (`log_despesas_erp`.`categoria_mapeada` in ('Água','Aluguéis e condomínio','Contabilidade','Energia','Internet','Monitoramento e segurança','Pagamento de mão de obra e funcionários','Parcelamentos','Segurança e Monitoramento','Serviço de mão de obra de terceiros','Sistemas','serviços financeiros','Pessoal','Vaptlar','Sem Categoria')) then 0 else `log_despesas_erp`.`valor` end) AS `despesas_variaveis` from `log_despesas_erp`) AS `transacoes_consolidadas` GROUP BY `transacoes_consolidadas`.`data` ORDER BY `transacoes_consolidadas`.`data` ASC ;
-- Estrutura para view `vw_dre_mensal`
DROP TABLE IF EXISTS `vw_dre_mensal`;
DROP VIEW IF EXISTS `vw_dre_mensal`;
CREATE ALGORITHM=UNDEFINED DEFINER=`lareof17`@`localhost` SQL SECURITY DEFINER VIEW `vw_dre_mensal`  AS SELECT date_format(`vw_dados_consolidados_diarios`.`data`,'%Y-%m-01') AS `mes`, sum(`vw_dados_consolidados_diarios`.`faturamento_total`) AS `faturamento_total`, sum(`vw_dados_consolidados_diarios`.`custo_produto_total`) AS `custo_produto_total`, sum(`vw_dados_consolidados_diarios`.`tarifa_venda_total`) AS `tarifa_venda_total`, sum(`vw_dados_consolidados_diarios`.`custo_frete_total`) AS `custo_frete_total`, sum(`vw_dados_consolidados_diarios`.`custo_ads`) AS `custo_ads`, sum(`vw_dados_consolidados_diarios`.`despesas_fixas`) AS `despesas_fixas`, sum(`vw_dados_consolidados_diarios`.`despesas_variaveis`) AS `despesas_variaveis` FROM `vw_dados_consolidados_diarios` GROUP BY date_format(`vw_dados_consolidados_diarios`.`data`,'%Y-%m-01') ORDER BY `mes` ASC ;
-- Estrutura para view `vw_fluxo_de_caixa`
DROP TABLE IF EXISTS `vw_fluxo_de_caixa`;
DROP VIEW IF EXISTS `vw_fluxo_de_caixa`;
CREATE ALGORITHM=UNDEFINED DEFINER=`lareof17`@`localhost` SQL SECURITY DEFINER VIEW `vw_fluxo_de_caixa`  AS SELECT `transacoes`.`data` AS `data`, sum(`transacoes`.`entradas`) AS `total_entradas`, sum(`transacoes`.`saidas`) AS `total_saidas` FROM (select `log_recebiveis_erp`.`data_recebimento` AS `data`,`log_recebiveis_erp`.`valor_recebido` AS `entradas`,0 AS `saidas` from `log_recebiveis_erp` union all select `log_despesas_erp`.`data_vencimento` AS `data`,0 AS `entradas`,`log_despesas_erp`.`valor` AS `saidas` from `log_despesas_erp` where (`log_despesas_erp`.`categoria_mapeada` not in ('Pessoal','VaptLar','Sem Categoria'))) AS `transacoes` GROUP BY `transacoes`.`data` ORDER BY `transacoes`.`data` ASC ;
-- Índices de tabela `anuncios_canais`
ALTER TABLE `anuncios_canais`
  ADD PRIMARY KEY (`id_anuncio_canal`,`canal_venda`),
  ADD KEY `idx_sku` (`sku_produto`);
-- Índices de tabela `estoque_diario`
ALTER TABLE `estoque_diario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `data_sku_unico` (`data_snapshot`,`sku`);
-- Índices de tabela `log_despesas_erp`
ALTER TABLE `log_despesas_erp`
  ADD PRIMARY KEY (`id_lancamento_tiny`);
-- Índices de tabela `log_recebiveis_erp`
ALTER TABLE `log_recebiveis_erp`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_id_lancamento_tiny` (`id_lancamento_tiny`);
-- Índices de tabela `mapa_produtos_anuncios`
ALTER TABLE `mapa_produtos_anuncios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_sku_anuncio_unico` (`sku`,`id_anuncio_pai`) USING BTREE,
  ADD KEY `idx_id_tiny` (`id_produto_tiny`);
-- Índices de tabela `posicionamento_anuncios`
ALTER TABLE `posicionamento_anuncios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_data_palavra_anuncio` (`data_verificacao`,`palavra_chave`,`id_anuncio_pai`);
-- Índices de tabela `produtos_catalogo`
ALTER TABLE `produtos_catalogo`
  ADD PRIMARY KEY (`sku`),
  ADD KEY `idx_id_tiny` (`id_produto_tiny`);
-- Índices de tabela `relatorio_diario`
ALTER TABLE `relatorio_diario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_data_variacao_unica` (`data_relatorio`,`id_anuncio_variacao`);
-- Índices de tabela `tarefas_pendentes_catalogo`
ALTER TABLE `tarefas_pendentes_catalogo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_produto_unico` (`id_produto_tiny`);
-- Índices de tabela `tarefas_pendentes_estoque`
ALTER TABLE `tarefas_pendentes_estoque`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tarefa_unica` (`sku`,`data_snapshot`);
-- Índices de tabela `tarefas_pendentes_trafego`
ALTER TABLE `tarefas_pendentes_trafego`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tarefa_unica` (`id_anuncio`,`data_metrica`);
-- Índices de tabela `tendencias_mercado`
ALTER TABLE `tendencias_mercado`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_data_categoria_palavra` (`data_verificacao`,`id_categoria`,`palavra_chave`);
-- Índices de tabela `trafego_diario`
ALTER TABLE `trafego_diario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `anuncio_dia_unico` (`id_anuncio`,`data_metrica`),
  ADD KEY `idx_data_metrica` (`data_metrica`);
-- Índices de tabela `vendas_financeiro`
ALTER TABLE `vendas_financeiro`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ordem_item_unica` (`id_ordem`,`id_anuncio`),
  ADD KEY `idx_data_venda` (`data_venda`);
-- AUTO_INCREMENT de tabela `estoque_diario`
ALTER TABLE `estoque_diario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
-- AUTO_INCREMENT de tabela `log_recebiveis_erp`
ALTER TABLE `log_recebiveis_erp`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;
-- AUTO_INCREMENT de tabela `mapa_produtos_anuncios`
ALTER TABLE `mapa_produtos_anuncios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
-- AUTO_INCREMENT de tabela `posicionamento_anuncios`
ALTER TABLE `posicionamento_anuncios`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;
-- AUTO_INCREMENT de tabela `relatorio_diario`
ALTER TABLE `relatorio_diario`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;
-- AUTO_INCREMENT de tabela `tarefas_pendentes_catalogo`
ALTER TABLE `tarefas_pendentes_catalogo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
-- AUTO_INCREMENT de tabela `tarefas_pendentes_estoque`
ALTER TABLE `tarefas_pendentes_estoque`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
-- AUTO_INCREMENT de tabela `tarefas_pendentes_trafego`
ALTER TABLE `tarefas_pendentes_trafego`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
-- AUTO_INCREMENT de tabela `tendencias_mercado`
ALTER TABLE `tendencias_mercado`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;
-- AUTO_INCREMENT de tabela `trafego_diario`
ALTER TABLE `trafego_diario`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;
-- AUTO_INCREMENT de tabela `vendas_financeiro`
ALTER TABLE `vendas_financeiro`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;
-- Banco de dados: `lareof17_meli_dados`
DROP DATABASE IF EXISTS `lareof17_meli_dados`;
CREATE DATABASE IF NOT EXISTS `lareof17_meli_dados` DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;
USE `lareof17_meli_dados`;
-- Estrutura para view `vw_dados_consolidados_diarios`
DROP TABLE IF EXISTS `vw_dados_consolidados_diarios`;
DROP VIEW IF EXISTS `vw_dados_consolidados_diarios`;
CREATE ALGORITHM=UNDEFINED DEFINER=`lareof17`@`localhost` SQL SECURITY DEFINER VIEW `vw_dados_consolidados_diarios`  AS SELECT `transacoes_consolidadas`.`data` AS `data`, sum(`transacoes_consolidadas`.`faturamento_total`) AS `faturamento_total`, sum(`transacoes_consolidadas`.`custo_produto_total`) AS `custo_produto_total`, sum(`transacoes_consolidadas`.`tarifa_venda_total`) AS `tarifa_venda_total`, sum(`transacoes_consolidadas`.`custo_frete_total`) AS `custo_frete_total`, sum(`transacoes_consolidadas`.`custo_ads`) AS `custo_ads`, sum(`transacoes_consolidadas`.`despesas_fixas`) AS `despesas_fixas`, sum(`transacoes_consolidadas`.`despesas_variaveis`) AS `despesas_variaveis` FROM (select `relatorio_diario`.`data_relatorio` AS `data`,`relatorio_diario`.`faturamento_total` AS `faturamento_total`,`relatorio_diario`.`custo_produto_total` AS `custo_produto_total`,`relatorio_diario`.`tarifa_venda_total` AS `tarifa_venda_total`,`relatorio_diario`.`custo_frete_total` AS `custo_frete_total`,`relatorio_diario`.`custo_ads` AS `custo_ads`,0 AS `despesas_fixas`,0 AS `despesas_variaveis` from `relatorio_diario` union all select `log_despesas_erp`.`data_vencimento` AS `data`,0 AS `faturamento_total`,0 AS `custo_produto_total`,0 AS `tarifa_venda_total`,0 AS `custo_frete_total`,0 AS `custo_ads`,(case when (`log_despesas_erp`.`categoria_mapeada` in ('Água','Aluguéis e condomínio','Contabilidade','Energia','Internet','Monitoramento e segurança','Pagamento de mão de obra e funcionários','Parcelamentos','Segurança e Monitoramento','Serviço de mão de obra de terceiros','Sistemas','serviços financeiros')) then `log_despesas_erp`.`valor` else 0 end) AS `despesas_fixas`,(case when (`log_despesas_erp`.`categoria_mapeada` in ('Água','Aluguéis e condomínio','Contabilidade','Energia','Internet','Monitoramento e segurança','Pagamento de mão de obra e funcionários','Parcelamentos','Segurança e Monitoramento','Serviço de mão de obra de terceiros','Sistemas','serviços financeiros','Pessoal','Vaptlar','Sem Categoria')) then 0 else `log_despesas_erp`.`valor` end) AS `despesas_variaveis` from `log_despesas_erp`) AS `transacoes_consolidadas` GROUP BY `transacoes_consolidadas`.`data` ORDER BY `transacoes_consolidadas`.`data` ASC ;
-- Estrutura para view `vw_dre_mensal`
DROP TABLE IF EXISTS `vw_dre_mensal`;
DROP VIEW IF EXISTS `vw_dre_mensal`;
CREATE ALGORITHM=UNDEFINED DEFINER=`lareof17`@`localhost` SQL SECURITY DEFINER VIEW `vw_dre_mensal`  AS SELECT date_format(`vw_dados_consolidados_diarios`.`data`,'%Y-%m-01') AS `mes`, sum(`vw_dados_consolidados_diarios`.`faturamento_total`) AS `faturamento_total`, sum(`vw_dados_consolidados_diarios`.`custo_produto_total`) AS `custo_produto_total`, sum(`vw_dados_consolidados_diarios`.`tarifa_venda_total`) AS `tarifa_venda_total`, sum(`vw_dados_consolidados_diarios`.`custo_frete_total`) AS `custo_frete_total`, sum(`vw_dados_consolidados_diarios`.`custo_ads`) AS `custo_ads`, sum(`vw_dados_consolidados_diarios`.`despesas_fixas`) AS `despesas_fixas`, sum(`vw_dados_consolidados_diarios`.`despesas_variaveis`) AS `despesas_variaveis` FROM `vw_dados_consolidados_diarios` GROUP BY date_format(`vw_dados_consolidados_diarios`.`data`,'%Y-%m-01') ORDER BY `mes` ASC ;
-- Estrutura para view `vw_fluxo_de_caixa`
DROP TABLE IF EXISTS `vw_fluxo_de_caixa`;
DROP VIEW IF EXISTS `vw_fluxo_de_caixa`;
CREATE ALGORITHM=UNDEFINED DEFINER=`lareof17`@`localhost` SQL SECURITY DEFINER VIEW `vw_fluxo_de_caixa`  AS SELECT `transacoes`.`data` AS `data`, sum(`transacoes`.`entradas`) AS `total_entradas`, sum(`transacoes`.`saidas`) AS `total_saidas` FROM (select `log_recebiveis_erp`.`data_recebimento` AS `data`,`log_recebiveis_erp`.`valor_recebido` AS `entradas`,0 AS `saidas` from `log_recebiveis_erp` union all select `log_despesas_erp`.`data_vencimento` AS `data`,0 AS `entradas`,`log_despesas_erp`.`valor` AS `saidas` from `log_despesas_erp` where (`log_despesas_erp`.`categoria_mapeada` not in ('Pessoal','VaptLar','Sem Categoria'))) AS `transacoes` GROUP BY `transacoes`.`data` ORDER BY `transacoes`.`data` ASC ;
-- Índices de tabela `anuncios_canais`
ALTER TABLE `anuncios_canais`
  ADD PRIMARY KEY (`id_anuncio_canal`,`canal_venda`),
  ADD KEY `idx_sku` (`sku_produto`);
-- Índices de tabela `estoque_diario`
ALTER TABLE `estoque_diario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `data_sku_unico` (`data_snapshot`,`sku`);
-- Índices de tabela `log_despesas_erp`
ALTER TABLE `log_despesas_erp`
  ADD PRIMARY KEY (`id_lancamento_tiny`);
-- Índices de tabela `log_recebiveis_erp`
ALTER TABLE `log_recebiveis_erp`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_id_lancamento_tiny` (`id_lancamento_tiny`);
-- Índices de tabela `mapa_produtos_anuncios`
ALTER TABLE `mapa_produtos_anuncios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_sku_anuncio_unico` (`sku`,`id_anuncio_pai`) USING BTREE,
  ADD KEY `idx_id_tiny` (`id_produto_tiny`);
-- Índices de tabela `posicionamento_anuncios`
ALTER TABLE `posicionamento_anuncios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_data_palavra_anuncio` (`data_verificacao`,`palavra_chave`,`id_anuncio_pai`);
-- Índices de tabela `produtos_catalogo`
ALTER TABLE `produtos_catalogo`
  ADD PRIMARY KEY (`sku`),
  ADD KEY `idx_id_tiny` (`id_produto_tiny`);
-- Índices de tabela `relatorio_diario`
ALTER TABLE `relatorio_diario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_data_variacao_unica` (`data_relatorio`,`id_anuncio_variacao`);
-- Índices de tabela `tarefas_pendentes_catalogo`
ALTER TABLE `tarefas_pendentes_catalogo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_produto_unico` (`id_produto_tiny`);
-- Índices de tabela `tarefas_pendentes_estoque`
ALTER TABLE `tarefas_pendentes_estoque`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tarefa_unica` (`sku`,`data_snapshot`);
-- Índices de tabela `tarefas_pendentes_trafego`
ALTER TABLE `tarefas_pendentes_trafego`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tarefa_unica` (`id_anuncio`,`data_metrica`);
-- Índices de tabela `tendencias_mercado`
ALTER TABLE `tendencias_mercado`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_data_categoria_palavra` (`data_verificacao`,`id_categoria`,`palavra_chave`);
-- Índices de tabela `trafego_diario`
ALTER TABLE `trafego_diario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `anuncio_dia_unico` (`id_anuncio`,`data_metrica`),
  ADD KEY `idx_data_metrica` (`data_metrica`);
-- Índices de tabela `vendas_financeiro`
ALTER TABLE `vendas_financeiro`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ordem_item_unica` (`id_ordem`,`id_anuncio`),
  ADD KEY `idx_data_venda` (`data_venda`);
-- AUTO_INCREMENT de tabela `estoque_diario`
ALTER TABLE `estoque_diario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
-- AUTO_INCREMENT de tabela `log_recebiveis_erp`
ALTER TABLE `log_recebiveis_erp`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;
-- AUTO_INCREMENT de tabela `mapa_produtos_anuncios`
ALTER TABLE `mapa_produtos_anuncios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
-- AUTO_INCREMENT de tabela `posicionamento_anuncios`
ALTER TABLE `posicionamento_anuncios`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;
-- AUTO_INCREMENT de tabela `relatorio_diario`
ALTER TABLE `relatorio_diario`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;
-- AUTO_INCREMENT de tabela `tarefas_pendentes_catalogo`
ALTER TABLE `tarefas_pendentes_catalogo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
-- AUTO_INCREMENT de tabela `tarefas_pendentes_estoque`
ALTER TABLE `tarefas_pendentes_estoque`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
-- AUTO_INCREMENT de tabela `tarefas_pendentes_trafego`
ALTER TABLE `tarefas_pendentes_trafego`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
-- AUTO_INCREMENT de tabela `tendencias_mercado`
ALTER TABLE `tendencias_mercado`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;
-- AUTO_INCREMENT de tabela `trafego_diario`
ALTER TABLE `trafego_diario`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;
-- AUTO_INCREMENT de tabela `vendas_financeiro`
ALTER TABLE `vendas_financeiro`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;
