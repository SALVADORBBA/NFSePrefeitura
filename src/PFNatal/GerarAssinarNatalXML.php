<?php

use NFSePrefeitura\NFSe\PFNatal\NatalGeradorXML;
use NFSePrefeitura\NFSe\PFNatal\AssinaturaNatal;
use NFSePrefeitura\NFSe\PFNatal\EnvioNatal;

class GerarNFSeNatal
{
    private $certPath;
    private $certPassword;
    private $key;
    private $baseDir = 'app/xml_nfse/Natal';
    private $fiscal;

    public function __construct($key)
    {
        $this->key    = $key;
        $this->fiscal = NfseRps::find($this->key);

        if (!$this->fiscal) {
            throw new \Exception("RPS/NFSe não encontrado. ID: {$this->key}");
        }

        $emit = $this->fiscal->nfse_emitente;
        $this->certPath     = $emit->cert_pfx_blob ?? null;
        $this->certPassword = $emit->cert_senha_plain ?? null;
    }

    public function processar()
    {
        $prestador = $this->fiscal->nfse_emitente;
        if (!$prestador) {
            throw new \Exception("Emitente/Prestador não encontrado para o RPS {$this->key}");
        }

        $tomador = $this->fiscal->tomador;
        if (!$tomador) {
            throw new \Exception("Tomador não encontrado para o RPS {$this->key}");
        }

        $servicos = NfseRpsServico::where('nfse_rps_id', '=', $this->fiscal->id)->get();
        if (!$servicos || count($servicos) < 1) {
            throw new \Exception("RPS {$this->fiscal->id} não possui serviços.");
        }

        if (!$this->certPath || !$this->certPassword) {
            throw new \Exception("Certificado/senha não informados para o emitente do RPS {$this->key}.");
        }

        // 1) Monta
  $dados = $this->buildDadosLote($this->fiscal, $prestador, $tomador, $servicos);

        // // 2) Valida (manual + regras Natal)
        // $this->validateDadosLote($dados);

        // 3) Gera XML do lote (sem assinatura)
        // $natal = new Natal();
        // $xml   = (string) $natal->gerarXmlLoteRps($dados);

      
     $xml = (new NatalGeradorXML())->gerarXmlLoteRps($dados);

        $arquivo = $this->salvarArquivo('01_inicial.xml', $this->dir('Inicial'), $xml);
        $this->log("PASSO 2 - XML gerado\nArquivo: {$arquivo}");

        // 4) Assina
        $xmlAssinado = $this->assinarXml($xml);

        // 5) Envia
        return $this->enviarParaWebservice($xmlAssinado);
    }

    private function assinarXml(string $xml): string
    {
        $assinatura  = new AssinaturaNatal($this->certPath, $this->certPassword);
        $xmlAssinado = (string) $assinatura->assinarXmlNatalEstruturado($xml);

        $arquivo = $this->salvarArquivo('02_assinado.xml', $this->dir('Assinados'), $xmlAssinado);
        $this->log("PASSO 3 - XML assinado\nArquivo: {$arquivo}");

        return $xmlAssinado;
    }

    private function enviarParaWebservice(string $xmlAssinado): string
    {
        $envio = new EnvioNatal($this->certPath, $this->certPassword);

        $retObj = $envio->enviarLoteRps($xmlAssinado);

        $arquivoObj = $this->salvarArquivo('03_response_obj.json', $this->dir('Respostas'), $retObj);
        $this->log("PASSO 4 - Retorno objeto salvo\nArquivo: {$arquivoObj}");

        $soapRequest  = method_exists($envio, 'getLastRequest')  ? $envio->getLastRequest()  : null;
        $soapResponse = method_exists($envio, 'getLastResponse') ? $envio->getLastResponse() : null;

        if ($soapRequest) {
            $arquivoReq = $this->salvarArquivo('03_request_soap.xml', $this->dir('Respostas'), (string)$soapRequest);
            $this->log("PASSO 5 - SOAP REQUEST salvo\nArquivo: {$arquivoReq}");
        }

        if ($soapResponse) {
            $arquivoRes = $this->salvarArquivo('03_response_soap.xml', $this->dir('Respostas'), (string)$soapResponse);
            $this->log("PASSO 6 - SOAP RESPONSE salvo\nArquivo: {$arquivoRes}");
            return (string)$soapResponse;
        }

        return $this->normalizeConteudo($retObj);
    }

