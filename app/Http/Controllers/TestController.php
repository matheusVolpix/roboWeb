<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Movimento;
use App\Models\Pessoa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TestController extends Controller
{
    public function index()
    {
        $controle = false;
        $commit = false;
        $dbconnectionMain = DB::connection("pgsql_main");
        //agrupamento dos movimentos apenas pelo numerocupomfiscal
        $cupons = Movimento::on("pgsql_secondary")
            ->selectRaw("numerocupomfiscal, COUNT(*) as count, MAX(loja) as loja, MAX(data) as data, MAX(caixa) as caixa, MAX(serialecf) as serialecf")
            ->where("baixado", "N")
            ->groupBy("numerocupomfiscal")
            ->get();

        foreach ($cupons as $cupom) {
            //desacrupamento os movimento para termos cada linha de mesmo numerocupomfiscal
            $movimento = Movimento::on("pgsql_secondary")
                ->where("numerocupomfiscal", $cupom->numerocupomfiscal)
                ->where("caixa", $cupom->caixa)
                ->where("loja", $cupom->loja)
                ->where("serialecf", $cupom->serialecf)
                ->where("data", $cupom->data)
                ->get();

            //para cada linha do movimento
            foreach ($movimento as $i => $item) {
                if ($item->tiporegistro == "NN") {
                    $controle = true;
                }

                //caso essa linha do movimento seja CC
                if ($item->tiporegistro == 'CC') {
                    //pega o numero do item CC
                    $numerocupomfiscalCC = $item->numerocupomfiscal;

                    //se CC for a ultima linha dos movimentos
                    $isUltimoItem = ($i === $movimento->keys()->last());

                    if ($isUltimoItem) {
                        // dd($i);

                        //pegamos de novo as linhas do movimento 
                        foreach ($movimento as $j => $cupomCC) {
                            //se a linha $j for igual ao numerocupom que é CC, entra na condição
                            if ($cupomCC->numerocupomfiscal == $numerocupomfiscalCC &&  $cupomCC->tiporegistro != "CC" && $cupomCC->itemcancelado != "S") {
                                dd("É CC e o ultimo", $movimento->toArray());
                                $this->gravaMovimento($cupomCC);
                            }
                        }
                    } else if ($i == 0) {
                        //se for o cancelamento do cupom anterior, precisamos dos movimento de numerocupomfiscal - 1 
                        $numeroAnterior = $item->numerocupomfiscal - 1;

                        $cupomAnteriorCancelado = Movimento::on("pgsql_secondary")
                            ->where("numerocupomfiscal", $numeroAnterior)
                            ->where("caixa", $cupom->caixa)
                            ->where("loja", $cupom->loja)
                            ->where("serialecf", $cupom->serialecf)
                            ->where("data", $cupom->data)
                            ->get();
                        //então executa as procedures com -1
                        foreach ($cupomAnteriorCancelado as $indexToCancel => $cupomToCancel) {
                            //verificação segura se o novo cupomCC é igual ao anterior
                            if ($cupomToCancel->numerocupomfiscal == ($numerocupomfiscalCC - 1) && $cupomToCancel->tiporegistro != "CC" && $cupomToCancel->itemcancelado != "S") {
                                // dd("Executa procedures numerocupomfiscal-1");
                                $this->gravaMovimento($cupomToCancel);
                            }
                        }
                    }
                    // else {

                    //     if ($item->tiporegistro == 'VI') {
                    //         $resultados = $dbconnectionMain->statement("CALL baixa_estoque_venda_online(4,1,'0000000000004', 1, 15.00, 0, '+', '2025-03-24')");


                    //         dd($resultados);
                    //     }
                    // }
                }

                //se for registro de venda de item e não for composto
                if ($item->formula != "S" && $item->itemcancelado != "S" && $item->cupomcancelado != "S" && $item->tiporegistro == "VI") {

                    // dd($item);
                    $setbaixaestoque_online = $dbconnectionMain->statement("CALL setbaixaestoque_online(
                    {$item->codigointernoproduto}::integer, 
                    '{$item->loja}'::character,
                    '{$item->data}'::date, 
                    {$item->quantidade}::numeric, 
                    {$item->custo}::numeric,  
                    {$item->valortotalitem}::numeric, 
                    '-'::character, 
                    '{$item->codigobarras}'::character )");

                    //Inserir ResumoVenda
                    $totaldivididoqtd = $item->valortotalitem / $item->quantidade;
                    $setresumovenda_online = $dbconnectionMain->statement("CALL setresumovenda_online(
                        {$item->loja}::integer,
                        '{$item->data}'::date, 
                        {$item->codigointernoproduto}::integer, 
                    '{$item->codigobarras}'::character,
                    {$item->quantidade}::numeric,
                     {$totaldivididoqtd}::numeric, 
                     '-')");

                    //Inserir ResumoVendaControle
                    if ($controle) {
                        $setresumovendacontrole_online = $dbconnectionMain->statement("CALL setresumovendacontrole_online({$item->loja}, {$item->data}, {$item->codigointernoproduto}, {$item->codigobarras}, {$item->quantidade}, '-')");
                    }
                }

                //se for registro de venda de item e for composto
                if ($item->formula == "S" && $item->itemcancelado != "S" && $item->cupomcancelado != "S" && $item->tiporegistro == "VI") {
                    dd($item);
                    $setbaixaestoque_online = $dbconnectionMain->statement("CALL setbaixaestoque_online ({$item->codigointernoproduto}, {$item->loja}, {$item->data}, {$item->quantidade}, {$item->custo},  {$item->valortotalitem}, '-', 0 )");

                    //Inserir ResumoVenda
                    $totaldivididoqtd = $item->valortotalitem / $item->quantidade;
                    $setresumovenda_online = $dbconnectionMain->statement("CALL setresumovenda_online({$item->loja}, {$item->data}, {$item->codigointernoproduto} ,{$item->codigobarras}, {$item->quantidade}, {$totaldivididoqtd}, '-')");


                    if ($controle) {
                        //Inserir ResumoVendaControle
                        $setresumovendacontrole_online = DB::connection('pgsql')->statement("CALL setresumovendacontrole_online({$item->loja}, {$item->data}, {$item->codigointernoproduto}, {$item->codigobarras}, {$item->quantidade}, '-')");
                    }
                }

                //se for um item de composicao
                if ($item->itemcancelado != "S" && $item->cupomcancelado != "S" && $item->tiporegistro == "PC") {
                    $setbaixacomposicao = $dbconnectionMain->statement("CALL setbaixacomposicao_online('-', {$item->codigointernoproduto}, {$item->loja}, {$item->codigobarras}, {$item->data}, {$item->valortotalitem}, {$item->quantidade}, {$item->custo})");
                }

                //Se for um registro de pagamento convenio
                // dd($movimento->toArray());
                // dd($item->codigo);
                // dd($item->tipopagamento == "CO" && $item->cupomcancelado != "S" && $item->tiporegistro == "PG");
                if ($item->tipopagamento == "CO" && $item->cupomcancelado != "S" && $item->tiporegistro == "PG") {
                    //Fabio pediu para tirar a verificação de CNPJCPF válido e fazer direto com o código do cliente;
                    $pessoa = Pessoa::on("pgsql_main")->where("codigo", $item->codigocliente)->get();
                    if ($pessoa->toArray() != 0) {
                        // dd($pessoa->toArray()['cnpjcpf']);
                        $setbaixaconvenio_online = $dbconnectionMain->statement("CALL setbaixaconvenio_online(
                                '{$pessoa->toArray()[0]['cnpjcpf']}',{$item->loja}, '{$item->data}', '{$item->nome}', {$item->ecf}, {$item->numerocupomfiscal}, {$item->finalizadora},{$item->valortotalcupom}, '-')");
                    } else {
                        $insertMovConvenio = "INSERT INTO movconvenio  (loja, data, cpf, nome, ecf, numerocupomfiscal, finalizadora, valorrecebido, codigocliente) value ({$item->loja}, {$item->data}, {$pessoa->cpnjcpf}, {$item->nome}, {$item->ecf}, {$item->numerocupons}, {$item->valorrecebido}, {$item->codigocliente})";
                    }
                }
                //Se for um registro de pagamento

                if ($item->cupomcancelado != "S" && $item->tiporegistro == "PG") {
                    $valorTotal = $item->valorrecebido - $item->valortroco;
                    $setresumocaixa_online = $dbconnectionMain->unprepared("CALL setresumocaixa_online(
                        '{$item->loja}'::character,
                        '{$item->data}'::date,
                        {$item->caixa}::integer,
                        {$item->finalizadora}::integer,
                        {$valorTotal}::numeric,
                        '-'::character
                    )");
                    $commit = true;
                }

                if ($i == 0 && $item->cupomcancelado != "S" && ($item->tiporegistro == "EM" || $item->tiporegistro == "CB" && $item->tiporegistro == "LX" || $item->tiporegistro == "AC" || $item->tiporegistro == "GV" || $item->tiporegistro == "RZ")) {
                    $commit = true;
                }

                if ($i === $movimento->keys()->last() && $item->tiporegistro == "CC") {
                    $commit = true;
                }
            }
        }

        dd("Saiu das procedures", $cupons->toArray());
    }

    public function gravaMovimento($item)
    {
        $dbconnectionMain = DB::connection("pgsql_main");

        // if ($item->tiporegistro == "NN") {
        //     $resumovendacontrole = $dbconnectionMain->statement("CALL setresumovendacontrole_online({$item->loja}, {$item->data}, {$item->codigointernoproduto}, {$item->codigodebarras}, {$item->qtdcontrole}, {$item->operacao})");
        // }


        if ($item->tiporegistro == 'VI' && $item->itemcancelado != 'S') {

            $codigodebarras = ($item->formula != 'S' ? $item->codigobarras : 0);
            $valorTotalItem = ($item->formula != 'S' ? "" : "{$item->valortotalitem}, ");

            $baixa_estoque = $dbconnectionMain->statement(
                "CALL setbaixaestoque_online(
                    {$item->codigointernoproduto}::integer, 
                    '{$item->loja}'::character,
                    '{$item->data}'::date, 
                    {$item->quantidade}::numeric, 
                    {$item->custo}::numeric,  
                    {$item->valortotalitem}::numeric, 
                    '+'::character, 
                    '{$item->codigobarras}'::character )"
            );

            $totaldivididoqtd = $item->valortotalitem / $item->quantidade;
            $resumo_venda = $dbconnectionMain->statement(

                "CALL setresumovenda_online(
                        {$item->loja}::integer,
                        '{$item->data}'::date, 
                        {$item->codigointernoproduto}::integer, 
                    '{$item->codigobarras}'::character,
                    {$item->quantidade}::numeric,
                     {$totaldivididoqtd}::numeric, 
                     '-')"
            );

            if ($item->tiporegistro == 'CC') {
                $resumo_Venda_controle = $dbconnectionMain->statement("
                 CALL setresumovendacontrole_online({$item->loja}, {$item->data}, {$item->codigointernoproduto}, {$item->codigobarras},
                {$item->quantidade}, {$valorTotalItem}, '+')
                ");
            }


            ///usar o:  if (controle) { ?
        }

        if ($item->tiporegistro = 'PC' && $item->itemcancelado != 'S') {
            // dd($item->codigobarras);
            $codigobarras = trim($item->codigobarras);
            $setbaixacomposicao = $dbconnectionMain->statement(
                "
            CALL setbaixacomposicao_online(
            '+'::character, 
            {$item->codigointernoproduto}::integer, 
            {$item->loja}::integer, 
            '{$codigobarras}'::character,
            '{$item->data}'::date,
            {$item->valortotalitem}::numeric,
            {$item->quantidade}::numeric,
            {$item->custo}::numeric
            )"
            );
        }

        if ($item->tiporegistro = 'PG') {
            $p_total = $item->valorrecebido - $item->valortroco;
            $setresumocaixa = $dbconnectionMain->statement(
                "CALL setresumocaixa_online(
            {$item->loja}::integer, 
            '{$item->data}'::date,
            {$item->caixa}::integer, 
            {$item->finalizadora}::integer, 
            {$p_total}::numeric, 
            '+'::character
            )"
            );
        }

        //faltou verificar esse:
        if ($item->tiporegistro = 'PG' && $item->tipopagamento = 'CO') {
            $pessoa = Pessoa::on("pgsql_main")->selectRaw("*")->where("cnpjcpf", $item->cpf)->get();
            if ($pessoa) {
                $setbaixaconvenio_online = "CALL setbaixaconvenio_online({$item->cpf},{$item->loja}, {$item->data}, {$item->nome}, {$item->ecf}, {$item->numerocupomfiscal}, {$item->finalizadora}, {$item->valortotalcupom}, {$item->operacao})";
            }
        }
    }
}
