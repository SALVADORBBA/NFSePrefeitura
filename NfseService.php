<?php

use NFSePrefeitura\NFSe\NFSeSigner;
use NFSePrefeitura\NFSe\PortoSeguro;
use NFSePrefeitura\NFSe\NFSeSender;
use NFePHP\Common\Certificate;

class NfseService
{
    private $wsdl;
    private $certPath;
    private $certPassword;
    private $client;

    public function __construct($wsdl = null, $certPath = null, $certPassword = null)
    {
        // Usa sempre o caminho relativo, conforme confirmado pelo usuário
        $wsdlPath = $wsdl ?: 'app/ws/nfse.wsdl';
        if (!file_exists($wsdlPath)) {
            throw new \Exception('Arquivo WSDL não encontrado: ' . $wsdlPath);
        }
        $this->wsdl = $wsdlPath;
        $this->certPath = $certPath;
        $this->certPassword = $certPassword;

        $options = [
            'trace' => 1,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'soap_version' => SOAP_1_1,
            'encoding' => 'UTF-8',
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ])
        ];
        $this->client = new \SoapClient($this->wsdl, $options);
    }

    /**
     * Assina o XML conforme ABRASF
     */
    public function assinarXml($xml, $certPath = null, $certPassword = null, $tag = 'InfDeclaracaoPrestacaoServico')
    {
        if (empty($xml)) {
            throw new \InvalidArgumentException('O XML passado para assinatura está vazio.');
        }
        $certPath = $certPath ?: $this->certPath;
        $certPassword = $certPassword ?: $this->certPassword;
        if (!file_exists($certPath)) {
            throw new \InvalidArgumentException('Certificado não encontrado: ' . $certPath);
        }
        $certificate = Certificate::readPfx(file_get_contents($certPath), $certPassword);
        $xml = \NFePHP\Common\Strings::clearXmlString($xml);
        $algorithm = OPENSSL_ALGO_SHA1;
        $canonical = [false, false, null, null];
        $signedXml = \NFePHP\Common\Signer::sign(
            $certificate,
            $xml,
            $tag,
            'Id',
            $algorithm,
            $canonical
        );
        return $signedXml;
    }

    /**
     * Envia o XML assinado para o método desejado do WebService
     * @param string $xmlAssinado XML assinado conforme ABRASF
     * @param string $metodo Nome do método (ex: RecepcionarLoteRps, GerarNfse)
     * @return mixed Resposta do WebService
     */
    public function enviar($xmlAssinado, $metodo)
    {
        // $params = ['xml' => $xmlAssinado];
        // return $this->client->__soapCall($metodo, [$params]);



   $cabecalho = '<cabecalho xmlns="http://www.abrasf.org.br/nfse.xsd" versao="2.04"><versaoDados>2.02</versaoDados></cabecalho>';
    $params = [
        'nfseCabecMsg' => $cabecalho,
        'nfseDadosMsg' => $xmlAssinado
    ];
    return $this->client->__soapCall($metodo, [$params]);



    }

    /**
     * Processa o envio de um lote de RPS
     */
    public function processar($key)
    {


            $nfse = NfseRps::find($key);
            $prestador= $nfse->nfse_emitente;
            $tomador= $nfse->tomador;
            $servicos = NfseRpsServico::where('nfse_rps_id', '=',$nfse->id)->get();
            $certPath =  $prestador->cert_pfx_blob;
            $certPassword =  $prestador->cert_senha_plain;
            $wsdl = $this->wsdl;



$rpsArray = [];

$rpsArray[] = [
    'numero' => $nfse->id,
    'serie'  => 'A',
    'tipo'   => 1,
    'data'   => date('Y-m-d'),
];

if (empty($rpsArray)) {
    throw new Exception('Lote NFSe deve conter ao menos um RPS.');
}

$quantidadeRps = count($rpsArray);
 
 $servicosArray = [];

$servicosArray = [];

foreach ($servicos as $servico) {
 
    // Remove acentos + normaliza texto
    $discriminacao =  $this->removerAcentos($servico->discriminacao);

    // Remove quebras excessivas e caracteres de controle
    $discriminacao = preg_replace('/[\r\n]+/', ' ', $discriminacao);
    $discriminacao = trim($discriminacao);

    $servicosArray[] = [
        'valorServicos' => (float) $servico->valor_servicos,
        'valorIss'      => (float) $servico->valor_iss,
        'aliquota'      => (float) $servico->aliquota,

        // ABRASF: somente 0 ou 1
    'issRetido' => ((int) $servico->iss_retido === 1) ? 1 : 2,

      'itemListaServico' => $nfse->itemListaServico,
        'codigoCnae'       => $prestador->codigocnae,

        'discriminacao' => $discriminacao,

        'codigoMunicipio'  => $servico->codigo_municipio,
        'exigibilidadeISS' => $servico->exigibilidade_iss,
    ];
}

 $dados = [
    'lote_id' => 'Lote' . $nfse->id,

    // ===== LOTE =====
    'numeroLote'         => (int) $nfse->id,
    'cnpjPrestador'      => preg_replace('/\D/', '', $prestador->cnpj),
    'inscricaoMunicipal' => $prestador->inscricao_municipal,
    'quantidadeRps'      => $quantidadeRps,

    // ===== RPS =====
    'rps' => [
        [
            'inf_id' => 'RPS' . $nfse->id,

            'infRps' => [
                'numero'      => $nfse->id,
                'serie'       => 'A',
                'tipo'        => 1,
                'dataEmissao' => date('Y-m-d\TH:i:s'),
            ],

            'competencia' => date('Y-m-01'),

            // 🔥 SERVIÇOS REAIS DO BANCO
            'servicos' => $servicosArray,

            // ===== REGIME =====
            'regimeEspecialTributacao' =>$prestador->regimeEspecialTributacao,
            'optanteSimplesNacional'   =>$prestador->optanteSimplesNacional,
            'incentivoFiscal'          =>$prestador->incentivoFiscal,

            // ===== TOMADOR =====
            'tomador' => [
                'cpfCnpj'    => $tomador->cnpj,
                'razaoSocial'=> 'TOMADOR',

                'endereco' => [
                    'logradouro'     =>  $tomador->logradouro,
                    'numero'          =>  $tomador->numero,
                    'bairro'          =>  $tomador->bairro,
                    'codigoMunicipio' =>  $tomador->codigoMunicipio,
                    'uf'              =>  $tomador->uf,
                    'cep'             =>  $tomador->cep,
                ],

                'telefone' => $tomador->fone,
                'email'    => $tomador->email,
            ],
        ],
    ],
];


$portoSeguro = new PortoSeguro($certPath, $certPassword);
$xml = $portoSeguro->gerarXmlLoteRps($dados);

// O $xml agora contém o XML completo, sem as tags de assinatura.
        self::salvar("02_inicial.xml", $xml);

        // 2. Assinar o XML usando NFSeSigner
        $xmlLoteAssinado = NFSeSigner::sign(
            $xml,
            $certPath,
            $certPassword,
            "InfDeclaracaoPrestacaoServico"
        );
        self::salvar("02_xmlLoteAssinado.xml", $xmlLoteAssinado);


        // 3. Enviar o XML assinado
      $resposta = $this->enviar($xmlLoteAssinado, 'RecepcionarLoteRps');
 
            self::salvar("03_resposta.xml",$resposta->outputXML);

         dd('XML assinado');
 exit;


            return    $resposta;
    }

    private static function salvar(string $nome, string $conteudo): void
    {
        $dir =   "app/xml_nfse/";
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir . date("Ymd_His_") . $nome, $conteudo);
    }

    /**
     * Envia o XML assinado para o método desejado do WebService usando cURL
     * @param string $endpoint URL do serviço (ex: https://portoseguroba.gestaoiss.com.br/ws/nfse.asmx)
     * @param string $soapAction Nome do método SOAP (ex: RecepcionarLoteRps)
     * @param string $xmlAssinado Envelope SOAP completo
     * @return string Resposta do WebService
     */
    public static function enviarViaCurl($endpoint, $soapAction, $xmlAssinado)
    {
        $headers = [
            "Content-Type: text/xml; charset=utf-8",
            "SOAPAction: \"$soapAction\""
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlAssinado);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Para testes
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("Erro ao enviar SOAP: $error");
        }

        return $response;
    }

public static function gerarXmlLoteRps(array $dados): string
    {
        // Chama o método estático da classe PortoSeguro
        return PortoSeguro::gerarXmlLoteRps($dados);
    }


    private  function removerAcentos($texto)
{
    $texto = mb_convert_encoding($texto, 'UTF-8', 'UTF-8');

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