    private function log(string $mensagem): void
    {
        echo "<pre>{$mensagem}</pre>";
    }

    /* =========================================================
     * BUILDERS
     * ========================================================= */

    /**
     * Remove caracteres especiais de uma string, mantendo apenas letras, números, espaço e pontuação básica.
     */
    private function sanitizeString($value) {
        // Remove tags HTML
        $value = strip_tags($value);
        // Remove/controla caracteres especiais, mantendo letras, números, espaço, ponto, vírgula, hífen, barra, parênteses
        $value = preg_replace('/[^\w\s\.,\-\/\(\)]/u', '', $value);
        // Remove múltiplos espaços
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }

    private function buildDadosLote($nfse, $prestador, $tomador, $servicos): array
    {
        $cnpjPrestador = $this->onlyDigits((string)($prestador->cnpj ?? ''));
        $inscMunPrestador = $this->sanitizeString((string)($prestador->inscricao_municipal ?? ''));

        $serv = $this->buildServicoAgregado($nfse, $prestador, $servicos);
        $tom  = $this->buildTomador($tomador);

        $serie  = $this->sanitizeString((string)($nfse->serie ?? 'A'));
        $numero = $this->sanitizeString((string)($nfse->numero ?? $nfse->numero_rps ?? '1'));
        $infId  = $this->gerarNumeroRps($serie, (int)$numero, $cnpjPrestador);

        $dataEmissao = date('Y-m-d\TH:i:s');
        $naturezaOperacao = (int)($nfse->natureza_operacao ?? $nfse->naturezaOperacao ?? 1);
        $rawOpt = $prestador->optante_simples_nacional
            ?? $prestador->optanteSimplesNacional
            ?? $nfse->optante_simples_nacional
            ?? $nfse->optanteSimplesNacional
            ?? 2;
        $optanteSimplesNacional = $this->toSimNao($rawOpt);
        $incentivadorCultural = $this->toSimNao($nfse->incentivador_cultural ?? $nfse->incentivadorCultural ?? 2);
        $status               = (int)($nfse->status ?? 1);

        $codigoMunicipio = $this->sanitizeString((string)(
            $prestador->codigo_municipio
            ?? $prestador->codigoMunicipio
            ?? $serv['codigoMunicipio']
            ?? ''
        ));

        $codigoTributacaoMunicipio = $this->sanitizeString((string)(
            $nfse->codigo_tributacao_municipio
            ?? $nfse->codigoTributacaoMunicipio
            ?? ''
        ));

        $codigoCnae = $this->sanitizeString((string)($prestador->codigo_cnae ?? $prestador->codigocnae ?? ''));
        $codigoNbs  = $this->sanitizeString((string)($prestador->codigo_nbs ?? $nfse->codigo_nbs ?? ''));

        $rpsItem = [
            'inf_id' => $infId,
            'infRps' => [
                'numero'      => $numero,
                'serie'       => $serie,
                'tipo'        => 1,
                'dataEmissao' => $dataEmissao,
            ],

            // >>> chaves em duplicidade (case) para não falhar validator por diferença de nome:
            'naturezaOperacao' => $naturezaOperacao,
            'NaturezaOperacao' => $naturezaOperacao,

            'optanteSimplesNacional' => $optanteSimplesNacional,
            'OptanteSimplesNacional' => $optanteSimplesNacional,

            'incentivadorCultural' => $incentivadorCultural,
            'IncentivadorCultural' => $incentivadorCultural,

            'status' => $status,
            'Status' => $status,

            // Valores (use minúsculo e maiúsculo)
            'valorServicos' => (float)$serv['valorServicos'],
            'ValorServicos' => (float)$serv['valorServicos'],

            'issRetido'   => (int)$serv['issRetido'],
            'IssRetido'   => (int)$serv['issRetido'],

            'baseCalculo' => (float)$serv['baseCalculo'],
            'BaseCalculo' => (float)$serv['baseCalculo'],

            // esses 2 podem ser omitidos por regra Natal dependendo do caso
            'aliquota'    => $serv['aliquota'], // float|null
            'Aliquota'    => $serv['aliquota'],
            'valorIss'    => $serv['valorIss'], // float|null
            'ValorIss'    => $serv['valorIss'],

            'discriminacao'   => (string)$serv['discriminacao'],
            'Discriminacao'   => (string)$serv['discriminacao'],

            'itemListaServico' => (string)$serv['itemListaServico'],
            'ItemListaServico' => (string)$serv['itemListaServico'],

            'codigoMunicipio'           => $codigoMunicipio,
            'CodigoMunicipio'           => $codigoMunicipio,

            'codigoTributacaoMunicipio' => $codigoTributacaoMunicipio,
            'CodigoTributacaoMunicipio' => $codigoTributacaoMunicipio,

            'codigoCnae' => $codigoCnae,
            'CodigoCnae' => $codigoCnae,

            // no manual o tipo simples é tsCodigoNbs (e tamanho no anexo)
            'codigoNbs' => $codigoNbs,
            'CodigoNbs' => $codigoNbs,

            'tomador' => $tom,
            'Tomador' => $tom,

            // Adiciona IBSCBS calculado pelo serviço
            'IBSCBS' => (function() use ($serv, $prestador, $nfse, $tomador) {
                // Recupera UF do emitente e do cliente
                $emitente_UF = $prestador->uf ?? $prestador->UF ?? '';
                $cliente_UF = $tomador->uf ?? $tomador->UF ?? '';
                // Recupera valores do item
                $vProdItem = $serv['valorServicos'] ?? 0;
                $vDescItem = 0; // Se houver campo de desconto, ajustar aqui
                $ibscbsCalc = $this->calcularIbscbsItens($vProdItem, $vDescItem, $emitente_UF, $cliente_UF);
                return $ibscbsCalc['itens'][0]['IBSCBS'] ?? null;
            })(),
            'construcaoCivil' => [
                'codigoObra' => (string)($nfse->codigo_obra ?? ''),
                'art'        => (string)($nfse->art ?? ''),
            ],
        ];

        $loteN = date('ymdHis') . random_int(100, 999);

        $dados = [
            'lote_id'            => $loteN,
            'numeroLote'         => $loteN,
            'cnpjPrestador'      => $cnpjPrestador,
            'inscricaoMunicipal' => $inscMunPrestador,
            'quantidadeRps'      => 1,

            'NomeFantasia' => (string)($prestador->nome_fantasia ?? ''),
            'RazaoSocial'  => (string)($prestador->razao_social ?? ''),

            'rps' => [$rpsItem],
        ];

        // aplica regras Natal que exigem omissão de tags em certos cenários
        $this->applyNatalBusinessRules($dados);

        return $dados;
    }

