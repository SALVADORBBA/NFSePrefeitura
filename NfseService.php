<?php

use NFSePrefeitura\NFSe\NFSeSigner;
use NFSePrefeitura\NFSe\PortoSeguro;
use NFePHP\Common\Certificate;

class NfseService
{
    private string $wsdl;
    private ?string $certPath;
    private ?string $certPassword;
    private \SoapClient $client;

    public function __construct(?string $wsdl = null, ?string $certPath = null, ?string $certPassword = null)
    {
        $wsdlPath = $wsdl ?: 'app/ws/nfse.wsdl';
        if (!file_exists($wsdlPath)) {
            throw new \Exception('Arquivo WSDL não encontrado: ' . $wsdlPath);
        }

        $this->wsdl         = $wsdlPath;
        $this->certPath     = $certPath;
        $this->certPassword = $certPassword;

        $options = [
            'trace'        => 1,
            'exceptions'   => true,
            'cache_wsdl'   => WSDL_CACHE_NONE,
            'soap_version' => SOAP_1_1,
            'encoding'     => 'UTF-8',
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true
                ]
            ])
        ];

        $this->client = new \SoapClient($this->wsdl, $options);
    }

    /* =========================================================
     * ASSINATURA (ABRASF)
     * ========================================================= */
    public function assinarXml(string $xml, ?string $certPath = null, ?string $certPassword = null, string $tag = 'InfDeclaracaoPrestacaoServico'): string
    {
        if (trim($xml) === '') {
            throw new \InvalidArgumentException('O XML passado para assinatura está vazio.');
        }

        $certPath = $certPath ?: $this->certPath;
        $certPassword = $certPassword ?: $this->certPassword;

        if (!$certPath || !file_exists($certPath)) {
            throw new \InvalidArgumentException('Certificado não encontrado: ' . (string)$certPath);
        }
        if ($certPassword === null) {
            throw new \InvalidArgumentException('Senha do certificado não informada.');
        }

        $xmlAssinado = NFSeNacionalSigner::assinarDpsXml(
            $xml,
            (string)$certPath,
            (string)$certPassword
        );

    }

    /* =========================================================
     * ENVIO SOAP (nfseCabecMsg + nfseDadosMsg)
     * ========================================================= */
    public function enviar(string $xmlAssinado, string $metodo, string $versao = '2.02')
    {
        $cabecalho = '<cabecalho xmlns="http://www.abrasf.org.br/nfse.xsd" versao="'.$this->xmlEscape($versao).'">'
                   . '<versaoDados>'.$this->xmlEscape($versao).'</versaoDados>'
                   . '</cabecalho>';

        $params = [
            'nfseCabecMsg' => $cabecalho,
            'nfseDadosMsg' => $xmlAssinado
        ];

        return $this->client->__soapCall($metodo, [$params]);
    }

    /* =========================================================
     * PROCESSAR (monta lote -> assina -> envia)
     * ========================================================= */
    public function processar($key)
    {
        $nfse      = NfseRps::find($key);
        if (!$nfse) {
            throw new \Exception("NFSe RPS não encontrado. Key: {$key}");
        }

      $prestador = $nfse->nfse_emitente;
        if (!$prestador) {
            throw new \Exception("Emitente/Prestador não encontrado para o RPS {$key}");
        }

        $tomador   = $nfse->tomador;
        if (!$tomador) {
            throw new \Exception("Tomador não encontrado para o RPS {$key}");
        }

        $servicos  = NfseRpsServico::where('nfse_rps_id', '=', $nfse->id)->get();
        if (!$servicos || count($servicos) < 1) {
            throw new \Exception("RPS {$nfse->id} não possui serviços.");
        }

        // Cert (se você usa blob no banco, aqui precisa adaptar para path real)
        $certPath     = $prestador->cert_pfx_blob;      // ATENÇÃO: se isso não for path, troque!
        $certPassword = $prestador->cert_senha_plain;

        // 1) Monta dados no formato que o builder PortoSeguro espera (SEM lista 'servicos')
        $dados = $this->buildDadosLote($nfse, $prestador, $tomador, $servicos);

        // 2) Gera XML do lote (sem assinatura)
        $portoSeguro = new PortoSeguro((string)$certPath, (string)$certPassword);
        $xml = $portoSeguro->gerarXmlLoteRps($dados);
        self::salvar("01_inicial.xml", $xml);

        // 3) Assina no nível InfDeclaracaoPrestacaoServico
        // $xmlAssinado = NFSeSigner::sign(
        //     $xml,
        //     (string)$certPath,
        //     (string)$certPassword,
        //     "InfDeclaracaoPrestacaoServico"
        // );
    $signer = new \NotasFiscais\Abrasf\PortoSeguroSigner($certPath, $certPassword);
    $xmlAssinado = $signer->signRps($xml);

 
        self::salvar("02_assinado.xml", $xmlAssinado);

        // 4) Envia (normalmente RecepcionarLoteRps ou RecepcionarLoteRpsSincrono)
        $resposta = $this->enviar($xmlAssinado, 'RecepcionarLoteRps');
        if (isset($resposta->outputXML)) {
            self::salvar("03_resposta.xml", $resposta->outputXML);
        } else {
            self::salvar("03_resposta_dump.txt", print_r($resposta, true));
        }

        return $resposta;
    }

    /* =========================================================
     * BUILDERS (o ponto principal da correção)
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

        // ABRASF: 1 RPS -> 1 Servico
        // então agregamos os itens do banco
        $serv = $this->buildServicoAgregado($nfse, $prestador, $servicos);

        $tom = $this->buildTomador($tomador);

        $rps = [
            'inf_id' => 'RPS' . (int)$nfse->id,

            'infRps' => [
                'numero'      => (int)$nfse->id,
                'serie'       => 'A',
                'tipo'        => 1,
           'dataEmissao' => (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d\TH:i:sP'),



            ],

            'competencia' => date('Ym01'),

            // ====== CAMPOS DO SERVIÇO (NO NÍVEL DO RPS) ======
            'valorServicos'    => $serv['valorServicos'],
            'valorIss'         => $serv['valorIss'],
            'aliquota'         => $serv['aliquota'],
            'issRetido'        => $serv['issRetido'],
            'itemListaServico' => $serv['itemListaServico'],
            'discriminacao'    => $serv['discriminacao'],
            'codigoMunicipio'  => $serv['codigoMunicipio'],
            'exigibilidadeISS' => $serv['exigibilidadeISS'],

            // ===== REGIME =====
                'regimeEspecialTributacao' =>  $prestador->regimeEspecialTributacao ?? 0,
                'optanteSimplesNacional'   =>  $prestador->optanteSimplesNacional?? 0 ,
                'incentivoFiscal'          =>  $prestador->incentivoFiscal ??  0,
                'codigoCnae' => $prestador->codigocnae ?? null,
                'codigoTributacaoMunicipio' => $nfse->codigo_tributacao_municipio ?? null, // ou de onde vier
                'municipioIncidencia' =>$prestador->codigoMunicipio, // geralmen

            // ===== TOMADOR =====
            'tomador' => $tom,
        ];

        return [
            'lote_id'           => 'Lote' . (int)$nfse->id,
            'numeroLote'        => (int)$nfse->id,
            'cnpjPrestador'     => $cnpjPrestador,
            'inscricaoMunicipal'=> $inscMunPrestador,
            'quantidadeRps'     => 1,
            'rps'               => [$rps],
        ];
    }

    /**
     * Agrega N itens do banco em 1 "Servico" ABRASF do RPS:
     * - soma valores
     * - concatena discriminação (1-, 2-, 3- ...)
     * - define issRetido (se qualquer item estiver retido, pode forçar 1)
     * - garante obrigatórios não vazios
     */
    private function buildServicoAgregado($nfse, $prestador, $servicos): array
    {
        $totalServ   = 0.0;
        $totalIss    = 0.0;
        $aliquota    = null;
        $issRetido   = 2; // default: não retido
        $discLinhas  = [];

        $codigoMunicipio  = null;
        $exigibilidadeISS = null;

        $itemListaServico = trim((string)$nfse->itemListaServico);
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

            // usa a primeira aliquota não-zero como referência (ou a primeira mesmo)
            if ($aliquota === null) {
                $aliquota = $pAliq;
            }

            // se qualquer item for retido, marca como retido
            if ((int)($s->iss_retido ?? 0) === 1) {
                $issRetido = 1;
            }

            // municipio / exigibilidade: pega do primeiro não vazio
            if (!$codigoMunicipio && !empty($s->codigo_municipio)) {
                $codigoMunicipio = (string)$s->codigo_municipio;
            }
            if (!$exigibilidadeISS && !empty($s->exigibilidade_iss)) {
                $exigibilidadeISS = (string)$s->exigibilidade_iss;
            }

            $disc = (string)($s->discriminacao ?? '');
            $disc = $this->removerAcentos($disc);
            $disc = preg_replace('/[\r\n]+/', ' ', $disc);
            $disc = trim($disc);

            if ($disc !== '') {
                $discLinhas[] = "{$i}- {$disc}";
            }
        }

        if ($codigoMunicipio === null || trim($codigoMunicipio) === '') {
            throw new \Exception("codigoMunicipio obrigatório (IBGE) não informado nos serviços do RPS {$nfse->id}");
        }
        if ($exigibilidadeISS === null || trim($exigibilidadeISS) === '') {
            throw new \Exception("exigibilidadeISS obrigatório não informado nos serviços do RPS {$nfse->id}");
        }

        $discriminacao = trim(implode("\n", $discLinhas));
        if ($discriminacao === '') {
            // fallback: pelo menos uma descrição
            $discriminacao = '1- Servico';
        }

        // Se por algum motivo não veio aliquota, usa 0.0000
        if ($aliquota === null) {
            $aliquota = 0.0;
        }

        // Formata já como o builder espera (numérico ok)
        return [
            'valorServicos'    => (float)$totalServ, // <- AGORA EXISTE NO NÍVEL DO RPS ✅
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

        $cep = $this->onlyDigits((string)($tomador->cep ?? ''));
        if ($cep !== '' && strlen($cep) !== 8) {
            throw new \Exception("CEP do tomador inválido: {$cep}");
        }

        $end = [
            'logradouro'     => (string)$tomador->logradouro,
            'numero'         => (string)$tomador->numero,
            'bairro'         => (string)$tomador->bairro,
            'codigoMunicipio'=> (string)$tomador->codigoMunicipio,
            'uf'             => (string)$tomador->uf,
            'cep'            => (string)$tomador->cep,
        ];

        // valida mínimos do endereço
        foreach (['logradouro','numero','bairro','codigoMunicipio','uf','cep'] as $k) {
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
            // 'inscricaoMunicipal' => (string)($tomador->inscricao_municipal ?? ''), // se existir
        ];
    }

    /* =========================================================
     * UTIL
     * ========================================================= */

    private static function salvar(string $nome, string $conteudo): void
    {
        $dir = "app/xml_nfse/";
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir . date("Ymd_His_") . $nome, $conteudo);
    }

    private function onlyDigits(string $v): string
    {
        return preg_replace('/\D+/', '', $v) ?? '';
    }

    private function xmlEscape(string $v): string
    {
        return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
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
}