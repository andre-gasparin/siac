<?php

namespace App\Console\Commands;

use App\Features\SpreadsheetImports\Actions\ProcessSpreadsheetImportQueueAction;
use Exception;
use Illuminate\Console\Command;

class ProcessSpreadsheetImportQueueCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'spreadsheet-imports:process-queue
                            {--limit=20 : Limite de itens por execução}
                            {--watch : Manter o comando ativo escutando continuamente por novos itens}
                            {--sleep=3 : Tempo de espera em segundos entre verificações quando em modo watch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Processar itens pendentes na fila customizada de importação de planilhas.';

    /**
     * Execute the console command.
     */
    public function handle(ProcessSpreadsheetImportQueueAction $action): int
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        $limit = (int) $this->option('limit');
        $isWatch = (bool) $this->option('watch');
        $sleepSeconds = max(1, (int) $this->option('sleep'));

        if ($isWatch) {
            $this->info("Fila de importação de planilhas ativa em modo contínuo (checando a cada {$sleepSeconds}s)...");
        } else {
            $this->info("Iniciando processamento da fila de importação de planilhas (limite: {$limit})...");
        }

        do {
            try {
                $result = $action->execute($limit);

                if ($result['processed_count'] > 0) {
                    $this->info("Processamento finalizado: {$result['processed_count']} processados, {$result['success_count']} sucessos, {$result['failed_count']} falhas.");

                    foreach ($result['details'] as $detail) {
                        if ($detail['status'] === 'completed') {
                            $this->line(" [OK] #{$detail['queue_id']} {$detail['file_name']} - {$detail['saved_count']} medições salvas.");
                        } else {
                            $this->error(" [FALHA] #{$detail['queue_id']} {$detail['file_name']} - Erro: {$detail['error']}");
                        }
                    }
                } elseif (! $isWatch) {
                    $this->info('Nenhum item pendente na fila.');
                }
            } catch (Exception $e) {
                $this->error("Erro fatal ao processar a fila: {$e->getMessage()}");

                if (! $isWatch) {
                    return self::FAILURE;
                }
            }

            if ($isWatch) {
                sleep($sleepSeconds);
            }
        } while ($isWatch);

        return self::SUCCESS;
    }
}