    private function buildServicoAgregado($nfse, $prestador, $servicos): array
    {
        $totalServ = 0.0;
        $totalIss  = 0.0;

        $aliquota  = null;
        $issRetido = 2; // tsSimNao (1=sim/retido, 2=não) :contentReference[oaicite:14]{index=14}

        $itemListaServico = trim((string)($nfse->itemListaServico ?? $nfse->item_lista_servico ?? ''));
        $discriminacao = 'SERVICOS PRESTADOS.';

        foreach ($servicos as $s) {
            $vServ = (float)($s->valor_servicos ?? 0);
            $vIss  = (float)($s->valor_iss ?? 0);
            $pAliq = (float)($s->aliquota ?? 0);

            $totalServ += $vServ;
            $totalIss  += $vIss;

            if ($aliquota === null && $pAliq > 0) {
                $aliquota = $pAliq;
            }

            if ((int)($s->iss_retido ?? 2) === 1) {
                $issRetido = 1;
            }
        }

        if ($aliquota === null) {
            $aliquota = 0.0;
        }

        // BaseCalculo é obrigatória em Natal :contentReference[oaicite:15]{index=15} :contentReference[oaicite:16]{index=16}
        $baseCalculo = max(0, $totalServ);

        return [
           // 'valorServicos'    => $totalServ,
            'baseCalculo'      => $baseCalculo,
            'issRetido'        => $issRetido,
            'aliquota'         => $aliquota,
            'valorIss'         => $totalIss,
            'itemListaServico' => $itemListaServico,
            'discriminacao'    => $discriminacao,
            'codigoMunicipio'  => (string)($prestador->codigo_municipio ?? $prestador->codigoMunicipio ?? ''),
        ];
    }

