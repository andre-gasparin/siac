# 📋 SIAC - Backlog de Tarefas e Prompts de Execução

> **Documento gerado a partir da reunião técnica com Caio Passos e anotações de alinhamento.**  
> Cada tarefa contém o contexto detalhado, objetivos de negócio, arquivos afetados, regras arquiteturais e o **prompt pronto para execução** pelo agente de desenvolvimento (em modo Review-Driven).

---

## 📑 Índice de Módulos

1. [Módulo 1: Sistemas e Parâmetros (`/unidades/{team}/sistemas`)](#modulo-1-sistemas-e-parametros)
2. [Módulo 2: Entrada de Dados (`/{team}/entrada-dados`)](#modulo-2-entrada-de-dados)
3. [Módulo 3: Tabela de Dados (`/{team}/tabela-dados`)](#modulo-3-tabela-de-dados)
4. [Módulo 4: Cálculos entre Parâmetros (`/{team}/calculos`)](#modulo-4-calculos-entre-parametros)
5. [Módulo 5: Alternador de Modo Claro/Escuro no Menu Principal](#modulo-5-modo-claro-e-escuro)
6. [Módulo 6: Progressive Web App (PWA) e Suporte Offline](#modulo-6-progressive-web-app-pwa)
7. [Módulo 7: Central de Notificações e Alertas](#modulo-7-central-de-notificacoes-e-alertas)
8. [Notas Adicionais e Pontos de Atenção Levantados na Reunião](#notas-adicionais)

---

<a id="modulo-1-sistemas-e-parametros"></a>
## 1. Módulo 1: Sistemas e Parâmetros (`/unidades/{team}/sistemas`)

### Tarefa 1.1: Remoção do campo "Código" dos parâmetros
- **Contexto:** Na listagem/edição de parâmetros por sistema, a coluna e o campo de input "Código" não são mais necessários para a rotina da operação (mantendo-se apenas Nome, Tag, Unidade, Decimais, EWMA e Limites).
- **Arquivos Envolvidos:**
  - `resources/js/features/teams/pages/Systems.vue`
- **Critérios de Aceite:**
  - O cabeçalho `<th>Código</th>` deve ser removido da tabela de parâmetros.
  - O campo de input `<input v-model="parameter.code" ... />` deve ser removido.
  - Salvar as alterações continua funcionando normalmente sem enviar erro de validação.

#### 🤖 Prompt para Execução (Tarefa 1.1)
```markdown
No arquivo `resources/js/features/teams/pages/Systems.vue`, remova a coluna "Código" da tabela de parâmetros:
1. Remova o cabeçalho `<th>Código</th>` (aproximadamente linha 372).
2. Remova a célula `<td>` correspondente que renderiza o input do código do parâmetro.
3. Certifique-se de que o alinhamento das colunas restantes (Nome, Tag, Unidade, Decimais, EWMA, Posição e Limites) permaneça íntegro.
4. Execute `npm run types:check` e `npm run build` para validar.
```

---

### Tarefa 1.2: Indicador visual tracejado no Drag-and-Drop de ordenação de sistemas
- **Contexto:** Ao arrastar um card de sistema para reordená-lo, atualmente há pouca indicação visual sobre onde ele será solto, causando dúvidas ao operador. Deve ser exibido um traço/borda tracejada evidente no local de inserção antes de soltar o item.
- **Arquivos Envolvidos:**
  - `resources/js/features/teams/pages/Systems.vue`
- **Critérios de Aceite:**
  - Ao arrastar um sistema sobre outro (`dragover`), deve ser exibida uma área ou borda destacada com linha tracejada (`border-2 border-dashed border-primary bg-primary/5`) ou uma barra indicadora de inserção antes do item de destino.
  - O evento `dragleave` deve ser tratado adequadamente para evitar flickering quando o cursor passa por filhos do card.
  - A ordenação deve persistir ao soltar e ser salva pelo botão "Salvar alterações".

#### 🤖 Prompt para Execução (Tarefa 1.2)
```markdown
Em `resources/js/features/teams/pages/Systems.vue`, aprimore a experiência de arrastar e soltar (drag and drop) dos cards de sistemas monitorados:
1. Melhore a indicação visual de destino durante o arrasto: ao disparar `onSystemDragOver`, exiba um indicador de inserção claro (por exemplo, uma linha divisora tracejada ou borda pontilhada/tracejada destacada `border-2 border-dashed border-primary bg-primary/10 transition-all`) que mostre com exatidão onde o card será inserido.
2. Evite o flickering (piscar) provocado pelo `dragleave` em elementos filhos (utilize verificação com `relatedTarget` ou controle simplificado de índice).
3. Ao soltar (`drop`), reordene os sistemas e reatribua os `sort_order` sequencialmente.
4. Verifique a compilação com `npm run lint:check` e `npm run types:check`.
```

---

<a id="modulo-2-entrada-de-dados"></a>
## 2. Módulo 2: Entrada de Dados (`/{team}/entrada-dados`)

### Tarefa 2.1: Reorganização da ordem dos campos de cabeçalho
- **Contexto:** Os operadores realizam a digitação dos dados seguindo um fluxo mental específico. A ordem solicitada para os campos no topo do formulário é:
  1. **Hora do registro** (timestamp do momento da digitação)
  2. **Coleta** (Data e Hora da coleta em campo)
  3. **Responsável** (Nome de quem realizou a digitação/coleta)
- **Arquivos Envolvidos:**
  - `resources/js/features/data-entry/components/DataEntryForm.vue`
- **Critérios de Aceite:**
  - O bloco "Hora do registro" deve ser o primeiro da seção superior.
  - O bloco "Hora da coleta" (Data + Hora) deve ser o segundo.
  - O bloco "Responsável" deve ser o terceiro.
  - A validação de preenchimento obrigatório do responsável deve ser preservada.

#### 🤖 Prompt para Execução (Tarefa 2.1)
```markdown
Em `resources/js/features/data-entry/components/DataEntryForm.vue`, reorganize a ordem dos campos no cabeçalho do formulário:
1. Mova o bloco "Hora do registro" (que exibe o `liveTimestamp`) para ser o primeiro item.
2. Posicione o bloco "Hora da coleta" (com `DatePicker` de data e `Input` de hora) como o segundo item.
3. Posicione o campo "Responsável" como o terceiro item.
4. Mantenha os estilos existentes de layout responsivo, espaçamentos e acessibilidade de labels.
5. Execute `npm run build` e confirme se o formulário monta corretamente.
```

---

### Tarefa 2.2: Separação nítida das colunas no tema claro (White Mode)
- **Contexto:** No modo claro (fundo branco), a tabela de parâmetros da tela de entrada de dados apresenta baixo contraste entre as colunas, dificultando a leitura rápida dos valores e limites.
- **Arquivos Envolvidos:**
  - `resources/js/features/data-entry/components/DataEntryForm.vue`
- **Critérios de Aceite:**
  - Aplicar divisores verticais ou bordas visíveis (`border-r border-border/70` ou `divide-x divide-border/60`) entre as colunas.
  - Cabeçalho com contraste adequado e diferenciação sutil de fundo para melhorar a legibilidade em telas com muito brilho.

#### 🤖 Prompt para Execução (Tarefa 2.2)
```markdown
Em `resources/js/features/data-entry/components/DataEntryForm.vue`, aprimore a distinção visual das colunas da tabela de parâmetros no tema claro (white mode):
1. Adicione divisores ou bordas laterais entre as colunas da tabela (`divide-x divide-border/60` ou bordas laterais discretas nas células `td` e `th`).
2. Garanta que no modo claro haja contraste adequado entre as colunas de "Parâmetro", "Resultado", "Unidade", "Mínimo" e "Máximo".
3. Teste o visual tanto no modo claro (`light`) quanto no escuro (`dark`).
```

---

### Tarefa 2.3: Coluna dedicada para o EWMA com o nome "Predição"
- **Contexto:** Atualmente o alerta de EWMA (`EwmaAlert`) é renderizado dentro da mesma célula do input de "Resultado". Deve ser criada uma coluna separada no cabeçalho e corpo da tabela chamada **"Predição"**, onde o indicador/badge do EWMA será posicionado de forma limpa.
- **Arquivos Envolvidos:**
  - `resources/js/features/data-entry/components/DataEntryForm.vue`
- **Critérios de Aceite:**
  - Adicionar a coluna `<th>Predição</th>` (ou centralizada com largura fixa, ex: `w-24`).
  - Mover o componente `<EwmaAlert v-if="ewmaResults[param.id]" ... />` para dentro da nova coluna `<td>`.
  - O cálculo do EWMA via `useEwmaPreview` continua funcionando em tempo real ao digitar.

#### 🤖 Prompt para Execução (Tarefa 2.3)
```markdown
Em `resources/js/features/data-entry/components/DataEntryForm.vue`:
1. Adicione uma nova coluna no cabeçalho da tabela: `<th class="w-28 px-2 py-3 text-center">Predição</th>`.
2. Posicione a coluna logo após a coluna "Resultado".
3. No corpo da tabela (`tbody`), crie a célula correspondente `<td>` e mova o componente `<EwmaAlert>` para dentro dela, centralizado.
4. Mantenha o input de resultado limpo, sem sobreposição com o alerta.
5. Verifique se o cálculo reativo do EWMA continua funcionando conforme o operador digita.
```

---

<a id="modulo-3-tabela-de-dados"></a>
## 3. Módulo 3: Tabela de Dados (`/{team}/tabela-dados`)

### Tarefa 3.1: Alinhamento horizontal do cabeçalho de parâmetros em linhas dedicadas
- **Contexto:** No cabeçalho da tabela de dados, o nome do parâmetro, a tag/código, a unidade e os botões de ação (gráficos de linha e barra) estão agrupados em um único `<th>` vertical. Como os nomes dos parâmetros possuem tamanhos variados, os ícones e tags ficam desalinhados verticalmente entre as colunas.
- **Arquivos Envolvidos:**
  - `resources/js/features/data-table/components/DataTableGrid.vue`
- **Critérios de Aceite:**
  - O `<thead>` deve conter linhas estruturadas (`tr`) para que cada elemento fique em sua respectiva linha horizontal:
    - Linha 1: Nome do parâmetro
    - Linha 2: Tag / Código
    - Linha 3: Unidade
    - Linha 4: Ações (botões de gráfico de linha e coluna perfeitamente alinhados)
    - Linhas 5 e 6: Limites mínimos e máximos (já existentes)
  - As colunas fixas da esquerda (Ações, Data, Hora) devem utilizar `rowspan` adequado para se manterem integradas.

#### 🤖 Prompt para Execução (Tarefa 3.1)
```markdown
Em `resources/js/features/data-table/components/DataTableGrid.vue`, reformule o cabeçalho da tabela de dados (`thead`) para que os elementos das colunas de parâmetros fiquem alinhados horizontalmente em linhas separadas:
1. Estruture as linhas do cabeçalho em:
   - Linha do Nome do Parâmetro
   - Linha da Tag / Código
   - Linha da Unidade
   - Linha dos Ícones de Ação (Gráfico de Linha e Gráfico de Coluna/Barra)
2. Ajuste as três primeiras colunas fixas ("Ações", "Data", "Hora") com o `rowspan` correto para cobrir essas linhas.
3. Garanta que os botões de gráfico fiquem alinhados na mesma altura em todas as colunas.
4. Mantenha as linhas de limite mínimo e máximo logo abaixo.
5. Teste a rolagem horizontal e o comportamento com `fitToScreen` ativado e desativado.
```

---

### Tarefa 3.2: Desativar por padrão os cálculos EWMA na consulta da tabela
- **Contexto:** Na reunião, foi alinhado que os cálculos EWMA (fora do min/max e em todos os resultados) devem vir **desativados por padrão** para não gerar dúvidas imediatas nos clientes durante a fase inicial de implantação. O operador poderá ativá-los quando desejar no menu "Opções".
- **Arquivos Envolvidos:**
  - `resources/js/features/data-table/composables/useDataTable.ts`
  - `app/Features/DataTable/Services/DataTableDataBuilder.php`
- **Critérios de Aceite:**
  - O estado `ewmaOutsideLimits` em `useDataTable.ts` deve iniciar com `false`.
  - O estado `ewmaAll` deve iniciar com `false`.
  - No backend (`DataTableDataBuilder.php`), o fallback padrão para `ewma_outside_limits` deve ser `false`.
  - Ao marcar no menu de opções e carregar, o cálculo do EWMA deve ser executado normalmente.

#### 🤖 Prompt para Execução (Tarefa 3.2)
```markdown
Ajuste os valores padrão dos filtros de EWMA na Tabela de Dados para que venham desativados por padrão:
1. No frontend (`resources/js/features/data-table/composables/useDataTable.ts`), altere:
   `const ewmaOutsideLimits = ref(false);`
   `const ewmaAll = ref(false);`
2. No backend (`app/Features/DataTable/Services/DataTableDataBuilder.php`), altere o default de:
   `$outsideLimits = (bool) ($filters['ewma_outside_limits'] ?? false);`
3. Execute os testes do DataTable: `php artisan test --compact --filter=DataTable` e garanta que passem.
```

---

### Tarefa 3.3: Destaque visual para linhas com comentários e linhas com modificações
- **Contexto:** Na listagem de coletas da tabela de dados, o operador precisa bater o olho e saber:
  1. Se a linha possui comentários registrados (destacar o ícone de balão de mensagem).
  2. Se algum dado da linha foi **modificado/editado** após a inserção inicial (destacar o ícone de histórico). Se foi apenas inserção original, o ícone permanece sem destaque.
- **Arquivos Envolvidos:**
  - `app/Features/DataTable/Services/DataTableDataBuilder.php`
  - `resources/js/features/data-table/types/index.ts`
  - `resources/js/features/data-table/components/DataTableGrid.vue`
- **Critérios de Aceite:**
  - O backend deve retornar no array de cada linha:
    - `has_comments: boolean`
    - `has_modifications: boolean` (verdadeiro se houver auditoria de alteração/update em leituras dessa data/hora)
  - No grid da tabela:
    - Botão de comentário: se `row.has_comments` for verdadeiro, exibir ícone com cor de destaque (ex: `text-blue-500 fill-blue-500/20` ou `text-primary font-bold`).
    - Botão de histórico: se `row.has_modifications` for verdadeiro, exibir ícone com cor de destaque (ex: `text-amber-500 font-bold`). Caso contrário, estilo neutro discreto.

#### 🤖 Prompt para Execução (Tarefa 3.3)
```markdown
Implemente destaque visual na coluna "Ações" da Tabela de Dados para linhas com comentários ou modificações:
1. No backend (`app/Features/DataTable/Services/DataTableDataBuilder.php`):
   - Identifique eficientemente se existem comentários (`TeamComment` ou tabela equivalente vinculada à data/hora e sistemas) e marque `has_comments = true` no array da linha.
   - Identifique se houve alterações em relação à inserção inicial (por exemplo, registros em `ParameterValue` com `updated_at > created_at` ou histórico de auditoria) e marque `has_modifications = true`.
2. No TypeScript (`resources/js/features/data-table/types/index.ts`):
   - Adicione `has_comments?: boolean` e `has_modifications?: boolean` na interface `DataRow`.
3. No componente (`resources/js/features/data-table/components/DataTableGrid.vue`):
   - No botão de histórico: aplique classe de destaque (ex: `text-amber-600 dark:text-amber-400 font-bold bg-amber-500/10`) quando `row.has_modifications` for true.
   - No botão de comentários: aplique classe de destaque (ex: `text-blue-600 dark:text-blue-400 font-bold bg-blue-500/10`) quando `row.has_comments` for true.
4. Execute os testes do backend e compile os assets do frontend.
```

---

### Tarefa 3.4: Fechar modal ao salvar comentário e formatar datas em pt-BR no histórico
- **Contexto:**
  1. No modal de histórico e comentários (`DataTableRowDetailsDialog.vue`), ao digitar um comentário e clicar em "Salvar comentário", o modal continua aberto, exigindo que o usuário feche manualmente.
  2. As datas exibidas no histórico e nas "Informações dos registros atuais" estão no formato americano (`YYYY-MM-DD HH:mm:ss`), devendo ser formatadas para o padrão brasileiro (`DD/MM/YYYY HH:mm`).
- **Arquivos Envolvidos:**
  - `resources/js/features/data-table/components/DataTableRowDetailsDialog.vue`
- **Critérios de Aceite:**
  - Ao salvar o comentário com sucesso na função `save(item)`, emitir evento `close` para fechar o diálogo automaticamente.
  - As datas do histórico (`item.created_at`) e as datas de leitura atual (`reading.created_at`, `reading.updated_at`) devem ser formatadas como `DD/MM/YYYY HH:mm`.

#### 🤖 Prompt para Execução (Tarefa 3.4)
```markdown
Em `resources/js/features/data-table/components/DataTableRowDetailsDialog.vue`:
1. Na função `save(item: RowComment)`: após salvar o comentário com sucesso (`toast.success`), invoque `emit('close')` para fechar o modal automaticamente.
2. Adicione uma função auxiliar para formatação de data e hora em padrão brasileiro (ex: `formatDateTime(dateStr)` utilizando `Intl.DateTimeFormat` ou formatação manual `DD/MM/YYYY HH:mm`).
3. Aplique essa formatação em:
   - Data do histórico (`item.created_at`)
   - Data de criação do registro atual (`reading.created_at`)
   - Data de atualização do registro atual (`reading.updated_at`)
4. Verifique com `npm run types:check`.
```

---

### Tarefa 3.5: Seleção de células com o mouse para edição ou exclusão em lote
- **Contexto:** Em momentos de parada de planta ou instrumentos descalibrados, o operador precisa limpar ou alterar múltiplos valores de parâmetros de uma só vez (semelhante à seleção por arraste do Excel). Essa funcionalidade deve poder ser ativada nas opções da tabela para não interferir na navegação padrão.
- **Arquivos Envolvidos:**
  - `resources/js/features/data-table/components/DataTableFilterBar.vue`
  - `resources/js/features/data-table/components/DataTableGrid.vue`
  - `resources/js/features/data-table/composables/useDataTable.ts`
  - `app/Features/DataTable/Http/Controllers/DataTableValueController.php` (ou novo endpoint de lote)
- **Critérios de Aceite:**
  - Adicionar opção no dropdown de Opções da tabela: "Permitir seleção de células em lote".
  - Quando ativa, o usuário pode clicar e arrastar sobre as células numéricas da tabela para selecioná-las (destaque visual com borda/fundo azul).
  - Exibir barra flutuante de ações com:
    - Quantidade de células selecionadas
    - Botão "Definir valor": abre campo para informar um novo valor e aplicá-lo em todas as células selecionadas
    - Botão "Apagar valores": limpa os valores selecionados em lote
    - Botão "Cancelar seleção"
  - Enviar requisição em lote para o backend e atualizar a tabela preservando a performance.

#### 🤖 Prompt para Execução (Tarefa 3.5)
```markdown
Implemente a seleção de células em lote na Tabela de Dados:
1. No menu de opções (`DataTableFilterBar.vue`), inclua o toggle `batchSelectionEnabled` ("Habilitar seleção de células em lote").
2. No componente `DataTableGrid.vue`:
   - Quando `batchSelectionEnabled` estiver ativo, capture os eventos `mousedown`, `mouseover` e `mouseup` nas células numéricas para calcular a seleção de células (armazenando pares de `{ timestamp, parameterId }`).
   - Aplique uma classe de seleção nas células selecionadas (`bg-primary/20 ring-2 ring-primary ring-inset`).
   - Renderize uma barra flutuante inferior fixa quando houver células selecionadas, contendo:
     - Badge "X células selecionadas"
     - Input/botão para definir um valor para todas
     - Botão para limpar/apagar todas as células selecionadas
     - Botão para cancelar a seleção
3. No backend (`app/Features/DataTable`), adicione um endpoint para atualização/exclusão em lote de valores para evitar múltiplas requisições avulsas.
4. Escreva testes Pest cobrindo a mutação em lote e rode `php artisan test --compact`.
```

---

### Tarefa 3.6: Fechar automaticamente o menu principal/lateral ao carregar a tabela
- **Contexto:** Ao carregar os dados na Tabela de Dados, a visualização ganha muito espaço horizontal se a barra lateral estiver recolhida. A tabela deve fechar o menu automaticamente ao concluir ou iniciar o carregamento dos dados.
- **Arquivos Envolvidos:**
  - `resources/js/features/data-table/pages/Index.vue` ou `resources/js/features/data-table/components/DataTableFilterBar.vue`
  - Integração com `useSidebar()` (`@/shared/components/ui/sidebar`)
- **Critérios de Aceite:**
  - Ao disparar o carregamento dos dados da tabela (`load`), invocar `setOpen(false)` do `useSidebar()`.
  - Em telas desktop, a sidebar se recolhe automaticamente, ampliando a área de trabalho para as colunas da tabela.

#### 🤖 Prompt para Execução (Tarefa 3.6)
```markdown
Na página da Tabela de Dados (`resources/js/features/data-table/pages/Index.vue` ou composable `useDataTable.ts`):
1. Importe `useSidebar` de `@/shared/components/ui/sidebar`.
2. No método disparado ao carregar os dados (ao clicar em "Carregar" ou trocar sistema), execute `const { setOpen } = useSidebar(); setOpen(false);`.
3. Garanta que isso ocorra suavemente sem gerar erros caso o componente de sidebar não esteja montado.
```

---

### Tarefa 3.7: Ajuste de tipografia e tamanho da fonte na tabela de dados
- **Contexto:** A fonte dos valores e células na tabela atual foi considerada pequena e de leitura cansativa em comparação com o sistema legado. Aumentar o tamanho e adotar uma tipografia mais legível e proporcional (estilo tabular mono/sans limpo).
- **Arquivos Envolvidos:**
  - `resources/js/features/data-table/components/DataTableGrid.vue`
  - `resources/css/app.css` (se necessário para classe auxiliar de fonte tabular)
- **Critérios de Aceite:**
  - Aumentar a fonte base dos valores da tabela (ex: de `text-[11px]` para `text-xs` ou `text-[13px] font-medium`).
  - Utilizar variantes numéricas tabulares (`tabular-nums`) para que os números fiquem perfeitamente alinhados verticalmente por casa decimal.

#### 🤖 Prompt para Execução (Tarefa 3.7)
```markdown
Em `resources/js/features/data-table/components/DataTableGrid.vue`:
1. Aumente a tipografia das células de dados numéricos para `text-xs md:text-[13px]` e aplique a classe `tabular-nums font-medium` para garantir que os números mantenham largura fixa e perfeito alinhamento visual.
2. Ajuste o padding das células (`px-2 py-2`) para acomodar a nova fonte sem quebrar o layout compacto.
3. Garanta que os cabeçalhos e rótulos acompanhem a proporção de tamanho de forma harmoniosa.
```

---

### Tarefa 3.8: Padronização de gráficos (limites min/max, cores fora do limite, datas DD/MM e escala Y)
- **Contexto:** Nos gráficos (tanto no popover da Tabela de Dados quanto nos Relatórios):
  1. Devem aparecer as linhas de limites mínimos e máximos (`markLine`).
  2. No gráfico de colunas/barras, a barra deve ficar colorida em vermelho quando o valor estiver fora dos limites.
  3. No eixo X e nos tooltips, a indicação de data deve ser exibida no padrão brasileiro `dia/mês` (`DD/MM`) e os textos em português.
  4. Escala do eixo Y: por padrão iniciar em zero (`min: 0`), exceto quando for **pH** ou quando houver **valores negativos** (ex: ORP), aplicando margem proporcional (+20%).
- **Arquivos Envolvidos:**
  - `resources/js/shared/components/charts/EChartRenderer.vue`
  - `resources/js/features/data-table/components/DataTableChartDialog.vue`
- **Critérios de Aceite:**
  - `EChartRenderer` deve aceitar `min_val` e `max_val` e renderizar `markLine` horizontal destacada.
  - As barras fora dos limites recebem `itemStyle: { color: '#ef4444' }`.
  - O eixo X formata os carimbos de tempo como `DD/MM` ou `DD/MM HH:mm`.
  - A escala não inicia em zero caso o parâmetro seja pH ou se houver valores negativos na série.

#### 🤖 Prompt para Execução (Tarefa 3.8)
```markdown
Em `resources/js/shared/components/charts/EChartRenderer.vue` e `DataTableChartDialog.vue`, implemente as melhorias nos gráficos:
1. Limites Mínimos e Máximos:
   - Adicione `markLine` nas séries do ECharts para desenhar linhas pontilhadas de alerta quando `min_val` ou `max_val` estiverem definidos.
2. Gráfico de Colunas/Barras:
   - Nas séries do tipo `bar`, avalie cada ponto de dado: se o valor estiver abaixo de `min_val` ou acima de `max_val`, configure a cor daquela barra individualmente para vermelho (`#ef4444`).
3. Datas e Formatação:
   - Configure o `xAxis` para formatar datas no padrão brasileiro `DD/MM` (ou `DD/MM HH:mm` no tooltip com hora).
   - Configure o tooltip para exibir títulos e rótulos em português.
4. Escala do Eixo Y:
   - Configure o valor mínimo do eixo Y:
     - Inicie em zero (`min: 0`) por padrão.
     - Exceção: se o nome do parâmetro contiver "pH", ou se houver qualquer valor negativo na série (como medições de ORP), deixe o mínimo automático ou com margem de 10-20% abaixo do menor valor.
5. Valide a compilação e teste abrindo o diálogo de gráfico de linha e de barras.
```

---

<a id="modulo-4-calculos-entre-parametros"></a>
## 4. Módulo 4: Cálculos entre Parâmetros (`/{team}/calculos`)

### Tarefa 4.1: Reorganização da ordem dos operandos no formulário (Destino -> Primário -> Secundário)
- **Contexto:** Na tela de cadastro/edição de regras de cálculo, a ordem natural do operador é definir primeiro onde o resultado será gravado (Destino) e depois de onde os dados serão lidos (Parâmetro Principal e Parâmetro Secundário).
- **Arquivos Envolvidos:**
  - `resources/js/features/calculations/pages/Index.vue`
- **Critérios de Aceite:**
  - No formulário do modal, posicionar primeiro os campos do **Destino**.
  - Em seguida, posicionar os campos do **Principal (Primário)**.
  - Por último, posicionar os campos do **Secundário**.

#### 🤖 Prompt para Execução (Tarefa 4.1)
```markdown
Em `resources/js/features/calculations/pages/Index.vue`, altere a ordem dos campos de operandos dentro do modal de cálculo:
1. Mova o componente `CalculationOperandFields` do `role="destination"` (Destino) para ser o primeiro bloco de operandos.
2. Mantenha o `role="primary"` (Principal) como o segundo bloco.
3. Mantenha o `role="secondary"` (Secundário) como o terceiro bloco.
4. Ajuste os textos de auxílio e tabulação para que a navegação com a tecla Tab siga essa sequência natural.
```

---

### Tarefa 4.2: Remoção do campo "Nome do cálculo"
- **Contexto:** O campo de nome arbitrário ("Cálculo 1", "Cálculo 2") gera confusão e é desnecessário, pois a identidade do cálculo é o próprio parâmetro de destino calculado (ex: "Ciclo de Sílica").
- **Arquivos Envolvidos:**
  - `resources/js/features/calculations/pages/Index.vue`
  - `app/Features/Calculations/Http/Requests/CalculationRuleRequest.php`
  - `app/Models/CalculationRule.php`
- **Critérios de Aceite:**
  - Remover o input "Nome" do modal de cálculo no frontend.
  - Caso o backend exija `name` no banco de dados, preenchê-lo automaticamente com o nome do parâmetro de destino ou tornar a coluna nullable.

#### 🤖 Prompt para Execução (Tarefa 4.2)
```markdown
Remova a necessidade do campo manual "Nome do cálculo":
1. No frontend (`resources/js/features/calculations/pages/Index.vue`):
   - Remova o input do campo `name` no formulário.
   - Antes de enviar (`save()`), atribua automaticamente a `form.name` o nome legível do parâmetro de destino selecionado.
2. No backend (`app/Features/Calculations`):
   - Em `CalculationRuleRequest.php`, torne o campo `name` opcional (`sometimes|nullable|string`).
   - Se o `name` vier vazio, atribua automaticamente o nome do parâmetro de destino.
3. Rode `php artisan test --compact --filter=Calculation` para garantir que o fluxo de salvamento e edição continue passando.
```

---

### Tarefa 4.3: Exibição dos nomes reais dos parâmetros na fórmula e destaque do Destino nos cards
- **Contexto:** Na listagem de cálculos cadastrados, os cards exibem fórmulas genéricas como `Destino = P / S`. O usuário quer bater o olho e ver os nomes reais (ex: `Caldeira 1 / Sílica ÷ Condensado / Sílica`), com o Destino destacado como o título do card.
- **Arquivos Envolvidos:**
  - `resources/js/features/calculations/pages/Index.vue`
- **Critérios de Aceite:**
  - O título do card deve ser o nome do **Parâmetro de Destino** com destaque visual (badge ou tipografia maior).
  - A fórmula matemática deve substituir as letras `P` e `S` pelos nomes dos respectivos parâmetros configurados.

#### 🤖 Prompt para Execução (Tarefa 4.3)
```markdown
Em `resources/js/features/calculations/pages/Index.vue`:
1. No card de exibição da regra de cálculo (`article`):
   - Destaque o parâmetro de Destino no topo do card como o identificador principal do cálculo (ex: `<h2 class="text-base font-bold text-primary">{{ parameterName(rule.destination_parameter_id) }}</h2>`).
   - Exiba o tipo de cálculo logo abaixo como subtítulo (ex: Ciclo, Diferença, etc.).
2. Crie uma função computada ou helper que monte a fórmula usando os nomes reais dos parâmetros em vez das siglas genéricas (ex: `Nome Primário ÷ Nome Secundário` com seus respectivos multiplicadores se houver).
3. Remova a exibição do nome genérico legado ("Cálculo 1").
```

---

<a id="modulo-5-modo-claro-e-escuro"></a>
## 5. Módulo 5: Alternador de Modo Claro/Escuro no Menu Principal

### Tarefa 5.1: Botão rápido de alternância Claro/Escuro no `AppHeader`
- **Contexto:** Atualmente a troca de tema exige navegar até as configurações de perfil. O usuário solicitou ter o alternador acessível diretamente no menu/cabeçalho principal para facilitar a alternância rápida de visualização durante os testes e no dia a dia.
- **Arquivos Envolvidos:**
  - `resources/js/app/components/AppHeader.vue`
  - `resources/js/shared/composables/useAppearance.ts`
- **Critérios de Aceite:**
  - Inserir um botão de ícone (Sol / Lua) no lado direito do `AppHeader` (próximo ao avatar ou ao TeamSwitcher).
  - Ao clicar, alterna imediatamente entre o modo claro (`light`) e o modo escuro (`dark`), persistindo a preferência do usuário via `useAppearance`.

#### 🤖 Prompt para Execução (Tarefa 5.1)
```markdown
Adicione o alternador rápido de tema claro/escuro no cabeçalho principal da aplicação:
1. No arquivo `resources/js/app/components/AppHeader.vue`:
   - Importe `useAppearance` de `@/shared/composables/useAppearance`.
   - Importe os ícones `Sun` e `Moon` de `@lucide/vue`.
   - Adicione um botão de alternância no bloco de ações da direita:
     - Quando estiver no tema escuro, exibir o ícone `Sun` (para mudar para claro).
     - Quando estiver no tema claro, exibir o ícone `Moon` (para mudar para escuro).
   - Ao clicar, alterne a aparência chamando `updateAppearance('dark' | 'light')`.
2. Garanta que o botão funcione suavemente em desktop e mobile.
```

---

<a id="modulo-6-progressive-web-app-pwa"></a>
## 6. Módulo 6: Progressive Web App (PWA) e Suporte Offline

### Tarefa 6.1: Configuração do PWA com manifesto, ícones e indicador offline
- **Contexto:** Conforme discutido na reunião, transformar o SIAC em um Progressive Web App (PWA) permite que o sistema seja "instalado" no Windows, macOS, Android e iOS como uma janela dedicada independente do navegador, sem downloads pesados corporativos, além de permitir o aviso de status offline e a futura recepção de notificações push.
- **Arquivos Envolvidos:**
  - `resources/views/app.blade.php`
  - `public/manifest.json` (ou gerado via plugin Vite)
  - `vite.config.js`
  - Criação de componente de banner de status offline
- **Critérios de Aceite:**
  - Criar o arquivo `manifest.json` com nome, cores de tema, ícones (`favicon.png` / `icon-192.png` / `icon-512.png`) e `display: "standalone"`.
  - Configurar Service Worker básico para permitir a instalação no navegador (Chrome, Edge, Safari, Android).
  - Criar listener para detectar perda de conexão (`window.addEventListener('offline')`) e exibir um banner amigável: *"Sem conexão com a internet. Algumas ações ficarão pendentes até a reconexão."*

#### 🤖 Prompt para Execução (Tarefa 6.1)
```markdown
Implemente o suporte a Progressive Web App (PWA) e detecção offline no SIAC:
1. Configure o manifesto da aplicação (`public/manifest.json` ou via `vite-plugin-pwa` no `vite.config.js`):
   - Nome: "SIAC - Consucal"
   - Short name: "SIAC"
   - Display: "standalone"
   - Start URL: "/"
   - Cores de fundo e tema alinhadas ao design system do app.
   - Mapeamento de ícones (192x192 e 512x512).
2. No `resources/views/app.blade.php`, adicione a tag `<link rel="manifest" href="/manifest.json">` e meta tags compatíveis com iOS/Apple mobile web app.
3. Crie um composable ou componente global `OfflineIndicator.vue` que escute eventos `online` e `offline` do navegador e apresente um banner discreto no topo quando a conexão for perdida.
4. Execute `npm run build` e valide a conformidade com as regras de bundle.
```

---

<a id="modulo-7-central-de-notificacoes-e-alertas"></a>
## 7. Módulo 7: Central de Notificações e Alertas

### Tarefa 7.1: Sistema de notificações no sistema (Sininho no cabeçalho e disparadores de eventos)
- **Contexto:** O cliente e a equipe precisam ser notificados sobre eventos críticos sem precisar navegar exaustivamente:
  1. Comentários adicionados em relatórios ou coletas.
  2. Modificações ou exclusões em lote realizadas na tabela de dados.
  3. Recálculos concluídos.
- **Arquivos Envolvidos:**
  - Migração de tabela de notificações (ou uso da tabela padrão `notifications` do Laravel)
  - `app/Features/Notifications` (ou Models/Services correspondentes)
  - `resources/js/app/components/AppHeader.vue` (ícone de sino com contador)
  - Componente de dropdown de notificações recentes
- **Critérios de Aceite:**
  - Ícone de sino com badge indicando o número de notificações não lidas no `AppHeader`.
  - Dropdown com a lista das notificações recentes e opção de "Marcar como lida".
  - Evento disparado quando um comentário for inserido em relatório ou coleta.
  - Base pronta para futuramente enviar Web Push Notifications no PWA.

#### 🤖 Prompt para Execução (Tarefa 7.1)
```markdown
Implemente a Central de Notificações no sistema:
1. No Backend:
   - Verifique ou crie o suporte à tabela de notificações do Laravel (`php artisan make:notifications-table`).
   - Crie as classes de notificação necessárias (ex: `ReportCommentedNotification`, `BatchDataModifiedNotification`).
   - Crie rotas e controller sob `app/Features/Notifications` para listar notificações não lidas e marcar como lidas.
2. No Frontend:
   - Em `resources/js/app/components/AppHeader.vue`, adicione um botão de sino (`Bell`) com badge numérico de notificações não lidas ao lado do avatar.
   - Ao clicar, exiba um `DropdownMenu` com as últimas notificações, autor, data/hora formatada e link direto para o recurso (relatório, coleta, etc.).
3. Escreva testes Pest cobrindo a criação e leitura das notificações.
```

---

<a id="notas-adicionais"></a>
## 8. Notas Adicionais e Pontos de Atenção Levantados na Reunião

1. **Importação Oficial de Dados de Produção:**
   - Durante a reunião, foi apontado que em uma importação prévia houve uma defasagem de 1 hora nas linhas. André destacou que na importação definitiva será realizada uma rotina detalhada de validação de timezone/fuso horário para garantir precisão absoluta dos dados históricos.
2. **Dashboard Inicial vs. Dashboard de Processo:**
   - Foi acordado separar o escopo:
     - **Dashboard Inicial:** Resumo simplificado da unidade (quantidade de relatórios emitidos, dados fora de limite nos últimos 7/30 dias, atalhos rápidos).
     - **Dashboard de Processo (Power BI interno):** Módulo analítico avançado com filtros customizados que será disponibilizado em etapa posterior.
3. **Fluxo de Trabalho Local e Homologação:**
   - As alterações são desenvolvidas e testadas localmente com a suite de testes automatizados (`php artisan test`) e build do frontend antes do deploy para o ambiente de testes do cliente.

---

*Fim do documento de tarefas.*
