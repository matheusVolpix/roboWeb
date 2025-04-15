<?php

namespace App\Services;

use App\Models\Movimento;
use Illuminate\Support\Facades\Log;
use App\Models\User; // Exemplo de modelo que pode ser usado para consultas

class BaixaEstoque
{
    public function executarTarefa()
    {
        $movimento = Movimento::on("pgsql_main")->get();

        Log::info($movimento);
    }
}