    private function buildTomador($tomador): array
    {
        $doc = $this->onlyDigits((string)($tomador->cnpj ?? $tomador->cpf_cnpj ?? ''));
        $razao = trim((string)($tomador->razao_social ?? $tomador->nome ?? ''));

        $end = [
            'logradouro'      => (string)($tomador->logradouro ?? ''),
            'numero'          => (string)($tomador->numero ?? ''),
            'bairro'          => (string)($tomador->bairro ?? ''),
            'codigoMunicipio' => (string)($tomador->codigo_municipio ?? $tomador->codigoMunicipio ?? ''),
            'uf'              => (string)($tomador->uf ?? ''),
            'cep'             => (string)($tomador->cep ?? ''),
        ];

        return [
            'cpfCnpj'     => $doc,
            'razaoSocial' => $razao,
            'endereco'    => $end,
            'contato'     => [
                'telefone' => (string)($tomador->fone ?? $tomador->telefone ?? ''),
                'email'    => (string)($tomador->email ?? ''),
            ],
        ];
    }

    /* =========================================================
     * VALIDAÇÃO (Manual + Regras Natal)
     * ========================================================= */

    private function validateDadosLote(array $dados): void
    {
        $this->required($dados, 'numeroLote');
        $this->required($dados, 'cnpjPrestador');
        $this->required($dados, 'quantidadeRps');
        $this->required($dados, 'rps');

        // tsCnpj 14 :contentReference[oaicite:17]{index=17}
        $this->assertDigitsLen($dados['cnpjPrestador'], 14, 'cnpjPrestador');

        // tsQuantidadeRps 4 (tamanho máx 4) :contentReference[oaicite:18]{index=18}
        $this->assertIntRange($dados['quantidadeRps'], 1, 9999, 'quantidadeRps');

        if (!is_array($dados['rps']) || count($dados['rps']) < 1) {
            throw new \Exception("rps deve conter ao menos 1 item.");
        }

        foreach ($dados['rps'] as $idx => $rpsItem) {
            $this->validateRpsItem($rpsItem, "rps[{$idx}]");
        }
    }

