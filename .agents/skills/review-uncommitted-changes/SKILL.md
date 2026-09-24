---
name: review-uncommitted-changes
description: 'Analisa arquivos e alterações de código (git diff, staged, unstaged, untracked ou diff específico de commit/branch/PR) no projeto sIAc3. Acione sempre que o usuário pedir para revisar arquivos modificados, analisar diferenças antes de comitar, analisar commit específico, realizar code review de alterações pendentes, checar regras de negócio, segurança, arquitetura feature-based, limite de linhas (max 800) ou conformidade de testes e build, categorizando os apontamentos por níveis de criticidade (Crítico, Importante, Sugestão).'
license: MIT
metadata:
    author: siac3
---

# Análise de Alterações de Código (sIAc3 Code Review)

Esta skill orienta a auditoria e o code review profundo de alterações no projeto **sIAc3** (Laravel 13, Inertia.js 3, Vue 3, Tailwind CSS 4, TypeScript, Wayfinder e Pest 4) — abrangendo arquivos pendentes de commit (staged, unstaged, untracked) ou diffs específicos de commits, branches e PRs. Ela avalia conformidade arquitetural feature-based, limites de tamanho de arquivo, segurança, integridade de regras de negócio, contratos backend/frontend, testes, build e formatação, categorizando todos os achados em **níveis de criticidade claros**.

---

## Quando Ativar

Acione esta skill imediatamente quando o usuário solicitar:
- "Analise os arquivos não comitados" / "Revisar git diff"
- "Faça um code review das alterações pendentes"
- "Veja se está tudo certo antes de comitar"
- "Audite os arquivos modificados para falhas de segurança e regras de negócio"
- "Traga os pontos de melhoria das minhas alterações em níveis de criticidade"
- "Analise o commit", "Analise o commit <hash>", "Revise a branch <branch>" ou PR específico.
- Qualquer pedido de inspeção de alterações (staged, unstaged, untracked ou diff de commit/branch).

---

## Fluxo de Execução da Análise

### Passo 1: Mapear o Escopo das Alterações
Execute e inspecione o estado atual do repositório ou o diff indicado:
1. **Alterações locais pendentes (padrão)**:
   - `git status --short` — lista arquivos modificados (`M`), adicionados (`A`), removidos (`D`), renomeados (`R`) e não rastreados (`??`).
   - `git diff` — analisa alterações não preparadas (unstaged).
   - `git diff --cached` (ou `git diff --staged`) — analisa alterações preparadas para commit.
   - Para arquivos novos não rastreados (`??`), inspecione o conteúdo completo do arquivo.
2. **Commit, Branch ou PR específico (quando indicado)**:
   - `git show <hash>` / `git diff <hash>~1..<hash>` — inspeciona o commit informado.
   - `git diff main...<branch>` (ou `origin/main...HEAD`) — analisa a branch ou diff do PR correspondente.

---

## Matriz de Níveis de Criticidade

Todos os apontamentos encontrados devem ser categorizados em um dos 3 níveis abaixo:

### 🔴 Crítico / Bloqueante (P0 — Não comitar sem corrigir)
Problemas que causam falhas de segurança, corrompem dados, violam gravemente a arquitetura ou quebram o sistema em execução:
- **Segurança**: Injeção de SQL (concatenação em `DB::raw`), endpoints/ações sem checagem de autorização/Policy (`$this->authorize()`, Form Request `authorize()`), bypass de middleware de tenant/auth, vazamento de credenciais ou dados protegidos em props Inertia.
- **Integridade de Dados e Negócio**: Mutações em múltiplas tabelas/registros sem `DB::transaction()`, quebra de regras essenciais de negócio ou fluxo de status.
- **Corrupção de Estado por Omissão (Round-trip Data Loss)**: Omissão de campos em whitelists ou na inicialização de formulários (`useForm`, `.map()`) que descartam ou resetam propriedades válidas ao carregar telas de edição.
- **Quebra de Execução / Contratos**: Divergência fatal de props entre backend (`Inertia::render`) e frontend (`defineProps`), erros fatais de tipagem/runtime.
- **Falso Positivo de Testes / Falha Silenciosa de Suíte**: Arquivos de teste com sintaxe/cabeçalho inválido (ex: ausência de `<?php`), ou suítes que terminam com `0 tests` / `No tests found` mascarando ausência real de execução.
- **Violação Arquitetural Severa**: Mover arquivos do framework (Models, Jobs, Commands, Providers) para dentro de pastas de features ou acoplar componentes externos a internals restritos de outras features.

