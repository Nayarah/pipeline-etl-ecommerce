# Pipeline de ETL para Data Marts (Marketing e Financeiro)

Este projeto consiste em um conjunto de pipelines de ETL responsáveis pela construção e manutenção de dois Data Marts principais em um banco de dados MySQL, com o objetivo de centralizar e analisar dados de negócio da empresa Lar&Off.

## 🎯 Contexto e Problema de Negócio

A principal motivação para este projeto foi a necessidade de consolidar dados de diversas fontes (APIs de Marketplaces, sistema ERP) que estavam isoladas, impossibilitando uma visão unificada da performance do negócio. A solução automatiza a coleta desses dados para permitir a análise de KPIs de forma centralizada.

## 🏗️ Arquitetura da Solução

O fluxo de dados segue a seguinte arquitetura de alto nível:

**Fontes de Dados (APIs) → Scripts de ETL (PHP) → Data Marts (MySQL) → Ferramenta de BI (Looker)**

## 📊 Data Marts Construídos

Foram modelados dois Data Marts principais, seguindo um modelo de Esquema Estrela (Star Schema) para otimização das consultas:

1.  **Marketing & Performance:**
    * **Objetivo:** Consolidar dados de tráfego, investimento em anúncios e métricas de conversão para análise de ROI e performance de campanhas.
    * **Tabelas Fato:** Vendas, Sessões de Tráfego.
    * **Tabelas de Dimensão:** Anúncios, Produtos, Tempo.

2.  **Financeiro:**
    * **Objetivo:** Agregar dados do ERP e das plataformas de pagamento para gerar visões de fluxo de caixa e DRE (Demonstrativo de Resultados).
    * **Fontes:** Tabelas de receitas e despesas.
    * **Visualização:** Dashboards com agregações diárias e relatórios com dados detalhados.

## 🛠️ Tecnologias Utilizadas

* **Linguagem de Backend:** PHP
* **Banco de Dados:** MySQL
* **Orquestração:** Cron Jobs
* **Business Intelligence:** Looker
* **Controle de Versão:** Git & GitHub

## STATUS

**Projeto concluído e em produção.**