    private function validateRpsItem(array $rpsItem, string $path): void
    {
        $this->required($rpsItem, 'infRps', "{$path}.infRps");

        // Identificação RPS: Numero (15), Serie (5), Tipo (1) :contentReference[oaicite:19]{index=19} :contentReference[oaicite:20]{index=20}
        $infRps = $rpsItem['infRps'];

        $this->required($infRps, 'numero', "{$path}.infRps.numero");
        $this->required($infRps, 'serie', "{$path}.infRps.serie");
        $this->required($infRps, 'tipo', "{$path}.infRps.tipo");
        $this->required($infRps, 'dataEmissao', "{$path}.infRps.dataEmissao");

        $this->assertDigitsMaxLen((string)$infRps['numero'], 15, "{$path}.infRps.numero"); // tsNumeroRps 15 :contentReference[oaicite:21]{index=21}
        $this->assertStrMaxLen((string)$infRps['serie'], 5, "{$path}.infRps.serie");       // tsSerieRps 5 :contentReference[oaicite:22]{index=22}
        $this->assertEqualsInt((int)$infRps['tipo'], 1, "{$path}.infRps.tipo (Natal só aceita Tipo=1)"); // :contentReference[oaicite:23]{index=23}

        // datetime AAAA-MM-DDTHH:mm:ss :contentReference[oaicite:24]{index=24}
        $this->assertDatetime($infRps['dataEmissao'], "{$path}.infRps.dataEmissao");

        // Obrigatórios do RPS (validator e manual)
        $natureza = $rpsItem['naturezaOperacao'] ?? $rpsItem['NaturezaOperacao'] ?? null;
        $optSN    = $rpsItem['optanteSimplesNacional'] ?? $rpsItem['OptanteSimplesNacional'] ?? null;
        $incent   = $rpsItem['incentivadorCultural'] ?? $rpsItem['IncentivadorCultural'] ?? null;
        $status   = $rpsItem['status'] ?? $rpsItem['Status'] ?? null;

        if ($natureza === null) throw new \Exception("Campo obrigatório do RPS não informado: naturezaOperacao");
        if ($optSN === null)    throw new \Exception("Campo obrigatório do RPS não informado: optanteSimplesNacional");

        // NaturezaOperacao: códigos 1..8 (tamanho 2) :contentReference[oaicite:25]{index=25}
        $this->assertIntRange((int)$natureza, 1, 8, "{$path}.naturezaOperacao");

        // tsSimNao 1/2 :contentReference[oaicite:26]{index=26}
        $this->assertInSetInt((int)$optSN, [1,2], "{$path}.optanteSimplesNacional");
        $this->assertInSetInt((int)$incent, [1,2], "{$path}.incentivadorCultural");

        // Status (no manual é “tsStatusRps” 1=normal 2=cancelado) :contentReference[oaicite:27]{index=27}
        $this->assertInSetInt((int)$status, [1,2], "{$path}.status");

        // Valores: ValorServicos (obrig), IssRetido (obrig), BaseCalculo (obrig) :contentReference[oaicite:28]{index=28}
        $valorServ = $rpsItem['valorServicos'] ?? $rpsItem['ValorServicos'] ?? null;
        if ($valorServ === null) {
            throw new \Exception("Campo obrigatório do RPS não informado: valorServicos");
        }
        $this->assertDecimal2($valorServ, "{$path}.valorServicos");

        $issRetido = $rpsItem['issRetido'] ?? $rpsItem['IssRetido'] ?? null;
        $baseCalc  = $rpsItem['baseCalculo'] ?? $rpsItem['BaseCalculo'] ?? null;

        if ($issRetido === null) throw new \Exception("Campo obrigatório do RPS não informado: issRetido");
        if ($baseCalc === null)  throw new \Exception("Campo obrigatório do RPS não informado: baseCalculo");

        $this->assertInSetInt((int)$issRetido, [1,2], "{$path}.issRetido"); // tsSimNao :contentReference[oaicite:29]{index=29}
        $this->assertDecimal2($baseCalc, "{$path}.baseCalculo");

        // ItemListaServico (6) e Discriminacao (2000) :contentReference[oaicite:30]{index=30}
        $itemLista = $rpsItem['itemListaServico'] ?? $rpsItem['ItemListaServico'] ?? '';
        $disc      = $rpsItem['discriminacao'] ?? $rpsItem['Discriminacao'] ?? '';

        $this->requiredStr($itemLista, "{$path}.itemListaServico");
        $this->assertStrMaxLen((string)$itemLista, 6, "{$path}.itemListaServico");

        $this->requiredStr($disc, "{$path}.discriminacao");
        $this->assertStrMaxLen((string)$disc, 2000, "{$path}.discriminacao");

        // CNAE (7) :contentReference[oaicite:31]{index=31}
        $cnae = $rpsItem['codigoCnae'] ?? $rpsItem['CodigoCnae'] ?? '';
        if ($cnae !== '') {
            $this->assertDigitsMaxLen((string)$cnae, 7, "{$path}.codigoCnae");
        }

        // Tomador (se você quer exigir sempre)
        $tom = $rpsItem['tomador'] ?? $rpsItem['Tomador'] ?? null;
        if ($tom) {
            $doc = $tom['cpfCnpj'] ?? '';
            if ($doc !== '') {
                $docD = $this->onlyDigits((string)$doc);
                if (!(strlen($docD) === 11 || strlen($docD) === 14)) {
                    throw new \Exception("{$path}.tomador.cpfCnpj inválido (CPF 11 ou CNPJ 14).");
                }
            }
        }
    }