### 🟡 Importante / Atenção (P1 — Fortemente recomendado corrigir)
Problemas que geram degradação de performance, dívida técnica imediata ou riscos sob condições de contorno:
- **Validação Condicional Frouxa e Estados Zumbi**: Campos dependentes de enums/discriminadores declarados como `nullable` sem validação condicional (`required_if` / validação de integridade no payload), permitindo persistir registros que nunca funcionam em runtime.
- **Tamanho de Arquivo (Limite de 800 Linhas)**: Arquivo novo ou modificado excedendo ou muito próximo de 800 linhas, necessitando de divisão modular.
- **Performance de Banco de Dados**: Consultas N+1 evidentes em loops, falta de `with()` / eager loading (`loadMissing()`, `withCount()`), queries sem paginação ou chunking.
- **Cobertura e Paridade de Testes**: Classes de serviço, controllers ou endpoints novos/modificados sem testes automatizados correspondentes no Pest, ou remoção inadvertida de casos de testes anteriores.
- **Encapsulamento de Módulos e Wayfinder**: Importações diretas de internals de outras features em vez de abstrações em `@/shared`, URLs manuais ignorando o Wayfinder (`@/routes` ou `@/actions`), ou edição manual de arquivos gerados pelo Wayfinder.
- **Tratamento de Nulos / Edge Cases**: Falta de verificação de nulos em retornos opcionais que podem gerar falha em casos específicos.

### 🔵 Sugestão / Melhoria Contínua (P2 — Refinamento e boas práticas)
Ajustes de qualidade, legibilidade e conformidade estética:
- **Estilo e Formatação**: Código PHP precisando de `vendor/bin/pint --dirty --format agent`, ou ajustes de formatação Prettier (`npm run format`) / ESLint (`npm run lint`).
- **Refatoração & Legibilidade**: Nomenclatura mais clara, simplificação de fluxos condicionais, remoção de código morto ou duplicado.
- **Tipagem Expressiva**: Definição de array shapes em PHPDocs (`array{id: int, name: string}`), tipos TypeScript mais estritos sem `any`.
- **Acessibilidade e Micro-UX**: Melhores feedbacks visuais em componentes, estados de loading/disabled ou acessibilidade em formulários.

---

## Dimensões de Auditoria (Checklist Técnico sIAc3)

Ao analisar cada arquivo e trecho alterado, avalie rigorosamente:

### 1. Estrutura e Localização de Arquivos (Padrões sIAc3)
Consulte `app/Features/README.md`, `README.md` e `AGENTS.md`:
- **Backend (`app/Features/<Feature>`)**:
  - Código funcional, controllers, services, actions, requests, policies, rules e rotas devem residir em `app/Features/<Feature>/...`.
  - Namespace correspondente: `App\Features\<Feature>\...`.
  - Rotas funcionais residem em `app/Features/<Feature>/Routes/`. Os arquivos em `routes/*.php` atuam apenas como agregadores.
  - Models (`app/Models`), Jobs (`app/Jobs`), Commands (`app/Console/Commands`), Providers (`app/Providers`), Middleware global (`app/Http/Middleware`), customizações do Fortify (`app/Actions/Fortify`, `app/Http/Responses`) e `database/*` NÃO devem ser movidos para dentro de features.
  - Integrações técnicas compartilhadas sem posse funcional ficam em `app/Infrastructure`.
  - Não crie DTOs, Repositories ou Interfaces desnecessárias por mera simetria.
