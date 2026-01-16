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
       // $wsdlPath = $wsdl ?: 'app/ws/nfse.wsdl';
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
        $params = ['xml' => $xmlAssinado];
        return $this->client->__soapCall($metodo, [$params]);
    }

    /**
     * Processa o envio de um lote de RPS
     */
    public function processar()
    {
        $certPath = $this->certPath ?: 'app/certificado/1/6968c3b3791e6_695fa57fadf87_quimica.pfx';
        $certPass = $this->certPassword ?: 'baby7902';
        $wsdl = $this->wsdl;

        $dados = [
            "lote_id" => "Lote1",
            "numeroLote" => "12345",
            "cnpjPrestador" => "12345678000199",
            "inscricaoMunicipal" => "123456",
            "quantidadeRps" => 1,
            "rps" => [
                [
                    "inf_id" => "Rps1",
                    "infRps" => [
                        "numero" => "1",
                        "serie" => "A",
                        "tipo" => "1",
                        "dataEmissao" => "2024-06-01T10:00:00",
                    ],
                    "competencia" => "2024-06-01",
                    "valorServicos" => "100.00",
                    "valorIss" => "5.00",
                    "aliquota" => "0.05",
                    "issRetido" => "2",
                    "itemListaServico" => "1401",
                    "discriminacao" => "Serviço de exemplo",
                    "codigoMunicipio" => "1234567",
                    "exigibilidadeISS" => "1",
                    "regimeEspecialTributacao" => "1",
                    "optanteSimplesNacional" => "1",
                    "incentivoFiscal" => "2",
                    "tomador" => [
                        "cpfCnpj" => "98765432000188",
                        "inscricaoMunicipal" => "654321",
                        "razaoSocial" => "Empresa Tomadora",
                        "endereco" => [
                            "logradouro" => "Rua Exemplo",
                            "numero" => "100",
                            "bairro" => "Centro",
                            "codigoMunicipio" => "1234567",
                            "uf" => "SP",
                            "cep" => "12345000",
                        ],
                        "telefone" => "11999999999",
                        "email" => "contato@empresa.com",
                    ],
                ],
            ],
        ];

        // 1. Gerar o XML do lote RPS
        $xmlLote = PortoSeguro::gerarXmlLoteRps($dados);
        self::salvar("01_xmlLote.xml", $xmlLote);

        // 2. Assinar o XML usando NFSeSigner
        $xmlLoteAssinado = NFSeSigner::sign(
            $xmlLote,
            $certPath,
            $certPass,
            "InfDeclaracaoPrestacaoServico"
        );
        self::salvar("02_xmlLoteAssinado.xml", $xmlLoteAssinado);

        // 3. Enviar o XML assinado
        $resposta = $this->enviar($xmlLoteAssinado, 'RecepcionarLoteRps');
        if ($resposta) {
            self::salvar("03_resposta.xml", $resposta);
        } else {
            var_dump($resposta);
        }
    }

    private static function salvar(string $nome, string $conteudo): void
    {
        $dir = __DIR__ . "/app/xml_nfse/";
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
}