    /**
     * Regras específicas Natal (anexo de validações):
     * - NÃO enviar ValorDeducoes e NÃO enviar descontos: deve ser omitido :contentReference[oaicite:32]{index=32}
     * - BaseCalculo é obrigatória :contentReference[oaicite:33]{index=33}
     * - Regras de Aliquota/ValorIss conforme OptanteSN/Natureza/Retenção :contentReference[oaicite:34]{index=34} :contentReference[oaicite:35]{index=35}
     */
    private function applyNatalBusinessRules(array &$dados): void
    {
        if (empty($dados['rps'][0])) return;

        foreach ($dados['rps'] as &$rps) {

            // 1) omitir deduções/descontos SEMPRE
            unset(
                $rps['ValorDeducoes'], $rps['valorDeducoes'],
                $rps['DescontoCondicionado'], $rps['descontoCondicionado'],
                $rps['DescontoIncondicionado'], $rps['descontoIncondicionado']
            );

            $natureza = (int)($rps['naturezaOperacao'] ?? $rps['NaturezaOperacao'] ?? 0);
            $optSN    = (int)($rps['optanteSimplesNacional'] ?? $rps['OptanteSimplesNacional'] ?? 2);
            $issRet   = (int)($rps['issRetido'] ?? $rps['IssRetido'] ?? 2);

            // 2) Natureza Isenção(3) ou Imune(4): Aliquota e ValorIss DEVEM ser omitidos :contentReference[oaicite:36]{index=36}
            if (in_array($natureza, [3,4], true)) {
                $this->unsetAliquotaValorIss($rps);
                continue;
            }

            // 3) Optante SN sem retenção (issRetido=2): Aliquota não deve ser informada e ValorIss não é calculado :contentReference[oaicite:37]{index=37}
            if ($optSN === 1 && $issRet === 2) {
                $this->unsetAliquotaValorIss($rps);
                continue;
            }

            // 4) Se chegou aqui, pode exigir alíquota em alguns cenários:
            //    - Não optante (optSN=2) e natureza 1/2 => alíquota pode ser obrigatória (conforme anexo N5) :contentReference[oaicite:38]{index=38}
            //    - Optante (optSN=1) com retenção => alíquota deve ser informada (N10) :contentReference[oaicite:39]{index=39}
            $aliq = $rps['aliquota'] ?? $rps['Aliquota'] ?? null;

            if (($optSN === 2 && in_array($natureza, [1,2], true)) || ($optSN === 1 && $issRet === 1)) {
                // se não veio, tenta calcular/assumir (aqui eu só valido presença; ideal buscar da tabela municipal)
                if ($aliq === null || (float)$aliq <= 0) {
                    throw new \Exception("Regra Natal: alíquota obrigatória para este cenário (optSN={$optSN}, issRetido={$issRet}, natureza={$natureza}).");
                }
            }

            // 5) ValorIss: quando informado, deve ser BaseCalculo * Aliquota (arredondamento) :contentReference[oaicite:40]{index=40}
            $base = (float)($rps['baseCalculo'] ?? $rps['BaseCalculo'] ?? 0);
            $vIss = $rps['valorIss'] ?? $rps['ValorIss'] ?? null;

            // se for um cenário que calcula ISS, valida consistência quando vier
            if ($vIss !== null && $aliq !== null && (float)$aliq > 0) {
                $calc = round($base * (float)$aliq, 2);
                $vIssN = round((float)$vIss, 2);
                if (abs($calc - $vIssN) > 0.01) {
                    throw new \Exception("Regra Natal: valorIss inválido. Esperado {$calc} (= baseCalculo*aliquota) e veio {$vIssN}.");
                }
            }
        }
        unset($rps);
    }

    private function unsetAliquotaValorIss(array &$rps): void
    {
        unset($rps['aliquota'], $rps['Aliquota'], $rps['valorIss'], $rps['ValorIss']);
    }

    /* =========================================================
     * Helpers de validação (tamanho/formato)
     * ========================================================= */

    private function required(array $arr, string $key, string $path = null): void
    {
        if (!array_key_exists($key, $arr) || $arr[$key] === null || $arr[$key] === '') {
            $p = $path ?: $key;
            throw new \Exception("Campo obrigatório não informado: {$p}");
        }
    }

    private function requiredStr($v, string $path): void
    {
        if (trim((string)$v) === '') {
            throw new \Exception("Campo obrigatório não informado: {$path}");
        }
    }

    private function assertStrMaxLen(string $v, int $max, string $path): void
    {
        if (mb_strlen($v) > $max) {
            throw new \Exception("{$path} excede tamanho máximo {$max}.");
        }
    }

    private function assertDigitsLen(string $v, int $len, string $path): void
    {
        if (!preg_match('/^\d+$/', $v) || strlen($v) !== $len) {
            throw new \Exception("{$path} inválido. Deve conter exatamente {$len} dígitos.");
        }
    }

    private function assertDigitsMaxLen(string $v, int $max, string $path): void
    {
        if ($v === '' || !preg_match('/^\d+$/', $v) || strlen($v) > $max) {
            throw new \Exception("{$path} inválido. Deve ser numérico e ter no máximo {$max} dígitos.");
        }
    }

    private function assertIntRange($v, int $min, int $max, string $path): void
    {
        if (!is_numeric($v)) {
            throw new \Exception("{$path} inválido. Deve ser numérico.");
        }
        $i = (int)$v;
        if ($i < $min || $i > $max) {
            throw new \Exception("{$path} fora do intervalo {$min}..{$max}.");
        }
    }

    private function assertInSetInt(int $v, array $allowed, string $path): void
    {
        if (!in_array($v, $allowed, true)) {
            throw new \Exception("{$path} inválido. Permitidos: " . implode(',', $allowed));
        }
    }

