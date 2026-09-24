<?php

namespace App\Console\Commands;

use App\Features\SpreadsheetImports\Actions\FetchSpreadsheetEmailsAction;
use Exception;
use Illuminate\Console\Command;

class FetchSpreadsheetEmailsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'spreadsheet-imports:fetch-emails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verificar caixa de e-mail e capturar anexos de planilhas correspondentes às regras cadastradas.';

    /**
     * Execute the console command.
     */
    public function handle(FetchSpreadsheetEmailsAction $action): int
    {
        $this->info('Iniciando verificação de e-mails para captura de planilhas...');

        try {
            $result = $action->execute();

            $this->info("Verificação concluída: {$result['emails_checked']} e-mails checados, {$result['attachments_captured']} anexos capturados.");
            foreach ($result['messages'] as $msg) {
                $this->line(" - {$msg}");
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error("Erro durante verificação de e-mails: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
