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
    public function processar()
    {
        $certPath =  'app/certificado/1/6968c3b3791e6_695fa57fadf87_quimica.pfx';
        $certPassword =  'baby7902';
        $wsdl = $this->wsdl;

       $dados = [
    'lote_id' => 'Lote532526430004311001133',
    'numeroLote' => '133',
    'cnpjPrestador' => '12345678912345',
    'inscricaoMunicipal' => '1234',
    'quantidadeRps' => 1,
    'rps' => [
        [
            'inf_id' => 'Rps13331',
            'infRps' => [
                'numero' => '133',
                'serie' => '3',
                'tipo' => '1',
                'dataEmissao' => '0001-01-01',
            ],
            'competencia' => '2014-03-17',
            'valorServicos' => 400.00,
            'valorIss' => 8.00,
            'aliquota' => 0.0200,
            'issRetido' => 1,
            'itemListaServico' => '1401',
            'discriminacao' => 'Discriminacao do servico',
            'codigoMunicipio' => '3100401',
            'exigibilidadeISS' => '3',
            'regimeEspecialTributacao' => '1',
            'optanteSimplesNacional' => '2',
            'incentivoFiscal' => '2',
            'tomador' => [
                'cpfCnpj' => '12345678912345',
                'inscricaoMunicipal' => '1234',
                'razaoSocial' => 'TOMADOR',
                'endereco' => [
                    'logradouro' => 'TESTE',
                    'numero' => '441',
                    'bairro' => 'TESTE',
                    'codigoMunicipio' => '3100401',
                    'uf' => 'MG',
                    'cep' => '35438000',
                ],
                'telefone' => '3433333333',
                'email' => 'teste@dominiodoemail.com',
            ],
        ],
    ],
];

$portoSeguro = new PortoSeguro($certPath, $certPassword);
$xml = $portoSeguro->gerarXmlLoteRps($dados);

// O $xml agora contém o XML completo, sem as tags de assinatura.
        self::salvar("02_inicial.xml", $xml);
        dd('XML Inicial');
 exit;
        // 2. Assinar o XML usando NFSeSigner
        $xmlLoteAssinado = NFSeSigner::sign(
            $xml,
            $certPath,
            $certPass,
            "InfDeclaracaoPrestacaoServico"
        );
        self::salvar("02_xmlLoteAssinado.xml", $xmlLoteAssinado);

 

        // 3. Enviar o XML assinado
      $resposta = $this->enviar($xmlLoteAssinado, 'RecepcionarLoteRps');
 
            self::salvar("03_resposta.xml",$resposta->outputXML);



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
    // Gera o XML conforme padrão ABRASF, incluindo namespaces corretos
    $xml  = '<?xml version="1.0" encoding="UTF-8"?>';
    $xml .= '<EnviarLoteRpsEnvio xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="http://www.abrasf.org.br/nfse.xsd">';
    $xml .= '<LoteRps Id="' . $dados['lote_id'] . '" versao="2.02">';
    $xml .= '<NumeroLote>' . $dados['numeroLote'] . '</NumeroLote>';
    $xml .= '<CpfCnpj><Cnpj>' . $dados['cnpjPrestador'] . '</Cnpj></CpfCnpj>';
    $xml .= '<InscricaoMunicipal>' . $dados['inscricaoMunicipal'] . '</InscricaoMunicipal>';
    $xml .= '<QuantidadeRps>' . $dados['quantidadeRps'] . '</QuantidadeRps>';
    $xml .= '<ListaRps>';

    foreach ($dados['rps'] as $rps) {
        $dataEmissao = substr($rps['infRps']['dataEmissao'], 0, 10);
        $xml .= '<Rps>';
        $xml .= '<InfDeclaracaoPrestacaoServico Id="' . $rps['inf_id'] . '">';
        $xml .= '<Rps>';
        $xml .= '<IdentificacaoRps>';
        $xml .= '<Numero>' . $rps['infRps']['numero'] . '</Numero>';
        $xml .= '<Serie>' . $rps['infRps']['serie'] . '</Serie>';
        $xml .= '<Tipo>' . $rps['infRps']['tipo'] . '</Tipo>';
        $xml .= '</IdentificacaoRps>';
        $xml .= '<DataEmissao>' . $dataEmissao . '</DataEmissao>';
        $xml .= '<Status>1</Status>';
        $xml .= '</Rps>';
        $xml .= '<Competencia>' . $rps['competencia'] . '</Competencia>';
        $xml .= '<Servico>';
        $xml .= '<Valores>';
        $xml .= '<ValorServicos>' . number_format($rps['valorServicos'], 2, '.', '') . '</ValorServicos>';
        $xml .= '<ValorIss>' . number_format($rps['valorIss'], 2, '.', '') . '</ValorIss>';
        $xml .= '<Aliquota>' . number_format($rps['aliquota'], 4, '.', '') . '</Aliquota>';
        $xml .= '</Valores>';
        $xml .= '<IssRetido>' . $rps['issRetido'] . '</IssRetido>';
        $xml .= '<ItemListaServico>' . $rps['itemListaServico'] . '</ItemListaServico>';
        $xml .= '<Discriminacao>' . htmlspecialchars($rps['discriminacao'], ENT_XML1) . '</Discriminacao>';
        $xml .= '<CodigoMunicipio>' . $rps['codigoMunicipio'] . '</CodigoMunicipio>';
        $xml .= '<ExigibilidadeISS>' . $rps['exigibilidadeISS'] . '</ExigibilidadeISS>';
        $xml .= '</Servico>';
        $xml .= '<Prestador>';
        $xml .= '<CpfCnpj><Cnpj>' . $dados['cnpjPrestador'] . '</Cnpj></CpfCnpj>';
        $xml .= '<InscricaoMunicipal>' . $dados['inscricaoMunicipal'] . '</InscricaoMunicipal>';
        $xml .= '</Prestador>';
        $xml .= '<Tomador>';
        $xml .= '<IdentificacaoTomador>';
        $xml .= '<CpfCnpj>';
        $xml .= '<Cnpj>' . $rps['tomador']['cpfCnpj'] . '</Cnpj>';
        $xml .= '</CpfCnpj>';
        $xml .= '<InscricaoMunicipal>' . $rps['tomador']['inscricaoMunicipal'] . '</InscricaoMunicipal>';
        $xml .= '</IdentificacaoTomador>';
        $xml .= '<RazaoSocial>' . htmlspecialchars($rps['tomador']['razaoSocial'], ENT_XML1) . '</RazaoSocial>';
        $xml .= '<Endereco>';
        $xml .= '<Endereco>' . htmlspecialchars($rps['tomador']['endereco']['logradouro'], ENT_XML1) . '</Endereco>';
        $xml .= '<Numero>' . $rps['tomador']['endereco']['numero'] . '</Numero>';
        $xml .= '<Bairro>' . htmlspecialchars($rps['tomador']['endereco']['bairro'], ENT_XML1) . '</Bairro>';
        $xml .= '<CodigoMunicipio>' . $rps['tomador']['endereco']['codigoMunicipio'] . '</CodigoMunicipio>';
        $xml .= '<Uf>' . $rps['tomador']['endereco']['uf'] . '</Uf>';
        $xml .= '<Cep>' . $rps['tomador']['endereco']['cep'] . '</Cep>';
        $xml .= '</Endereco>';
        $xml .= '<Contato>';
        $xml .= '<Telefone>' . $rps['tomador']['telefone'] . '</Telefone>';
        $xml .= '<Email>' . htmlspecialchars($rps['tomador']['email'], ENT_XML1) . '</Email>';
        $xml .= '</Contato>';
        $xml .= '</Tomador>';
        $xml .= '<RegimeEspecialTributacao>' . $rps['regimeEspecialTributacao'] . '</RegimeEspecialTributacao>';
        $xml .= '<OptanteSimplesNacional>' . $rps['optanteSimplesNacional'] . '</OptanteSimplesNacional>';
        $xml .= '<IncentivoFiscal>' . $rps['incentivoFiscal'] . '</IncentivoFiscal>';
        $xml .= '</InfDeclaracaoPrestacaoServico>';
        $xml .= '</Rps>';
    }

    $xml .= '</ListaRps>';
    $xml .= '</LoteRps>';
    $xml .= '</EnviarLoteRpsEnvio>';

    return $xml;
}
}