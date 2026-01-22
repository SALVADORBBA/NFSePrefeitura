<?php

use NFSePrefeitura\NFSe\PFNatal\Natal;
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

        // Certificado (ajuste aqui se for blob e você precisar salvar em arquivo)
        $this->certPath     = $this->fiscal->nfse_emitente->cert_pfx_blob ?? null;
        $this->certPassword = $this->fiscal->nfse_emitente->cert_senha_plain ?? null;
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
            throw new \Exception("Certificado/cSenha não informados para o emitente do RPS {$this->key}.");
        }

        // 1) Monta array no formato que Natal::gerarXmlLoteRps espera
        $dados = $this->buildDadosLote($this->fiscal, $prestador, $tomador, $servicos);
//   dd(  $dados);  exit;
        // 2) Gera XML do lote (sem assinatura)
            $natal = new Natal();
            $xml   = (string) $natal->gerarXmlLoteRps($dados);
            $arquivo = $this->salvarArquivo('01_inicial.xml', $this->dir('Inicial'), $xml);
            $this->log("PASSO 2 - XML gerado\nArquivo: {$arquivo}");


                 $xmlAssinado = $this->assinarXml($xml);

   
            // PASSO 4 - Enviar para webservice
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
  /**
     * Retorna o SOAP RESPONSE XML como string.
     * Também salva request/response + objeto retornado em JSON.
     */
    private function enviarParaWebservice(string $xmlAssinado): string
    {
        $envio = new EnvioNatal($this->certPath, $this->certPassword);

        // retorno do SoapClient (geralmente objeto/struct)
        $retObj = $envio->enviarLoteRps($xmlAssinado);

        // salva objeto em JSON (para análise)
        $arquivoObj = $this->salvarArquivo('03_response_obj.json', $this->dir('Respostas'), $retObj);
        $this->log("PASSO 4 - Retorno objeto salvo\nArquivo: {$arquivoObj}");

        // tenta pegar o SOAP bruto se EnvioNatal tiver getters
        $soapRequest  = null;
        $soapResponse = null;

        if (method_exists($envio, 'getLastRequest')) {
            $soapRequest = $envio->getLastRequest();
        }
        if (method_exists($envio, 'getLastResponse')) {
            $soapResponse = $envio->getLastResponse();
        }

        if ($soapRequest) {
            $arquivoReq = $this->salvarArquivo('03_request_soap.xml', $this->dir('Respostas'), (string)$soapRequest);
            $this->log("PASSO 5 - SOAP REQUEST salvo\nArquivo: {$arquivoReq}");
        }

        if ($soapResponse) {
            $arquivoRes = $this->salvarArquivo('03_response_soap.xml', $this->dir('Respostas'), (string)$soapResponse);
            $this->log("PASSO 6 - SOAP RESPONSE salvo\nArquivo: {$arquivoRes}");

            // ✅ retorna o SOAP Response XML (string) — mais útil e coerente com assinatura do método
            return (string)$soapResponse;
        }

        // fallback: se não tiver lastResponse, devolve JSON do objeto
        return $this->normalizeConteudo($retObj);
    }


  private function log(string $mensagem): void
    {
        echo "<pre>{$mensagem}</pre>";
    }
    /* =========================================================
     * BUILDERS (AJUSTADO PARA O FORMATO DO Natal)
     * ========================================================= */

    private function buildDadosLote($nfse, $prestador, $tomador, $servicos): array
    {
        $cnpjPrestador = $this->onlyDigits((string)$prestador->cnpj);
        if (strlen($cnpjPrestador) !== 14) {
            throw new \Exception("CNPJ do prestador inválido: {$cnpjPrestador}");
        }

        $inscMunPrestador = trim((string)$prestador->inscricao_municipal);
        if ($inscMunPrestador === '') {
            throw new \Exception("Inscrição municipal do prestador não informada.");
        }

        // Agrega serviços do banco em 1 serviço ABRASF
        $serv = $this->buildServicoAgregado($nfse, $prestador, $servicos);

        // Tomador (formato que Natal espera)
        $tom = $this->buildTomador($tomador);

        // Identificação do RPS
        $serie  = (string)($nfse->serie ?? 'A');
        $numero = (int)($nfse->numero ?? $nfse->numero_rps ?? 1);

        // RPS Id (até 20 chars) - usado em InfRps/@Id
        $infId = $this->gerarNumeroRps($serie, $numero, $cnpjPrestador);

        // Campos obrigatórios do validator do Natal
        $naturezaOperacao      = (int)($nfse->natureza_operacao ?? 1); // ajuste conforme prefeitura
        $optanteSimplesNacional = (int)($prestador->optanteSimplesNacional ?? 0);
        $incentivadorCultural   = (int)($nfse->incentivadorCultural ?? 2); // 1=sim,2=nao (geralmente 2)
        $status                 = (int)($nfse->status ?? 1); // 1=normal (geralmente)

        // Campos que o XML do Natal escreve (evite vazio)
        $codigoTributacaoMunicipio = (string)($nfse->codigo_tributacao_municipio ?? $nfse->codigoTributacaoMunicipio ?? '1406');
        $codigoMunicipio           = (string)($prestador->codigoMunicipio ?? $serv['codigoMunicipio'] ?? '2925303'); // ajuste Natal/IBGE correto
 
        // Monta o RPS no formato EXATO que Natal::gerarXmlLoteRps usa
        $rpsItem = [
            'inf_id' => $infId,

            'infRps' => [
                'numero'      => $numero,
                'serie'       => $serie,
                'tipo'        => 1,
                'dataEmissao' => (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))
                    ->format('Y-m-d\TH:i:sP'),
            ],

            // obrigatórios no validator
            'naturezaOperacao'       => $naturezaOperacao,
            'optanteSimplesNacional' => $optanteSimplesNacional,
            'incentivadorCultural'   => $incentivadorCultural,
            'status'                 => $status,
            'valorServicos'          => (float)$serv['valorServicos'],
            'ValorDeducoes'          => 0.00,
                      // usados no XML (Serviço)
            'issRetido'                 => 2 ??(int)$serv['issRetido'],
            'valorIss'                  => (float)$serv['valorIss'],
            'baseCalculo' => (float)$serv['valorServicos'],
            'aliquota'                  =>0.05?? (float)$serv['aliquota'],
            'itemListaServico'          => (string)$serv['itemListaServico'],
        
            'codigoTributacaoMunicipio' => '2408102' ?? (string)$serv['itemListaServico'],
            'discriminacao'             => (string)$serv['discriminacao'],
            'codigoMunicipio'           => '2408102' ?? $codigoMunicipio,
       
            // opcionais (se quiser já alimentar)
         
                      'tomador'                => $tom,
            // 'valorLiquidoNfse' => (float)$serv['valorServicos'],
        ];