    private function assertEqualsInt(int $v, int $expected, string $path): void
    {
        if ($v !== $expected) {
            throw new \Exception("{$path} inválido. Esperado {$expected}.");
        }
    }

    private function assertDatetime($v, string $path): void
    {
        $s = (string)$v;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $s)) {
            throw new \Exception("{$path} inválido. Formato esperado AAAA-MM-DDTHH:mm:ss.");
        }
    }

    // decimal 0.00 (manual) :contentReference[oaicite:41]{index=41}
    private function assertDecimal2($v, string $path): void
    {
        if (!is_numeric($v)) {
            throw new \Exception("{$path} inválido. Deve ser decimal (ex: 0.00).");
        }
        // força 2 casas quando transformar em string (mas aqui só valida que é número)
        // regra de “sem milhar” é no XML, então garanta que você não está mandando string com vírgula.
        if (is_string($v) && (strpos($v, ',') !== false)) {
            throw new \Exception("{$path} inválido. Use ponto como separador decimal (ex: 1234.56).");
        }
    }

    private function toSimNao($v): int
    {
        // manual tsSimNao: 1=Sim, 2=Não :contentReference[oaicite:42]{index=42}
        if (is_bool($v)) return $v ? 1 : 2;

        if (is_numeric($v)) {
            $i = (int)$v;
            if ($i === 1) return 1;
            if ($i === 2) return 2;
            if ($i === 0) return 2; // comum em banco: 0 = não
            return ($i > 0) ? 1 : 2;
        }

        $s = strtolower(trim((string)$v));
        if (in_array($s, ['1','s','sim','true','t','yes','y'], true)) return 1;
        return 2;
    }

    /* =========================================================
     * Utils
     * ========================================================= */

    private function onlyDigits(string $v): string
    {
        return preg_replace('/\D+/', '', $v) ?? '';
    }

    private function salvarArquivo(string $nome, string $dir, $conteudo): string
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $arquivo = rtrim($dir, '/\\') . '/' . date('Ymd_His_') . $nome;
        $data = $this->normalizeConteudo($conteudo);
        file_put_contents($arquivo, $data);

        return $arquivo;
    }

    private function normalizeConteudo($conteudo): string
    {
        if ($conteudo === null) return '';
        if (is_string($conteudo)) return $conteudo;
        if (is_scalar($conteudo)) return (string)$conteudo;

        return json_encode($conteudo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
    }

    private function gerarNumeroRps(string $serie, int $numero, string $cnpj): string
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        $cnpjPrefixo = substr($cnpj, 0, 3);
        $timestamp = date('ymdHis');
        $aleatorio = random_int(10, 99);

        $rps = $serie
            . str_pad((string)$numero, 3, '0', STR_PAD_LEFT)
            . $cnpjPrefixo
            . $timestamp
            . $aleatorio;

        return substr($rps, 0, 20);
    }

    private function dir(string $sub): string
    {
        return rtrim($this->baseDir, '/\\') . '/' . trim($sub, '/\\');
    }

    /**
     * Calcula os dados de IBS/CBS para os itens usando o serviço IbsCbsJsonService.
     */
    private function calcularIbscbsItens($vProdItem, $vDescItem, $emitente_UF, $cliente_UF)
    {
        $data_emissao = date('Y-m-d H:i:s');
        $vBaseItem = max(0, (float)$vProdItem - (float)$vDescItem);

        $notaObj = (object)[
            'data_emissao' => substr((string)($data_emissao ?: date('Y-m-d')), 0, 10),
        ];

        $classTrib = '00001';
        $cstIbsCbs = '000';

        $itemObj = (object)[
            'valor_total' => $vBaseItem,
            'ibs_classificacao' => $classTrib,
            'ibs_cst' => $cstIbsCbs,
        ];

        $itemsObj = [$itemObj];

        $ufEmitente = strtoupper(trim((string)($emitente_UF ?: 'SP')));
        $ufCliente  = strtoupper(trim((string)($cliente_UF  ?: 'RS')));

        $json = IbsCbsJsonService::gerarJsonNota($notaObj, $itemsObj, $ufEmitente, $ufCliente);

        $ibscbsCalc = json_decode($json, true);
        if (!is_array($ibscbsCalc)) {
            throw new Exception('IBS/CBS: retorno JSON inválido (json_decode falhou).');
        }

        return $ibscbsCalc; 
 
    }

}