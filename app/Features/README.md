# Arquitetura backend por features

O backend é organizado pelo motivo pelo qual muda. Comportamento funcional fica
em `app/Features/<Feature>`, sem deslocar os pontos convencionais do Laravel.

## Responsabilidades

| Código | Destino |
| --- | --- |
| Controllers, Form Requests, Actions, Services, regras e rotas de um domínio | `app/Features/<Feature>` |
| Integrações técnicas compartilhadas | `app/Infrastructure` |
| Models Eloquent | `app/Models` |
| Jobs, Commands, Providers e middleware global | diretórios convencionais em `app` |
| Customizações do Fortify | `app/Actions/Fortify`, `app/Http/Responses` e provider |
| Migrations, factories e seeders | `database/*` |

Nem toda feature precisa das mesmas subpastas. Crie apenas as necessárias para
responsabilidades concretas.

## Dependências

A direção preferida é:

```text
Routes -> Controllers -> Actions/Services da feature -> Models/Infrastructure
```

Uma feature pode usar Models e infraestrutura transversal. Dependências entre
features devem ser intencionais e passar por classes coesas, não por detalhes
internos acidentais.

Controllers coordenam HTTP, autorização, entrada e resposta. Operações
transacionais, consultas extensas ou transformações reutilizáveis pertencem a
Actions ou Services. Não crie DTOs, repositories, interfaces ou pastas somente
para completar uma árvore.

## Rotas e contratos

Rotas funcionais ficam em `app/Features/<Feature>/Routes`. Os arquivos em
`routes/` são agregadores com `require app_path(...)`.

Ao refatorar, preserve:

- URL, verbo, nome e middleware das rotas;
- bindings, payloads, status e redirects;
- nomes dos componentes e props Inertia;
- funções de rotas nomeadas geradas pelo Wayfinder.

Controllers estendem `App\Http\Controllers\Controller`. Models, Providers,
middleware global e Fortify continuam em seus locais convencionais mesmo quando
importam uma API pública de uma feature.

## Verificação

Execute os testes focados durante cada mudança. Antes de finalizar uma alteração
estrutural, regenere Wayfinder e rode Pint, PHPStan, a suíte Pest, os checks
frontend e o build.
