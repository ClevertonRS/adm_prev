# Arquitetura Técnica — Radar GPON

**Data**: 3 de julho de 2026  
**Versão**: 1.0  
**Status**: Documentação completa de engenharia reversa

---

## 📋 Índice

1. [Visão Geral](#visão-geral)
2. [Estrutura Técnica](#estrutura-técnica)
3. [Banco de Dados](#banco-de-dados)
4. [Fluxo de Autenticação](#fluxo-de-autenticação)
5. [Permissões e Autorização](#permissões-e-autorização)
6. [APIs e Endpoints](#apis-e-endpoints)
7. [Frontend](#frontend)
8. [Regras de Negócio](#regras-de-negócio)
9. [Convenções de Código](#convenções-de-código)
10. [Problemas Identificados](#problemas-identificados)
11. [Backlog Técnico Priorizado](#backlog-técnico-priorizado)

---

## Visão Geral

Radar GPON é um sistema de gestão e análise de ocorrências GPON (Gigabit Passive Optical Network) para operadoras de telecom. Funciona como:

- **Dashboard interativo** para visualizar ocorrências abertas e fechadas
- **Sistema de importação** de dados via planilhas Excel (.xlsx / .xls)
- **Análise de reincidência** de problemas em GPONs e splitters
- **Admin** para gerenciar usuários e mapeamentos GPON ↔ Empresa
- **Histórico e auditoria** de todas as alterações

**Stack**: PHP 7.4+ | MySQL 5.7+ | jQuery | Bootstrap 5 | DataTables | PhpSpreadsheet

**Dependências via Composer**:

- `phpoffice/phpspreadsheet` v2.x (leitura/escrita Excel)
- Polyfills Symfony (suporte a string multi-byte)

---

## Estrutura Técnica

### Árvore de Diretórios

```
admin/
├── index.php                    # Front controller (router + inicialização)
├── composer.json               # Dependências
├── ARQUITETURA.md              # Este arquivo
│
├── config/
│   ├── database.php            # Configuração PDO + credenciais
│   └── gpon_empresa_map.php    # Mapeamento estático GPON↔Empresa (legado)
│
├── includes/
│   ├── auth.php                # Sessão, login/logout, autorização
│   └── functions.php           # 1283 linhas: lógica de negócio, helpers
│
├── api/
│   ├── dashboard.php           # /api/data, /api/stats, /api/filters, /api/reinc-counts
│   ├── analise.php             # /api/analise, /api/analise/* (análise de reincidência)
│   ├── ocorrencias.php         # /api/ocorrencia/* (CRUD)
│   ├── historico.php           # /api/historico/*, /api/previsao/* (timeline + previsões)
│   ├── importacao.php          # /upload (Excel upload + parser)
│   ├── exportacao.php          # /exportar, /api/ultima-atualizacao (XLSX export)
│   ├── admin.php               # /api/admin/usuario* (user CRUD)
│   └── gpon_empresas.php       # /api/admin/gpon-empresas* (mapeamentos)
│
├── views/
│   ├── login.php               # Página de login (GET) + handler (POST)
│   ├── dashboard.php           # Página principal (/ ou /app)
│   ├── analise.php             # Página de análise (/analise)
│   └── admin.php               # Página de admin (/admin)
│
├── assets/
│   ├── css/
│   │   ├── gpon.css            # 2224 linhas: tema base + componentes
│   │   ├── analise.css         # CSS da página de análise
│   │   └── admin.css           # CSS da página de admin
│   │
│   └── js/
│       ├── gpon.js             # 2149 linhas: dashboard interativo
│       ├── analise.js          # 1768 linhas: página de análise
│       ├── analise-core.js     # Core da análise (processamento de dados)
│       ├── analise-dashboard.js# Dashboard de análise
│       ├── analise-filtros.js  # Filtros da análise
│       ├── analise-analitico.js# Visualizações analíticas
│       ├── analise-modais.js   # Modais da análise
│       ├── analise-rankings.js # Rankings de análise
│       └── pt-BR.json          # Tradução para português
│
├── uploads/                     # Diretório para uploads (não é usado atualmente)
└── vendor/                      # Dependências Composer

```

### Ponto de Entrada

**[index.php](index.php)**

- Carrega PDO via `config/database.php`
- Inicializa esquema via `includes/functions.php::gpon_init_db()`
- Inicia sessão via `includes/auth.php::gpon_session_start()`
- Rota requisição manualmente por URI e método HTTP
- Carrega arquivos `api/*.php` e `views/*.php` condicionalmente
- Executa handler ou renderiza template

---

## Banco de Dados

### Motor & Charset

- **MySQL 5.7+** (InnoDB)
- **Charset**: utf8mb4 (suporte a emoji)
- **Timezone BD**: UTC-3 ("SET time_zone = '-03:00'") — Brasília
- **DST**: Nenhum (MT e MS não têm horário de verão desde 2019)

### Tabelas Principais

#### 1. `usuarios`

Gestão de usuários do sistema.

| Campo         | Tipo                                  | Constraints                  | Descrição                      |
| ------------- | ------------------------------------- | ---------------------------- | ------------------------------ |
| id            | INT UNSIGNED                          | PK, AUTO_INCREMENT           |                                |
| nome          | VARCHAR(200)                          | NOT NULL                     | Nome completo do usuário       |
| usuario       | VARCHAR(100)                          | NOT NULL, UNIQUE             | Identificador único para login |
| senha         | VARCHAR(255)                          | NOT NULL                     | Hash bcrypt (password_hash)    |
| nivel         | ENUM('admin','operador','backoffice') | NOT NULL, DEFAULT 'operador' | Nível de acesso                |
| status        | TINYINT(1)                            | NOT NULL, DEFAULT 1          | 1=ativo, 0=desativado          |
| ultimo_acesso | DATETIME                              | NULL                         | Timestamp do último login      |
| created_at    | TIMESTAMP                             | DEFAULT CURRENT_TIMESTAMP    | Criação                        |

**Índices**: `idx_usuario_status` (usuario, status)

**Usuário Padrão** (criado na primeira execução):

- Usuário: `admin`
- Senha: `gpon@2024`
- Nível: `admin`
- ⚠️ **CRÍTICO**: Trocar senha na primeira execução!

#### 2. `ocorrencias`

Registro central de todas as ocorrências GPON.

| Campo                    | Tipo         | Índice                            | Descrição                                                                                      |
| ------------------------ | ------------ | --------------------------------- | ---------------------------------------------------------------------------------------------- |
| id                       | INT UNSIGNED | PK                                |                                                                                                |
| oc                       | VARCHAR(100) | UNIQUE                            | Número do tíquete (referência) — normalizado para dígitos; fallback: número alternativo ou '-' |
| ta                       | VARCHAR(100) |                                   | VTA PK (Plano de Teste Ativo)                                                                  |
| status                   | VARCHAR(100) | idx_status                        | Status da ocorrência (ex: 'Ativo', 'Fechado', 'etc')                                           |
| data_criacao             | DATETIME     | idx_criacao, idx_status_criacao   | Data de abertura                                                                               |
| data_encerramento        | DATETIME     |                                   | Data de encerramento (NULL = em aberto)                                                        |
| previsao_finalizacao     | DATETIME     |                                   | Previsão do técnico para finalização                                                           |
| gpon                     | VARCHAR(100) | idx_gpon, idx_gpon_splitters      | Sigla do site V2 (ex: 'MTCBA01')                                                               |
| afetacao                 | TEXT         |                                   | Serviço FTTX afetado (ex: 'Telefonia', 'Internet')                                             |
| splitters                | VARCHAR(200) | idx_splitters, idx_gpon_splitters | Splitters nível 1 (ex: 'SP9,SP24,SP28') — formatado                                            |
| splitters_nivel2         | VARCHAR(200) |                                   | Splitters nível 2 (ex: 'SP142') — formatado                                                    |
| baixa_reparo             | TEXT         |                                   | O que foi feito no reparo                                                                      |
| baixa_causa              | TEXT         |                                   | Causa raiz do problema                                                                         |
| codigo_baixa_componente  | VARCHAR(200) |                                   | Código do componente defeituoso                                                                |
| codigo_baixa_defeito     | VARCHAR(200) |                                   | Código do tipo de defeito                                                                      |
| empresa                  | VARCHAR(200) | idx_empresa                       | Empresa de manutenção (ex: 'ABILITY', 'ONDACOM')                                               |
| localidade               | VARCHAR(200) | idx_localidade                    | Localidade (cidade) do problema — normalizada                                                  |
| uf                       | CHAR(2)      | idx_uf, idx_uf_status             | Estado (UF) derivado da localidade — chave para análise regional                               |
| observacoes_operacionais | TEXT         |                                   | Anotações operacionais                                                                         |
| repetida                 | TINYINT(1)   | idx_repetida                      | Flag: 1=reincidência detectada, 0=não                                                          |
| aging_encerrados         | INT          |                                   | Tempo em horas do fechamento (calculado: data_encerramento - data_criacao)                     |
| created_at               | TIMESTAMP    |                                   | Quando foi inserido no BD                                                                      |
| updated_at               | TIMESTAMP    | ON UPDATE                         | Última atualização                                                                             |

**Índices**:

- `idx_gpon`
- `idx_status`, `idx_status_criacao`
- `idx_empresa`
- `idx_localidade(100)` — prefixo porque TEXT
- `idx_criacao`
- `idx_splitters(100)`
- `idx_repetida`
- `idx_uf_status`
- `idx_gpon_splitters` — composite para análise de reincidência

#### 3. `historico_ocorrencias`

Auditoria: todas as alterações, comentários e histórico.

| Campo          | Tipo                                                           | Índice      | Descrição                                                                |
| -------------- | -------------------------------------------------------------- | ----------- | ------------------------------------------------------------------------ |
| id             | INT UNSIGNED                                                   | PK          |                                                                          |
| ocorrencia_id  | INT UNSIGNED                                                   | idx_oc_id   | FK → ocorrencias.id                                                      |
| oc             | VARCHAR(100)                                                   | idx_oc      | Número do tíquete (desnormalizado para query rápida)                     |
| campo          | VARCHAR(100)                                                   |             | Qual campo foi alterado (ex: 'status', 'empresa') — NULL para comentário |
| valor_anterior | TEXT                                                           |             | Valor antes da alteração                                                 |
| valor_novo     | TEXT                                                           |             | Valor depois da alteração                                                |
| tipo           | ENUM('comentario','edicao','importacao','exclusao','previsao') | idx_oc_tipo | Tipo de registro                                                         |
| texto          | TEXT                                                           |             | Conteúdo (comentário, descrição da edição, etc)                          |
| usuario_id     | INT UNSIGNED                                                   |             | FK → usuarios.id (quem fez)                                              |
| usuario_nome   | VARCHAR(200)                                                   |             | Nome do usuário (desnormalizado)                                         |
| created_at     | TIMESTAMP                                                      |             | Timestamp                                                                |

**Índices**:

- `idx_oc_id`
- `idx_oc`
- `idx_oc_tipo` — composite para queries de histórico por tipo

#### 4. `importacoes`

Log de todas as importações de Excel.

| Campo        | Tipo         | Índice         | Descrição                          |
| ------------ | ------------ | -------------- | ---------------------------------- |
| id           | INT UNSIGNED | PK             |                                    |
| arquivo      | VARCHAR(255) |                | Nome original do arquivo           |
| total_linhas | INT          |                | Linhas lidas da planilha           |
| inseridos    | INT          |                | OCs novas criadas                  |
| atualizados  | INT          |                | OCs atualizadas (ON DUPLICATE KEY) |
| erros        | INT          |                | Linhas com erro                    |
| usuario_id   | INT UNSIGNED |                | FK → usuarios.id                   |
| usuario_nome | VARCHAR(200) |                | Nome desnormalizado                |
| created_at   | TIMESTAMP    | idx_created_at | Timestamp da importação            |

#### 5. `gpon_empresas`

Mapeamento GPON ↔ Empresa (substitui array PHP em `config/gpon_empresa_map.php`).

| Campo         | Tipo         | Índice                    | Descrição                   |
| ------------- | ------------ | ------------------------- | --------------------------- |
| id            | INT UNSIGNED | PK                        |                             |
| gpon          | VARCHAR(100) | UNIQUE idx_gpon           | Código GPON (ex: 'MTCBA01') |
| empresa       | VARCHAR(50)  |                           | Empresa de manutenção       |
| criado_em     | TIMESTAMP    | DEFAULT CURRENT_TIMESTAMP |                             |
| atualizado_em | TIMESTAMP    | ON UPDATE                 |                             |

#### 6. `schema_version`

Versionamento de migrações.

| Campo      | Tipo         | Descrição               |
| ---------- | ------------ | ----------------------- |
| version    | INT UNSIGNED | Versão atual do esquema |
| updated_at | TIMESTAMP    | Quando foi executada    |

**Versão Atual**: 5

---

## Fluxo de Autenticação

### 1. Login

**Rota**: `POST /login`

1. Usuário submete formulário `views/login.php` com `usuario` e `senha`
2. `gpon_handle_login($pdo)` processa:
   - Valida campos não-vazios
   - **Rate limiting**: bloqueia após 5 tentativas incorretas por 5 minutos
   - Consulta `SELECT * FROM usuarios WHERE usuario=? AND status=1`
   - Valida senha com `password_verify($entrada, $hash_bd)`
   - Store em `$_SESSION`: `gpon_user_id`, `gpon_nome`, `gpon_usuario`, `gpon_nivel`
   - Atualiza `usuarios.ultimo_acesso = NOW()`
   - Redireciona para `/` (dashboard)

### 2. Sessão

**Arquivo**: `includes/auth.php::gpon_session_start()`

```php
session_set_cookie_params([
    'lifetime' => 0,                  // Browser-only (sem expiration)
    'path'     => '/',
    'secure'   => $https,             // HTTPS only (condicional)
    'httponly' => true,               // JS não acessa cookies
    'samesite' => 'Strict',           // CSRF protection básica
]);
ini_set('session.use_strict_mode', '1');
```

- **Lifetime**: 0 = cookie de sessão (expira ao fechar navegador)
- **SameSite=Strict**: rejeita cookies em requisições cross-site
- **HTTPOnly**: previne acesso via JavaScript

### 3. Verify User

**Função**: `gpon_current_user()` → retorna array com `id`, `nome`, `usuario`, `nivel` ou `null`

### 4. Logout

**Rota**: `GET /logout`

```php
session_unset();
session_destroy();
```

Redireciona para `/login`.

---

## Permissões e Autorização

### Níveis de Usuário

| Nível        | Descrição       | Permissões                                                                |
| ------------ | --------------- | ------------------------------------------------------------------------- |
| `admin`      | Administrador   | Tudo (CRUD ocorrências, admin de usuários, mapeamentos GPON, histórico)   |
| `operador`   | Operador padrão | Ver dashboard, importar, exportar, CRUD ocorrências (próprias), histórico |
| `backoffice` | Backoffice      | Ver análise, histórico, mapeamentos GPON (read-only)                      |

### Funções de Verificação

Todas em `includes/auth.php`:

- `gpon_require_login()` → redireciona se não autenticado
- `gpon_require_admin()` → erro 403 se não admin
- `gpon_require_admin_or_backoffice()` → error 403 se operador
- `gpon_require_admin_or_backoffice_api()` → versão JSON (para APIs)
- `gpon_is_admin()` → boolean
- `gpon_is_backoffice()` → boolean
- `gpon_is_admin_or_backoffice()` → boolean

### Padrão de Autorização nas APIs

```php
function gpon_api_exemplo() {
    gpon_require_admin();  // Garante admin
    // Resto da lógica
}
```

⚠️ **PROBLEMA IDENTIFICADO**: Nem todos os endpoints checam nível; alguns apenas verificam login.

---

## APIs e Endpoints

### 1. Dashboard (`api/dashboard.php`)

#### `GET /api/data`

Retorna tabela de ocorrências abertas/fechadas com filtros.

**Parâmetros**:

- `incluir_encerradas` (param) — 0/1 (padrão: 0 = apenas ativas)
- `ocultar_fibrasil` (param) — 0/1 (oculta empresa FIBRASIL)
- `empresa` (param) — valores separados por `|||`
- `localidade` (param) — valores separados por `|||`
- `gpon` (param) — valores separados por `|||`
- `uf` (param) — valores separados por `|||`
- `status_prazo` (param) — ex: `Dentro do Prazo|||Fora do Prazo`

**Resposta**:

```json
{
  "ok": true,
  "rows": [
    {
      "id": 1,
      "oc": "12345",
      "status": "Ativo",
      "data_criacao": "2026-06-28 14:30:00",
      "status_prazo": "Dentro do Prazo",
      "aging_abertos": 120, // minutos
      "reincidencia_max": 3,
      "reincidencia_detail": [{ "sp": "SP24", "cnt": 5 }],
      "reincidencia_recente": { "tipo": "critica", "horas": 18 }
    }
  ],
  "total": 150
}
```

#### `GET /api/stats`

Retorna KPIs: totais por status, breaking por empresa, reincidência, etc.

#### `GET /api/filters`

Retorna valores únicos de cada filtro (uf, empresa, localidade, gpon, status_prazo).

#### `GET /api/reinc-counts`

Retorna contagens de reincidência por combinação GPON+Splitter.

### 2. Análise (`api/analise.php`)

#### `GET /api/analise`

Análise geral de reincidência com filtros de período, UF, GPON, etc.

**Parâmetros**:

- `periodo` (query) — '', '24h', 'hoje', '7d', '30d', 'custom'
- `inicio`, `fim` (query) — para 'custom' (YYYY-MM-DD)
- `uf`, `gpon`, `sp`, `baixa_causa` (query)
- `ocultar_improcedentes`, `ocultar_fibrasil` (query)

**Resposta**: Análise completa com rankings, tendências, etc.

#### `GET /api/analise/historico`

Histórico de ocorrências para um GPON+SP específico.

#### `GET /api/analise/analitico`

Evolução analítica (tendências de 12 meses por GPON).

#### `GET /api/analise/resumo`

Resumo consolidado de análise.

### 3. Ocorrências (`api/ocorrencias.php`)

#### `GET /api/ocorrencia/{id}`

Retorna uma ocorrência com campos enriquecidos.

#### `PUT /api/ocorrencia/{id}`

Atualiza ocorrência. Registra alterações em `historico_ocorrencias`.

**Body** (JSON):

```json
{
  "status": "Fechado",
  "data_encerramento": "2026-07-03 16:00",
  "empresa": "ABILITY",
  ...
}
```

#### `DELETE /api/ocorrencia/{id}`

Exclui (apenas admin/backoffice). Registra em histórico.

### 4. Histórico (`api/historico.php`)

#### `GET /api/historico/{id}`

Retorna histórico (comentários, edições) de uma ocorrência.

#### `POST /api/historico/{id}`

Adiciona comentário.

**Body** (JSON):

```json
{ "texto": "Comentário do usuário" }
```

#### `PUT /api/historico-item/{id}`

Edita comentário.

#### `DELETE /api/historico-item/{id}`

Exclui comentário.

#### `PUT /api/previsao/{id}`

Define ou atualiza previsão de finalização.

**Body** (JSON):

```json
{ "previsao_finalizacao": "2026-07-10 18:00" }
```

### 5. Import/Export

#### `POST /upload` (Content-Type: multipart/form-data)

Upload de arquivo Excel (.xlsx / .xls).

1. Valida extensão e magic bytes (ZIP/OLE2)
2. Carrega com PhpSpreadsheet
3. Mapeia cabeçalhos via `gpon_map_header()`
4. Insere/atualiza via `INSERT ... ON DUPLICATE KEY UPDATE`
5. Registra em `importacoes`
6. Retorna resumo (inseridos, atualizados, erros)

#### `GET /exportar`

Exporta ocorrências filtradas para XLSX.

#### `GET /api/ultima-atualizacao`

Retorna timestamp da última importação.

### 6. Admin (`api/admin.php`)

#### `GET /api/admin/usuarios`

Lista usuários (admin only).

#### `POST /api/admin/usuarios`

Cria usuário (admin only).

#### `GET /api/admin/usuario/{id}`

Detalhe de usuário.

#### `PUT /api/admin/usuario/{id}`

Atualiza usuário.

#### `DELETE /api/admin/usuario/{id}`

Exclui usuário (não permite auto-exclusão).

### 7. GPON Empresas (`api/gpon_empresas.php`)

#### `GET /api/admin/gpon-empresas`

Lista mapeamentos.

#### `POST /api/admin/gpon-empresas`

Cria mapeamento (admin/backoffice).

#### `PUT /api/admin/gpon-empresas/{id}`

Atualiza mapeamento.

#### `DELETE /api/admin/gpon-empresas/{id}`

Exclui mapeamento.

#### `GET /api/gpon-nao-mapeados`

Retorna GPONs que não têm mapeamento em `gpon_empresas`.

### Padrão de Resposta JSON

```json
{
  "ok": true|false,
  "data": {...},
  "message": "string (erro ou sucesso)",
  "rows": [...],
  "total": 100,
  "error": "string (alternativa a message)"
}
```

---

## Frontend

### Arquitetura

**Padrão**: Procedural + jQuery + vanilla JS  
**Bibliotecas**:

- Bootstrap 5.3.3
- jQuery (implícito em DataTables)
- DataTables 1.13.8 (tabelas)
- SweetAlert2 (modais)
- Bootstrap Icons 1.11.3

### Páginas Principais

#### 1. Login (`views/login.php`)

- Formulário simples: usuário + senha
- Exibe erros via flash message
- POST → `gpon_handle_login($pdo)`

#### 2. Dashboard (`views/dashboard.php`)

**JavaScript**: `assets/js/gpon.js` (2149 linhas)

Componentes:

- **Topbar**: logo, título, botões (analise, importar), user badge
- **Sidebar**: filtros (UF, Empresa, Localidade, GPON, Status Prazo)
- **Tabela DataTables**: ocorrências com reincidência, aging, status prazo
- **Modais**:
  - Upload de Excel
  - Edição de ocorrência
  - Visualização de histórico
  - Previsão de finalização

Fluxo:

1. Página carrega com `BASE_PATH` injetado em `<script>`
2. JS faz AJAX → `/api/filters` (carrega valores de filtro)
3. JS carrega tabela com `/api/data`
4. Usuário altera filtro → query string + AJAX
5. Clica em ocorrência → abre modal com `/api/ocorrencia/{id}`

#### 3. Análise (`views/analise.php`)

**JavaScript**: `assets/js/analise.js` (1768 linhas) + submódulos

Componentes:

- **Barra de filtros**: período, UF, GPON, SP, causas
- **Rankings**: SPs mais recorrentes, GPONs top, etc
- **Heatmap**: ocorrências por dia/horário (opcional)
- **Timeline**: sequência de OCs para combo selecionada
- **Gráficos**: tendências mensais, MTTR, distribuição

#### 4. Admin (`views/admin.php`)

**Acesso**: admin only

Recursos:

- **Usuários**: CRUD (tabela + modal)
- **Mapeamentos GPON**: CRUD
- **Histórico de Importações**: log readOnly
- **Admin do Sistema**: logs, migração de dados (se aplicável)

### CSS

**Arquivos**:

- `assets/css/gpon.css` (2224 linhas) — tema base, topbar, sidebar, componentes gerais
- `assets/css/analise.css` — overrides/específicos da página de análise
- `assets/css/admin.css` — overrides/específicos da página de admin

**Tema**: Purple (primário #7c3aed) + Cyan (accent #0ea5e9)

**Variáveis CSS** (em `:root`):

```css
--gpon-primary: #7c3aed;
--gpon-sidebar-w: 260px;
--gpon-header-h: 56px;
--shadow-md: 0 4px 16px rgba(124, 58, 237, 0.12);
--radius-md: 10px;
```

**Componentes reutilizáveis**:

- `.gpon-topbar` — barra fixa no topo
- `.sf-card` — card de filtro (sidebar)
- `.badge-status` — status com cores
- `.badge-dentro-prazo`, `.badge-fora-prazo`, etc
- `.flash-msg`, `.flash-toast` — notificações

### JavaScript — Padrão Modular

**Arquivo**: `assets/js/gpon.js`

Usa IIFE (Immediately Invoked Function Expression):

```javascript
const GPON = (() => {
  const state = {
    /* estado compartilhado */
  };
  const SF_CONFIG = [
    /* configuração de filtros */
  ];

  function flash(msg, type, duration) {
    /* ... */
  }
  function formatAging(mins) {
    /* ... */
  }

  // Público
  return {
    init: function () {
      /* inicializa */
    },
    loadFilters: function () {
      /* ... */
    },
    applyFilters: function () {
      /* ... */
    },
    // ... outros métodos
  };
})();

document.addEventListener("DOMContentLoaded", () => {
  GPON.init();
});
```

**Convenções**:

- Prefixo `gpon_` em funções PHP
- Classe CSS `.gpon-*` para componentes
- ID `#gpon-*`, `#sf-*` para elementos-chave
- `data-*` attributes para metadata (ex: `data-ocorrencia-id`)

---

## Regras de Negócio

### SLA (Service Level Agreement)

Definidas em `index.php` (constantes):

```php
define('GPON_SLA_HORAS',           8);     // Prazo SLA
define('GPON_SLA_PROXIMO_HORAS',   6);     // Limite "Atenção"
define('GPON_REPETIDA_JANELA',     90);    // Dias para considerar reincidência
```

**Status Prazo**:

- **Dentro do Prazo**: encerrada em ≤ 8h OU aberta por < 6h
- **Atenção**: aberta há 6-8h
- **Fora do Prazo**: aberta > 8h OU encerrada > 8h

Calculado por `gpon_status_prazo()` (função helpers).

### Reincidência

**Definição**: Mesmo GPON + mesmos Splitters + Localidade = reincidência

Detectada por:

1. `gpon_update_repetidas()` — executa após import/edição
2. `gpon_reincidencia_recente_map()` — em dashboard, marca ocorrências ativas com reincidência < 72h

**Crítica**: reincidência fechada < 24h antes da ativa atual
**Recente**: reincidência fechada 24-72h antes

### Normalização de Dados

Ao importar ou editar:

1. **OC** (Tíquete Referência): normaliza para dígitos; fallback = número alternativo ou '-'
2. **Splitters**: extrai códigos `SP\d+`, deduplica, formata como `SP9,SP24,SP28`
3. **Localidade**: busca mapeamento em `gpon_localidade_uf()` para derivar UF
4. **Empresa**:
   - Prioridade 1: valor informado
   - Prioridade 2: mapeamento de localidade (`gpon_empresa_por_localidade()`)
   - Prioridade 3: mapeamento de GPON (`gpon_empresa_por_gpon()`)
   - Fallback: '-'
5. **Data**: `gpon_planilha_para_utc()` converte de serial Excel ou texto para ISO 8601

### Timezone

- **BD**: UTC-3 ("SET time_zone = '-03:00'")
- **Exibição**: Brasília local (sem DST desde 2019)
- **PHP**: `date_default_timezone_set('America/Sao_Paulo')`
- **JS**: Sem conversão explícita (assume BD já em Brasília)

### Mapeamento Localidade → UF

Centralizado em `gpon_localidade_uf()`:

- **MT**: ~50 municípios
- **MS**: ~50 municípios
- **AC** (Acre) → mapeado como **MT** (regra operacional)
- **RO** (Rondônia) → mapeado como **MS** (regra operacional)
- **DF**: ~10 regiões
- **GO**: ~15 cidades

Se não encontrar na lista → `null` → ocorrência ignorada em import

---

## Convenções de Código

### PHP

**Nomes de Funções**:

- `gpon_*` — funções públicas (helpers, API handlers)
- `gpon_api_*` — handlers de endpoint
- `gpon_handle_*` — handlers de ação (login, upload)
- `gpon_require_*` — verificações de segurança
- `gpon_render_*` — renderização de views
- `gpon_enrich_*` — enriquecimento de dados
- `gpon_calc_*` — cálculos

**Tipos e Hints**:

- Declaração de tipos: `declare(strict_types=1)` em arquivos novos
- Parâmetros tipo: `function foo(PDO $pdo, array $user, string $valor): ?string`
- Type hints nullable: `?string`, `?int`

**Prepared Statements**: Sempre usar placeholders `?` ou `:named`

**Constantes**: UPPERCASE com prefixo `GPON_`

### JavaScript

**Nomes de Variáveis**: camelCase

```javascript
const myVariable = 123;
function doSomething() {}
```

**Seletores CSS**: kebab-case

```javascript
document.querySelector("#my-element");
```

**Convenção de ID**:

- `#gpon-*` — Dashboard
- `#sf-*` — Sidebar Filtros
- `#modal-*` — Modais
- `#analise-*` — Análise

**Escopo**: IIFE para encapsulamento

### SQL

**Estilos**:

- Keywords UPPERCASE: `SELECT`, `FROM`, `WHERE`
- Nomes de coluna/tabela: backtick (`` ` ``)
- Índices: `idx_*`
- Constraints: `UNIQUE`, `PRIMARY KEY`, `FOREIGN KEY`

---

## Problemas Identificados

### 🔴 Críticos (Bloqueadores)

1. **Credenciais Codificadas** (`config/database.php`)
   - Senha padrão em código
   - Sem suporte a .env
   - **Risco**: Vazamento em repositório público

2. **Sem CSRF** (POST/PUT/DELETE endpoints)
   - Endpoints de mutação sem token CSRF
   - **Risco**: Ataque CSRF em operações críticas

3. **Autorização Incompleta**
   - Alguns endpoints checam apenas `gpon_require_login()`, não nível
   - Ex: DELETE de ocorrência deveria ser admin/backoffice
   - **Risco**: Operador pode deletar dados

### 🟠 Altos (Impactam Manutenção/Performance)

4. **Monolito `includes/functions.php`** (1283 linhas)
   - Mistura: schema, helpers, análise, import, export, histórico
   - **Impacto**: Difícil navegar, refatorar, testar

5. **Duplicação de Filtras SQL**
   - `api/dashboard.php`, `api/analise.php`, `api/exportacao.php` reconstroem WHERE
   - **Impacto**: Manutenção em 3+ lugares

6. **`gpon.js` Grande** (2149 linhas)
   - Tudo em 1 arquivo
   - **Impacto**: Sem modularização, difícil testar

7. **Sem Composer.lock**
   - Dependências não lockadas
   - **Risco**: Deploy não reproduzível

### 🟡 Médios (UX/Performance)

8. **Roteamento Manual** (`index.php`)
   - 200+ linhas de `if/preg_match`
   - **Impacto**: Difícil adicionar rotas

9. **Sem Transações Explícitas**
   - Import pode falhar parcialmente
   - **Impacto**: Estado inconsistente

10. **TIMESTAMPDIFF em WHERE**
    - `gpon_status_prazo_sql()` força full table scan
    - **Impacto**: Performance em milhões de registros

11. **Sem Cache**
    - Filtros carregados em cada request
    - Contagens recalculadas sempre
    - **Impacto**: N+1 queries

12. **Hard-coded Localidades**
    - Mapa de UF em PHP estático (600+ linhas)
    - Não escalável
    - **Impacto**: Manutenção tediosa

### 🔵 Baixos (Nice-to-Have)

13. **Sem Type Hints Completos** (PHP)
    - Mix de tipado e dinâmico
    - **Impacto**: IDE autocomplete ruim

14. **Sem Testes Automatizados**
    - Sem PHPUnit / Jest
    - **Impacto**: Regressões não detectadas

15. **CSS Temas Duplicados**
    - `gpon.css` + `analise.css` + `admin.css` repetem estilos
    - **Impacto**: Bundle grande, manutenção

---

## Backlog Técnico Priorizado

### Sprint 1: Segurança (P1)

- [ ] **1.1 - Move credenciais para .env**
  - Arquivo: `config/database.php`
  - Impacto: Mitiga vazamento em repositório
  - Esforço: 1h

- [ ] **1.2 - Add CSRF tokens**
  - Arquivo: `index.php`, todas `views/*.php`, `api/*.php`
  - Impacto: Previne ataque CSRF em POST/PUT/DELETE
  - Esforço: 3h

- [ ] **1.3 - Reforça autorização**
  - Arquivo: `api/*.php` (audit + fix)
  - Impacto: Operador não consegue deletar/editar dados restritos
  - Esforço: 2h

### Sprint 2: Refatoração Backend (P1)

- [ ] **2.1 - Quebra `includes/functions.php`**
  - Mover em: `includes/schema.php`, `includes/analysis.php`, `includes/import.php`, `includes/export.php`, `includes/helpers.php`
  - Impacto: Código mais navegável, facilitaria teste unitário
  - Esforço: 4h

- [ ] **2.2 - Centraliza filtros SQL**
  - Arquivo: `includes/query-builder.php` (novo)
  - Função: `build_ocorrencia_where(array $filters)` — reutilizável
  - Impacto: Single source of truth para WHERE
  - Esforço: 2h

- [ ] **2.3 - Melhora roteamento**
  - Arquivo: `routes.php` (novo) — array de rotas ou classe Router simples
  - Impacto: Mais claro, facilita adicionar rotas
  - Esforço: 2h

### Sprint 3: Performance & Dados (P2)

- [ ] **3.1 - Add composer.lock**
  - Comando: `composer install`
  - Impacto: Deploy reproduzível
  - Esforço: 0.5h

- [ ] **3.2 - Otimiza TIMESTAMPDIFF**
  - Arquivo: migração BD (adiciona coluna `aging_minutos` calculada)
  - Impacto: Queries status_prazo mais rápidas
  - Esforço: 2h

- [ ] **3.3 - Move localidades para banco**
  - Arquivo: nova tabela `localidades` (id, nome, uf)
  - Impacto: Dados centralizados, fácil manutenção
  - Esforço: 3h

- [ ] **3.4 - Add transações no import**
  - Arquivo: `api/importacao.php`
  - Impacto: Rollback em erro = estado consistente
  - Esforço: 1h

### Sprint 4: Frontend Modularização (P2)

- [ ] **4.1 - Componentiza CSS**
  - Arquivo: `assets/css/components/` (botões, cards, badges, etc)
  - Impacto: Bundle menor, reutilização
  - Esforço: 2h

- [ ] **4.2 - Quebra `gpon.js`**
  - Mover em: `assets/js/modules/` (filters, table, modals, flash, etc)
  - Impacto: Código modular, testável
  - Esforço: 3h

- [ ] **4.3 - Melhora análise.js**
  - Refazer: organizar submódulos, aplicar DRY, adicionar comentários
  - Impacto: Manutenção mais fácil
  - Esforço: 2h

### Sprint 5: Quality (P3)

- [ ] **5.1 - Add PHPUnit**
  - Arquivo: `tests/FunctionTest.php` (começar com alguns helpers)
  - Impacto: Confiança em refatorações
  - Esforço: 3h

- [ ] **5.2 - Add type hints**
  - Audit: todos `includes/*.php`, `api/*.php`
  - Impacto: IDE melhor, código mais legível
  - Esforço: 4h

- [ ] **5.3 - Documentação de API**
  - Arquivo: `API.md` (OpenAPI / Swagger)
  - Impacto: Fácil integração de outros sistemas
  - Esforço: 2h

---

## Próximos Passos

1. **Aprovação deste plano** pela stakeholder
2. **Priorização**: validar se Sprint 1 (segurança) é crítica
3. **Branching strategy**: criar `develop` + features
4. **CI/CD**: antes de implementar qualquer sprint
5. **Testes**: escrita de testes para novas features + refatoração

---

**Fim da Documentação — Engenharia Reversa Completa**

Próximos passos: Aguardar aprovação para iniciar Sprints.