<EnviarLoteRpsEnvio xmlns="http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd">
        $loteN = date('ymdHis') . random_int(100, 999);

        return [
            'lote_id'            =>   $loteN,
            'numeroLote'         => $loteN,
            'cnpjPrestador'      => $cnpjPrestador,
             'NomeFantasia'       =>$prestador->nome_fantasia , // <-- INCLUA ESTE CAMPO
           'RazaoSocial'        => $prestador->razao_social  ?? '',  // <-- INCLUA ESTE CAMPO
            'inscricaoMunicipal' => $inscMunPrestador,
            'quantidadeRps'      => 1,
            'rps'                => [$rpsItem],
        ];
    } 
    private function buildServicoAgregado($nfse, $prestador, $servicos): array
    {
        $totalServ   = 0.0;
        $totalIss    = 0.0;

        // IMPORTANTÍSSIMO: sua lógica anterior nunca mudava a aliquota porque ela inicia em 2 e você checa "=== null"
        $aliquota  = null;

        $issRetido = 2; // 1=retido, 2=não retido
        $discLinhas = [];

        $codigoMunicipio  = (string)($prestador->codigoMunicipio ?? '2925303'); // ajuste
        $exigibilidadeISS = 1;

        $itemListaServico = trim((string)($nfse->itemListaServico ?? $nfse->item_lista_servico ?? ''));
        if ($itemListaServico === '') {
            throw new \Exception("itemListaServico não informado no RPS {$nfse->id}");
        }

        $i = 0;
        foreach ($servicos as $s) {
            $i++;

            $vServ = (float)($s->valor_servicos ?? 0);
            $vIss  = (float)($s->valor_iss ?? 0);
            $pAliq = (float)($s->aliquota ?? 0);

            $totalServ += $vServ;
            $totalIss  += $vIss;

            if ($aliquota === null && $pAliq > 0) {
                $aliquota = $pAliq;
            }

            // se tiver algum item retido, você pode forçar issRetido=1 (opcional)
            if ((int)($s->iss_retido ?? 2) === 1) {
                $issRetido = 1;
            }

            $disc = (string)($s->discriminacao ?? '');
            $disc = $this->removerAcentos($disc);
            $disc = preg_replace('/[\r\n]+/', ' ', $disc);
            $disc = trim($disc);

            if ($disc !== '') {
                $discLinhas[] = "{$i}- {$disc}";
            }
        }

 
            $discriminacao = 'SERVICOS PRESTADOS.';
       
        if ($aliquota === null) {
            $aliquota = 0.0;
        }

        return [
            'valorServicos'    => (float)$totalServ,
            'valorIss'         => (float)$totalIss,
            'aliquota'         => (float)$aliquota,
            'issRetido'        => (int)$issRetido,
            'itemListaServico' => $itemListaServico,
            'discriminacao'    => $discriminacao,
            'codigoMunicipio'  => (string)$codigoMunicipio,
            'exigibilidadeISS' => (string)$exigibilidadeISS,
        ];
    }

    private function buildTomador($tomador): array
    {
        $doc = $this->onlyDigits((string)($tomador->cnpj ?? $tomador->cpf_cnpj ?? ''));
        if (!(strlen($doc) === 11 || strlen($doc) === 14)) {
            throw new \Exception("CPF/CNPJ do tomador inválido: {$doc}");
        }

        $razao = trim((string)($tomador->razao_social ?? $tomador->nome ?? 'TOMADOR'));
        if ($razao === '') $razao = 'TOMADOR';

        $end = [
            'logradouro'      => (string)($tomador->logradouro ?? ''),
            'numero'          => (string)($tomador->numero ?? ''),
            'bairro'          => (string)($tomador->bairro ?? ''),
            'codigoMunicipio' => (string)($tomador->codigoMunicipio ?? $tomador->codigo_municipio ?? ''),
            'uf'              => (string)($tomador->uf ?? ''),
            'cep'             => (string)($tomador->cep ?? ''),
        ];

        foreach (['logradouro', 'numero', 'bairro', 'codigoMunicipio', 'uf', 'cep'] as $k) {
            if (trim((string)$end[$k]) === '') {
                throw new \Exception("Tomador.endereco.{$k} obrigatório e não informado.");
            }
        }

        return [
            'cpfCnpj'     => $doc,
            'razaoSocial' => $razao,
            'endereco'    => $end,
            'telefone'    => (string)($tomador->fone ?? $tomador->telefone ?? ''),
            'email'       => (string)($tomador->email ?? ''),
        ];
    }

    private function onlyDigits(string $v): string
    {
        return preg_replace('/\D+/', '', $v) ?? '';
    }

    private function removerAcentos($texto): string
    {
        $texto = mb_convert_encoding((string)$texto, 'UTF-8', 'UTF-8');

        $mapa = [
            'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a',
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
            'ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
            'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
            'ç'=>'c',
            'Á'=>'A','À'=>'A','Ã'=>'A','Â'=>'A','Ä'=>'A',
            'É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E',
            'Í'=>'I','Ì'=>'I','Î'=>'I','Ï'=>'I',
            'Ó'=>'O','Ò'=>'O','Õ'=>'O','Ô'=>'O','Ö'=>'O',
            'Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U',
            'Ç'=>'C',
        ];

        return strtr($texto, $mapa);
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
        if ($conteudo === null) {
            return '';
        }

        if (is_string($conteudo)) {
            return $conteudo;
        }

        if (is_scalar($conteudo)) {
            return (string) $conteudo;
        }

        // array / object
        return json_encode($conteudo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
    }
    private function gerarNumeroRps(string $serie, int $numero, string $cnpj): string
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        $cnpjPrefixo = substr($cnpj, 0, 3);
        $timestamp = date('ymdHis');
        $aleatorio = random_int(10, 99);

        $rps = $serie
            . str_pad($numero, 3, '0', STR_PAD_LEFT)
            . $cnpjPrefixo
            . $timestamp
            . $aleatorio;

        return substr($rps, 0, 20);
    }

    
    private function dir(string $sub): string
    {
        return rtrim($this->baseDir, '/\\') . '/' . trim($sub, '/\\');
    }
}


    /**
     * Valida o XML gerado contra o schema ABRASF 2.04 e retorna todos os erros encontrados.
     */
    private function validarXmlAbrasf(string $xml, string $xsdPath): array
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->loadXML($xml);

        $erros = [];
        if (!$dom->schemaValidate($xsdPath)) {
            foreach (libxml_get_errors() as $error) {
                $erros[] = [
                    'linha' => $error->line,
                    'mensagem' => trim($error->message),
                    'nivel' => $error->level,
                    'codigo' => $error->code,
                ];
            }
            libxml_clear_errors();
        }
        return $erros;
    }

    /**
     * Gera, assina e valida o XML, mostrando todos os erros e incompatibilidades.
     */
    public function gerarAssinarValidarXml(array $dados, string $xsdPath)
    {
        // Gera o XML normalmente
        $xml = $this->gerarXmlLoteRps($dados);

        // Valida contra o schema ABRASF 2.04
        $erros = $this->validarXmlAbrasf($xml, $xsdPath);

        if (!empty($erros)) {
            // Exibe todos os erros encontrados
            foreach ($erros as $erro) {
                echo "Erro na linha {$erro['linha']}: {$erro['mensagem']} (Nível: {$erro['nivel']}, Código: {$erro['codigo']})\n";
            }
            throw new \Exception("XML inválido ou incompatível com o schema ABRASF 2.04.");
        }

        // Se não houver erros, prossegue com assinatura e envio
        return $this->assinarXml($xml);
    }