- **Frontend (`resources/js/`)**:
  - `resources/js/app/`: shell, cabeçalho, sidebar, navegação e layouts globais (`layouts/`).
  - `resources/js/features/<feature>/`: implementação real de telas (`pages/`), componentes (`components/`), composables (`composables/`) e tipos (`types/`).
  - `resources/js/shared/`: componentes genéricos reutilizáveis (`components/`), primitivos de UI (`components/ui`), composables, utilitários (`lib/`) e contratos compartilhados. `shared` nunca importa de `app` ou `features`.
  - `resources/js/pages/`: adaptadores finos Inertia que apenas importam e default-exportam a página correspondente em `resources/js/features/<feature>/pages/`. Não adicione lógica, requests, estado ou templates nos adaptadores.
  - Wayfinder: utilize funções geradas em `@/routes` (rotas nomeadas) ou `@/actions` (controllers). Nunca hardcode URLs de backend e nunca edite manualmente `resources/js/actions` ou `resources/js/routes`.
  - Componentes Vue: devem possuir um elemento raiz único (*single root element*).

### 2. Tamanho e Complexidade de Arquivos (<= 800 Linhas)
- Verifique o total de linhas de cada arquivo modificado ou criado.
- Caso o arquivo passe de 800 linhas, aponte imediatamente com nível 🟡 **Importante** e sugira a extração de Services, Actions, Subcomponentes ou Composables específicos.

### 3. Segurança e Vulnerabilidades
- Sem queries concatenadas em `DB::raw()`.
- Endpoints sensíveis protegidos por autorização (`$this->authorize()`, Policy, Gate, Form Request `authorize()`).
- Sem `Model::create($request->all())` não validado.
- Evitar `v-html` inseguro no Vue sem sanitização.
- Sem segredos, credenciais ou tokens em texto plano.
- Props do Inertia não devem expor dados protegidos (hashes, tokens sensíveis, colunas confidenciais).

### 4. Regras de Negócio, Integridade e Performance
- **Ciclo de Vida de Dados e Formulários (Prop-to-Form Round-Trip)**:
  - Rastreie o ciclo completo de qualquer novo campo adicionado: `Inertia Props -> useForm (init e mapeamentos) -> Componentes e inputs -> Normalização/Payload -> Request Validation -> Database`.
  - Inspecione mapeamentos manuais de `props` para `form` (ex: `(props.entity.items || []).map(...)`) ou desestruturações explícitas. Se novos campos foram adicionados a types ou backend, garanta que todos os pontos de mapeamento manual no frontend os incluam para evitar que a abertura da edição zere dados preexistentes.
- **Validação Condicional e Prevenção de Estados Zumbi**:
  - Quando um campo é discriminador de tipo/estratégia (ex: `type`, `status`, `driver`), os campos associados a esse tipo devem ser validados condicionalmente no backend (`required_if`, `required_unless` ou validação customizada), nunca puramente `nullable`.
  - Validação de integridade referencial em JSON: garanta que normalizadores backend limpem ou rejeitem referências a entidades que não existem nas coleções pai/irmãs válidas.
- **Integridade Transacional e Banco de Dados**:
  - Alterações em múltiplos registros devem estar dentro de `DB::transaction()`.
  - Eager loading (`with()`, `loadMissing()`, `withCount()`) para evitar N+1.
  - Tipagem estrita e null safety (PHP 8.3 e TypeScript sem `any`).
  - Contratos de dados sincronizados entre backend (`Inertia::render`) e frontend (`defineProps`).

### 5. Testes, Build e Formatação
- **Tag de Abertura PHP**: Certifique-se de que TODO arquivo `.php` (código de aplicação ou testes em `tests/*`) contenha `<?php` na primeira linha.
- **Validação Quantitativa de Testes (Test Execution Sanity)**:
  - Ao rodar Pest para validar arquivos afetados (`php artisan test --compact`), NUNCA confie apenas no código de saída (`exit code 0`).
  - Valide explicitamente se a contagem de testes executados é maior que zero (`tests > 0`, `assertions > 0`). Se reportar `0 tests` ou `No tests found`, aponte imediatamente como 🔴 **Crítico (P0)**.
