# sIAc3

Aplicação Laravel com frontend Vue/Inertia para dashboards, visualização de dados, gestão de unidades, sistemas monitorados e configurações de conta.

## Stack

- PHP 8.3 e Laravel 13
- Inertia.js 3 e Vue 3
- TypeScript
- Tailwind CSS 4
- Laravel Fortify
- Laravel Wayfinder
- Pest 4
- Vite 8

## Instalação

Requisitos:

- PHP 8.3 ou superior
- Composer
- Node.js e npm
- Extensões PHP exigidas pelo Laravel

Instalação automatizada:

```bash
composer setup
```

O script instala as dependências, cria o `.env`, gera a chave da aplicação, executa as migrations e compila o frontend.

Para iniciar servidor, queue worker e Vite:

```bash
composer run dev
```

Por padrão, o `.env.example` utiliza SQLite. Ajuste as variáveis de banco antes das migrations se outro banco for utilizado.

Para os recursos de dashboard com IA, configure `GEMINI_API_KEY` e, quando necessário, `GEMINI_MODEL` no `.env`.

## Arquitetura do frontend

O frontend segue feature-based architecture:

```text
resources/js/
├── app/
│   ├── components/     # Shell, cabeçalho, sidebar e navegação
│   └── layouts/        # Layouts globais da aplicação
├── features/
│   ├── auth/
│   ├── dashboards/
│   ├── data-table/
│   ├── home/
│   ├── settings/
│   └── teams/
├── pages/              # Adaptadores finos para páginas Inertia
├── shared/
│   ├── components/
│   │   └── ui/         # Primitivos de UI
│   ├── composables/
│   ├── lib/
│   └── types/
├── actions/            # Gerado pelo Wayfinder
└── routes/             # Gerado pelo Wayfinder
```

### `features`

Cada feature mantém próximos os arquivos que mudam juntos:

```text
features/<feature>/
├── pages/
├── components/
├── composables/
└── types/
```

As pastas opcionais só devem ser criadas quando necessárias.

### `pages`

`resources/js/pages` é a camada de entrada do Inertia. Esses arquivos mantêm compatibilidade com os nomes de componentes retornados pelo backend e apenas reexportam a implementação da feature.

Exemplo:

```vue
<script lang="ts">
import TeamsPage from '@/features/teams/pages/Index.vue';

export default TeamsPage;
</script>
```

Não coloque lógica, estado, requests ou templates nessa camada.

### `app`

Contém elementos que montam a aplicação: layouts globais, sidebar, cabeçalho, navegação e componentes ligados ao shell.

### `shared`

Contém código reutilizável e independente de features. `shared` não pode importar arquivos de `app` ou `features`.

### Limites entre camadas

- `app` pode compor `features` e `shared`.
- Features podem importar seus próprios arquivos, `shared`, `@/routes` e `@/actions`.
- Uma feature não deve acessar internals de outra feature.
- Código compartilhado entre features deve ser promovido para `shared` somente quando a reutilização for real.
- `actions` e `routes` são gerados pelo Wayfinder e não devem ser editados manualmente.

## Adicionando uma feature

1. Crie `resources/js/features/<feature>/pages`.
2. Adicione componentes, composables e tipos dentro da própria feature.
3. Crie em `resources/js/pages` o adaptador correspondente ao nome retornado por `Inertia::render()`.
4. Utilize funções Wayfinder para links, formulários e requests ao backend.
5. Atualize testes que dependam do nome Inertia ou do caminho físico do componente.
6. Execute as verificações do projeto.

## Comandos

Frontend:

```bash
npm run dev
npm run format
npm run format:check
npm run lint
npm run lint:check
npm run types:check
npm run build
```

Backend e testes:

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
composer run types:check
composer run ci:check
```

Após alterações nas rotas ou namespaces de controllers:

```bash
php artisan wayfinder:generate --with-form --no-interaction
```

## Instruções para agentes de IA

- `AGENTS.md`: regras completas do projeto e Laravel Boost.
- `GEMINI.md`: ponto de entrada para Gemini e compatibilidade com ferramentas do ecossistema.
- `.agents/rules/project-architecture.md`: regra de workspace nativa do Google Antigravity.

Todos os agentes devem tratar `AGENTS.md` como a fonte de verdade das convenções do repositório.
