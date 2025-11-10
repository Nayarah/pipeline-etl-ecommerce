# Pipeline de ETL para Data Marts (Marketing e Financeiro) - Lar&Off

## 🎯 Contexto e Objetivo

Este projeto consiste em um conjunto de pipelines de **ETL (Extract, Transform, Load)** desenvolvidos em PHP para a empresa **Lar&Off**. O objetivo principal é consolidar dados de diversas fontes (APIs de Marketplaces e sistema ERP) em um banco de dados **MySQL** para a construção de **Data Marts** de **Marketing & Performance** e **Financeiro**.

A solução automatiza a coleta e o processamento de dados que estavam isolados, permitindo uma visão unificada e a análise de **KPIs (Key Performance Indicators)** de forma centralizada através de uma ferramenta de **Business Intelligence (BI)**.

## 🏗️ Arquitetura da Solução

O fluxo de dados segue uma arquitetura de alto nível, com a orquestração baseada em **Cron Jobs** para a execução dos *scripts* em PHP.

> **Fontes de Dados (APIs) → Scripts de ETL (PHP) → Data Marts (MySQL) → Ferramenta de BI (Looker)**

### 🛠️ Tecnologias Utilizadas

| Categoria | Tecnologia | Uso no Projeto |
| :--- | :--- | :--- |
| **Linguagem de Backend** | PHP | Desenvolvimento dos *scripts* de ETL e *workers*. |
| **Banco de Dados** | MySQL | Destino final dos dados (Data Marts) e armazenamento de filas. |
| **Orquestração** | Cron Jobs | Agendamento da execução das tarefas (`tarefa_*.php`). |
| **Fontes de Dados** | Mercado Livre API, Tiny ERP API | Extração de dados transacionais, de catálogo e métricas. |
| **Business Intelligence** | Looker | Visualização e análise dos dados consolidados. |

## 📊 Modelo de Dados (Esquema Estrela)

O modelo de dados é baseado no **Esquema Estrela**, otimizado para consultas analíticas. O DDL completo (criação de tabelas e views) pode ser encontrado na pasta `sql_schema/`.

### Data Mart de Marketing & Performance

**Objetivo:** Análise de ROI, performance de campanhas, tráfego e conversão.

| Tipo de Tabela | Tabela | Descrição | Chave Única |
| :--- | :--- | :--- | :--- |
| **Fato** | `trafego_diario` | Métricas diárias de visitas, cliques, custo e vendas por anúncio. | `id_anuncio`, `data_metrica` |
| **Fato** | `relatorio_diario` | Consolidação diária de vendas e métricas de tráfego (tabela agregada). | `data_relatorio`, `id_anuncio_variacao` |
| **Dimensão** | `anuncios_canais` | Detalhes dos anúncios (título, status, tipo logístico). | `id_anuncio_canal`, `canal_venda` |
| **Dimensão** | `produtos_catalogo` | Detalhes do produto (SKU, nome, custo) do Tiny ERP. | `sku` |
| **Suporte** | `estoque_diario` | Snapshot diário dos níveis de estoque (Geral, Direct, Full). | `data_snapshot`, `sku` |

### Data Mart Financeiro

**Objetivo:** Geração de visões de fluxo de caixa e DRE (Demonstrativo de Resultados).

| Tipo de Tabela | Tabela | Descrição | Chave Única |
| :--- | :--- | :--- | :--- |
| **Fato** | `vendas_financeiro` | Detalhes de cada item vendido, com valores financeiros (líquido, tarifas, frete rateado). | `id_ordem`, `id_anuncio` |
| **Suporte** | `log_despesas_erp` | Log de despesas importadas do ERP. | `id_lancamento_tiny` |
| **Suporte** | `log_recebiveis_erp` | Log de recebíveis importados do ERP. | `id_lancamento_tiny` |

### Views (Camada de BI)

As Views são a camada de agregação e transformação final para o consumo no Looker.

| View | Propósito |
| :--- | :--- |
| **`vw_dados_consolidados_diarios`** | Consolidação diária de todas as receitas (`relatorio_diario`) e despesas (`log_despesas_erp`). |
| **`vw_dre_mensal`** | Demonstração do Resultado do Exercício (DRE) mensal, baseada na consolidação diária. |
| **`vw_fluxo_de_caixa`** | Visão diária do fluxo de caixa, unindo recebíveis e despesas. |

## ⚙️ Organização do Código e Scripts de ETL

O código-fonte é organizado em *scripts* PHP de execução única, agendados via Cron Job.

| Padrão de Nomenclatura | Exemplo | Função |
| :--- | :--- | :--- |
| **`config.php`** | N/A | Contém credenciais de acesso (DB, APIs) e funções de suporte (e.g., gerenciamento de token Meli). |
| **`tarefa_*.php`** | `tarefa_coletar_financas.php` | Scripts principais agendados para iniciar a coleta, sincronização ou consolidação de dados. |
| **`worker_*.php`** | `worker_processar_trafego.php` | Scripts que processam filas ou dados em lote, separados das tarefas de coleta para melhor desempenho e orquestração. |
| **`tarefas_pendentes_*`** | `tarefas_pendentes_estoque` | Tabelas de fila no MySQL para orquestração assíncrona entre tarefas e *workers*. |
| **`autorizar.php`** | N/A | Script para o fluxo de autenticação OAuth do Mercado Livre. |

## 💾 Estrutura do Schema SQL

O DDL (Data Definition Language) para a criação do banco de dados está organizado na pasta `sql_schema/` para facilitar a implantação:

*   `01_tables_ddl.sql`: Contém todos os comandos `CREATE TABLE`.
*   `02_views_ddl.sql`: Contém todos os comandos `CREATE VIEW`.
*   `03_indexes_ddl.sql`: Contém os comandos `ALTER TABLE` para chaves primárias e índices.
*   `04_auto_increment_ddl.sql`: Contém os comandos `ALTER TABLE` para definir o `AUTO_INCREMENT`.

## ⚖️ Licença e Contato

### 👨‍💻 Autor
- **Nome:** Nayara Francisco de Almeida
- **GitHub:** [https://github.com/Nayarah](https://github.com/Nayarah)
- **LinkedIn:** [https://www.linkedin.com/in/nayara-falmeida/](https://www.linkedin.com/in/nayara-falmeida/))
### 🤝 Contribuições
Sinta-se à vontade para abrir **Issues** ou enviar **Pull Requests** se tiver sugestões, melhorias ou encontrar bugs. Todo o feedback é bem-vindo!

### 📄 Licença do Projeto
Este projeto está licenciado sob a **Licença MIT**. Para detalhes completos sobre as permissões e restrições de uso, consulte o arquivo **`LICENSE`** na raiz do repositório.