- **Cobertura Proporcional no Pest**: Cobertura de testes Pest em `tests/Feature` ou `tests/Unit` para novas regras. Ao editar arquivos de testes existentes, garanta que casos de testes anteriores não foram deletados inadvertidamente.
- **Regeneração do Wayfinder**: Ao alterar rotas ou assinaturas de controllers, executar `php artisan wayfinder:generate --with-form --no-interaction`.
- **Formatação e Linters**:
  - PHP: `vendor/bin/pint --dirty --format agent`
  - Frontend: `npm run format:check` / `npm run format`, `npm run lint:check` / `npm run lint`
- **Validação de Build e Tipagem**:
  - Frontend: `npm run types:check` (`vue-tsc --noEmit`), `npm run build` / `npm run build:ssr`
  - Backend: `composer run types:check` (PHPStan)
  - Pipeline Geral: `composer run ci:check`

---

## Formato do Relatório de Análise

Apresente a análise com o seguinte formato estruturado e visual:

```markdown
# 📋 Relatório de Code Review — Alterações Pendentes

## 📊 1. Resumo dos Arquivos Analisados
- **Modificados**: X | **Novos/Untracked**: Y | **Removidos**: Z

| Arquivo | Status | Linhas | Avaliação Inicial |
| :--- | :--- | :--- | :--- |
| `app/Features/...` | Modificado | ~120 linhas | ✅ Conforme |
| `resources/js/...` | Novo | ~850 linhas | ⚠️ Excede 800 linhas |

---

## 🎯 2. Apontamentos por Nível de Criticidade

### 🔴 Crítico / Bloqueante (P0)
*(Se não houver problemas críticos, indique: "✅ Nenhum apontamento crítico identificado.")*
- **[Segurança] Falha de autorização no endpoint X**:
  - **Arquivo**: `app/Features/.../Controller.php:L45`
  - **Impacto**: Usuários não autorizados podem alterar dados protegidos.
  - **Ação Recomendada**: Adicionar `$this->authorize('update', $model);`.

### 🟡 Importante / Atenção (P1)
*(Se não houver apontamentos importantes, indique: "✅ Nenhum apontamento de alta relevância identificado.")*
- **[Tamanho] Arquivo excede 800 linhas**:
  - **Arquivo**: `resources/js/features/.../BigComponent.vue (850 linhas)`
  - **Impacto**: Dificuldade de manutenção, acoplamento excessivo e limites de contexto.
  - **Ação Recomendada**: Extrair subcomponentes para `components/` e estado para `composables/`.
- **[Performance] Possível N+1 em loop**:
  - **Arquivo**: `app/Features/.../Service.php:L80`
  - **Impacto**: Múltiplas queries executadas para carregar a relação `team`.
  - **Ação Recomendada**: Adicionar `->with('team')` na consulta inicial.

### 🔵 Sugestão / Melhoria Contínua (P2)
*(Se não houver sugestões, indique: "✅ Código alinhado com as boas práticas.")*
- **[Formatação] Pint / Estilo**:
  - **Ação**: Executar `vendor/bin/pint --dirty --format agent` para alinhar formatação e imports PHP.
- **[Tipagem] Array Shape no PHPDoc**:
  - **Arquivo**: `app/Features/.../Service.php`
  - **Ação**: Especificar formato `@param array{name: string, email: string}`.

---

## 🧪 3. Status de Testes e Qualidade
- **Pest PHP**: [✅ Testes existentes cobrem a mudança (X testes executados) / ⚠️ Recomendado criar teste em `tests/Feature/...`]
- **Pint / Linter**: [✅ Formatado / ⚠️ Executar `vendor/bin/pint --dirty --format agent`]
- **TypeScript / vue-tsc**: [✅ Aprovado (`npm run types:check`) / ⚠️ Pendente validação]
- **Build / SSR**: [✅ Aprovado (`npm run build`) / ⚠️ Pendente validação]
- **Wayfinder**: [✅ Sincronizado / ⚠️ Executar `php artisan wayfinder:generate --with-form --no-interaction`]

---

## 💡 4. Plano de Ação Recomendado (Diffs e Correções)
*(Apresente os blocos de código diff ou comandos exatos para aplicar as correções dos itens P0 e P1)*
```
