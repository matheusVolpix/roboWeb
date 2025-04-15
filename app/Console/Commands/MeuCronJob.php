<?php

namespace App\Console\Commands;

use App\Services\BaixaEstoque;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;


class MeuCronJob extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:meu-cron-job';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected BaixaEstoque $baixaEstoque;

    public function __construct(BaixaEstoque $baixaEstoque)
    {
        parent::__construct();
        $this->baixaEstoque = $baixaEstoque;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->baixaEstoque->executarTarefa();
    }
}
