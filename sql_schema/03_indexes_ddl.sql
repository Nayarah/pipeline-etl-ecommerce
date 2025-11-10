ALTER TABLE `anuncios_canais`
  ADD PRIMARY KEY (`id_anuncio_canal`,`canal_venda`),
  ADD KEY `idx_sku` (`sku_produto`);
ALTER TABLE `estoque_diario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `data_sku_unico` (`data_snapshot`,`sku`);
ALTER TABLE `log_despesas_erp`
  ADD PRIMARY KEY (`id_lancamento_tiny`);
ALTER TABLE `log_recebiveis_erp`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_id_lancamento_tiny` (`id_lancamento_tiny`);
ALTER TABLE `mapa_produtos_anuncios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_sku_anuncio_unico` (`sku`,`id_anuncio_pai`) USING BTREE,
  ADD KEY `idx_id_tiny` (`id_produto_tiny`);
ALTER TABLE `posicionamento_anuncios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_data_palavra_anuncio` (`data_verificacao`,`palavra_chave`,`id_anuncio_pai`);
ALTER TABLE `produtos_catalogo`
  ADD PRIMARY KEY (`sku`),
  ADD KEY `idx_id_tiny` (`id_produto_tiny`);
ALTER TABLE `relatorio_diario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_data_variacao_unica` (`data_relatorio`,`id_anuncio_variacao`);
ALTER TABLE `tarefas_pendentes_catalogo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_produto_unico` (`id_produto_tiny`);
ALTER TABLE `tarefas_pendentes_estoque`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tarefa_unica` (`sku`,`data_snapshot`);
ALTER TABLE `tarefas_pendentes_trafego`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tarefa_unica` (`id_anuncio`,`data_metrica`);
ALTER TABLE `tendencias_mercado`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_data_categoria_palavra` (`data_verificacao`,`id_categoria`,`palavra_chave`);
ALTER TABLE `trafego_diario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `anuncio_dia_unico` (`id_anuncio`,`data_metrica`),
  ADD KEY `idx_data_metrica` (`data_metrica`);
ALTER TABLE `vendas_financeiro`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ordem_item_unica` (`id_ordem`,`id_anuncio`),
  ADD KEY `idx_data_venda` (`data_venda`);
ALTER TABLE `anuncios_canais`
  ADD PRIMARY KEY (`id_anuncio_canal`,`canal_venda`),
  ADD KEY `idx_sku` (`sku_produto`);
ALTER TABLE `estoque_diario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `data_sku_unico` (`data_snapshot`,`sku`);
ALTER TABLE `log_despesas_erp`
  ADD PRIMARY KEY (`id_lancamento_tiny`);
ALTER TABLE `log_recebiveis_erp`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_id_lancamento_tiny` (`id_lancamento_tiny`);
ALTER TABLE `mapa_produtos_anuncios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_sku_anuncio_unico` (`sku`,`id_anuncio_pai`) USING BTREE,
  ADD KEY `idx_id_tiny` (`id_produto_tiny`);
ALTER TABLE `posicionamento_anuncios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_data_palavra_anuncio` (`data_verificacao`,`palavra_chave`,`id_anuncio_pai`);
ALTER TABLE `produtos_catalogo`
  ADD PRIMARY KEY (`sku`),
  ADD KEY `idx_id_tiny` (`id_produto_tiny`);
ALTER TABLE `relatorio_diario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_data_variacao_unica` (`data_relatorio`,`id_anuncio_variacao`);
ALTER TABLE `tarefas_pendentes_catalogo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_produto_unico` (`id_produto_tiny`);
ALTER TABLE `tarefas_pendentes_estoque`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tarefa_unica` (`sku`,`data_snapshot`);
ALTER TABLE `tarefas_pendentes_trafego`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tarefa_unica` (`id_anuncio`,`data_metrica`);
ALTER TABLE `tendencias_mercado`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_data_categoria_palavra` (`data_verificacao`,`id_categoria`,`palavra_chave`);
ALTER TABLE `trafego_diario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `anuncio_dia_unico` (`id_anuncio`,`data_metrica`),
  ADD KEY `idx_data_metrica` (`data_metrica`);
ALTER TABLE `vendas_financeiro`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ordem_item_unica` (`id_ordem`,`id_anuncio`),
  ADD KEY `idx_data_venda` (`data_venda`);
