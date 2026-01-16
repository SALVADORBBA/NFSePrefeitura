<?php
namespace NFSePrefeitura\NFSe;

/**
 * Classe para integração com o sistema de NFSe de Porto Seguro/BA
 * @version 2.0
 * @package DevelopApi
 */
class PortoSeguro
{
    /** @var string URL do WSDL do serviço */
    private static $wsdl = 'https://portoseguroba.gestaoiss.com.br/ws/nfse.asmx?WSDL';

    /** @var string Caminho do certificado digital */
    private $certPath;

    /** @var string Senha do certificado digital */
    private $certPassword;

    private static function getWsdlWithValidation()
    {
        $context = stream_context_create(['http' => ['ignore_errors' => true]]);
        $wsdlContent = file_get_contents(self::$wsdl, false, $context);

        if ($wsdlContent === false || !simplexml_load_string($wsdlContent)) {
            throw new \Exception("Falha ao validar WSDL: " . ($http_response_header[0] ?? 'Erro desconhecido'));
        }

        return self::$wsdl;
    }
    
    /**
     * Envia lote RPS para emissão de NFSe
     * @param array $dados Dados da NFSe conforme padrão ABRASF
     * @param array $opcoes Opções adicionais para o SOAP client
     * @return \stdClass Resposta do webservice
     * @throws \Exception Em caso de erro na comunicação
     * 
     * @example
     * $dados = [
     *     'numeroLote' => '1',
     *     'cnpjPrestador' => '12345678901234',
     *     'inscricaoMunicipal' => '123456',
     *     'quantidadeRps' => 1,
     *     'rps' => [[...]]
     * ];
     * $response = PortoSeguro::enviarLoteRps($dados);
     */
    public static function generateNfseCabecMsg(array $prestador)
    {
        return [
            'versao' => '1.00',
            'cnpjPrestador' => $prestador['cnpjPrestador'],
            'inscricaoMunicipal' => $prestador['inscricao_municipal']
        ];
    }

    public static function generateNfseDadosMsg(array $dados)
    {
        return [
            'Rps' => $dados['rps'],
            'Prestador' => $dados['prestador'],
            'Tomador' => $dados['tomador'],
            'Servico' => $dados['servico']
        ];
    }

    public function __construct(string $certPath, string $certPassword)
    {
        $this->certPath = $certPath;
        $this->certPassword = $certPassword;
    }

    public function enviarLoteRps(array $dados, array $opcoes = [])
    {
        try {
            $defaultOptions = [
                'soap_version' => SOAP_1_2,
                'exceptions' => true,
                'trace' => 1,
                'stream_context' => stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                        'pfx' => file_get_contents($this->certPath),
                        'passphrase' => $this->certPassword
                    ]
                ])
            ];
            
            $wsdlUrl = self::getWsdlWithValidation();
            $client = new \SoapClient(
                $wsdlUrl,
                array_merge($defaultOptions, $opcoes)
            );
            
            $cabecMsg = self::generateNfseCabecMsg($dados);
            $dadosMsg = self::generateNfseDadosMsg($dados);
            
            $response = $client->__soapCall('GerarNfse', [
                'nfseCabecMsg' => $cabecMsg,
                'nfseDadosMsg' => $dadosMsg
            ]);
            
            return $response;
        } catch (\SoapFault $e) {
            throw new \Exception("Erro SOAP: " . $e->getMessage(), $e->getCode(), $e);
        }
    }
    
    /**
     * Gera XML para envio de lote RPS (ajustado para padrão Porto Seguro)
     * @param array $dados
     * @param string|null $signatureRps XML da assinatura do RPS (opcional)
     * @param string|null $signatureLote XML da assinatura do Lote (opcional)
     * @return string
     */
    public static function gerarXmlLoteRps($dados, $signatureRps = null, $signatureLote = null)
    {
        $xml = '<?xml version="1.0" encoding="utf-8"?>';
        $xml .= '<EnviarLoteRpsEnvio xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="http://www.abrasf.org.br/nfse.xsd">';
        $xml .= '<LoteRps Id="' . htmlspecialchars($dados['lote_id'] ?? 'Lote1', ENT_XML1) . '" versao="2.02">';
        $xml .= '<NumeroLote>' . htmlspecialchars($dados['numeroLote'], ENT_XML1) . '</NumeroLote>';
        $xml .= '<CpfCnpj><Cnpj>' . htmlspecialchars($dados['cnpjPrestador'], ENT_XML1) . '</Cnpj></CpfCnpj>';
        $xml .= '<InscricaoMunicipal>' . htmlspecialchars($dados['inscricaoMunicipal'], ENT_XML1) . '</InscricaoMunicipal>';
        $xml .= '<QuantidadeRps>' . htmlspecialchars($dados['quantidadeRps'], ENT_XML1) . '</QuantidadeRps>';
        $xml .= '<ListaRps>';
        foreach ($dados['rps'] as $rps) {
            $xml .= '<Rps>';
            $xml .= '<InfDeclaracaoPrestacaoServico Id="' . htmlspecialchars($rps['inf_id'] ?? 'Rps1', ENT_XML1) . '">';
            $xml .= '<Rps>';
            $xml .= '<IdentificacaoRps>';
            $xml .= '<Numero>' . htmlspecialchars($rps['infRps']['numero'], ENT_XML1) . '</Numero>';
            $xml .= '<Serie>' . htmlspecialchars($rps['infRps']['serie'], ENT_XML1) . '</Serie>';
            $xml .= '<Tipo>' . htmlspecialchars($rps['infRps']['tipo'], ENT_XML1) . '</Tipo>';
            $xml .= '</IdentificacaoRps>';
            $xml .= '<DataEmissao>' . htmlspecialchars($rps['infRps']['dataEmissao'], ENT_XML1) . '</DataEmissao>';
            $xml .= '<Status>1</Status>';
            $xml .= '</Rps>';
            $xml .= '<Competencia>' . htmlspecialchars($rps['competencia'], ENT_XML1) . '</Competencia>';
            $xml .= '<Servico>';
            $xml .= '<Valores>';
            $xml .= '<ValorServicos>' . htmlspecialchars($rps['valorServicos'], ENT_XML1) . '</ValorServicos>';
            $xml .= '<ValorIss>' . htmlspecialchars($rps['valorIss'], ENT_XML1) . '</ValorIss>';
            $xml .= '<Aliquota>' . htmlspecialchars($rps['aliquota'], ENT_XML1) . '</Aliquota>';
            $xml .= '</Valores>';
            $xml .= '<IssRetido>' . htmlspecialchars($rps['issRetido'], ENT_XML1) . '</IssRetido>';
            $xml .= '<ItemListaServico>' . htmlspecialchars($rps['itemListaServico'], ENT_XML1) . '</ItemListaServico>';
            $xml .= '<Discriminacao>' . htmlspecialchars($rps['discriminacao'], ENT_XML1) . '</Discriminacao>';
            $xml .= '<CodigoMunicipio>' . htmlspecialchars($rps['codigoMunicipio'], ENT_XML1) . '</CodigoMunicipio>';
            $xml .= '<ExigibilidadeISS>' . htmlspecialchars($rps['exigibilidadeISS'], ENT_XML1) . '</ExigibilidadeISS>';
            $xml .= '</Servico>';
            $xml .= '<Prestador>';
            $xml .= '<CpfCnpj><Cnpj>' . htmlspecialchars($dados['cnpjPrestador'], ENT_XML1) . '</Cnpj></CpfCnpj>';
            $xml .= '<InscricaoMunicipal>' . htmlspecialchars($dados['inscricaoMunicipal'], ENT_XML1) . '</InscricaoMunicipal>';
            $xml .= '</Prestador>';
            $xml .= '<Tomador>';
            $xml .= '<IdentificacaoTomador>';
            $xml .= '<CpfCnpj><Cnpj>' . htmlspecialchars($rps['tomador']['cpfCnpj'], ENT_XML1) . '</Cnpj></CpfCnpj>';
            $xml .= '<InscricaoMunicipal>' . htmlspecialchars($rps['tomador']['inscricaoMunicipal'], ENT_XML1) . '</InscricaoMunicipal>';
            $xml .= '</IdentificacaoTomador>';
            $xml .= '<RazaoSocial>' . htmlspecialchars($rps['tomador']['razaoSocial'], ENT_XML1) . '</RazaoSocial>';
            $xml .= '<Endereco>';
            $xml .= '<Endereco>' . htmlspecialchars($rps['tomador']['endereco']['logradouro'], ENT_XML1) . '</Endereco>';
            $xml .= '<Numero>' . htmlspecialchars($rps['tomador']['endereco']['numero'], ENT_XML1) . '</Numero>';
            $xml .= '<Bairro>' . htmlspecialchars($rps['tomador']['endereco']['bairro'], ENT_XML1) . '</Bairro>';
            $xml .= '<CodigoMunicipio>' . htmlspecialchars($rps['tomador']['endereco']['codigoMunicipio'], ENT_XML1) . '</CodigoMunicipio>';
            $xml .= '<Uf>' . htmlspecialchars($rps['tomador']['endereco']['uf'], ENT_XML1) . '</Uf>';
            $xml .= '<Cep>' . htmlspecialchars($rps['tomador']['endereco']['cep'], ENT_XML1) . '</Cep>';
            $xml .= '</Endereco>';
            $xml .= '<Contato>';
            $xml .= '<Telefone>' . htmlspecialchars($rps['tomador']['telefone'], ENT_XML1) . '</Telefone>';
            $xml .= '<Email>' . htmlspecialchars($rps['tomador']['email'], ENT_XML1) . '</Email>';
            $xml .= '</Contato>';
            $xml .= '</Tomador>';
            $xml .= '<RegimeEspecialTributacao>' . htmlspecialchars($rps['regimeEspecialTributacao'], ENT_XML1) . '</RegimeEspecialTributacao>';
            $xml .= '<OptanteSimplesNacional>' . htmlspecialchars($rps['optanteSimplesNacional'], ENT_XML1) . '</OptanteSimplesNacional>';
            $xml .= '<IncentivoFiscal>' . htmlspecialchars($rps['incentivoFiscal'], ENT_XML1) . '</IncentivoFiscal>';
            // Assinatura do RPS
            if ($signatureRps) {
                $xml .= $signatureRps;
            }
            $xml .= '</InfDeclaracaoPrestacaoServico>';
            $xml .= '</Rps>';
        }
        $xml .= '</ListaRps>';
        $xml .= '</LoteRps>';
        // Assinatura do Lote
        if ($signatureLote) {
            $xml .= $signatureLote;
        }
        $xml .= '</EnviarLoteRpsEnvio>';
        return $xml;
    }
    /**
     * Gera dados de exemplo para emissão de NFSe em Porto Seguro/BA
     * @return array
     */
    public static function gerarDadosExemplo()
    {
        return [
            'numeroLote' => '1',
            'cnpjPrestador' => '49535940000174',
            'inscricaoMunicipal' => '173013001',
            'quantidadeRps' => 1,
            'rps' => [[
                'infRps' => [
                    'numero' => '1',
                    'serie'  => '1',
                    'tipo'   => '1',
                    'dataEmissao' => date('Y-m-d\TH:i:sP'),
                ],
                'competencia' => date('Y-m-01'),
                'itemListaServico' => '0710',
                'codigoTribMunicipio' => '0710',
                'discriminacao' => 'ServiCo',
                'codigoMunicipio' => '2925303',
                'tomador' => [
                    'cpfCnpj' => '93102208568',
                    'razaoSocial' => 'MARCIONILIO ALEX CURVELO SANTOS',
                    'endereco' => [
                        'logradouro' => 'Rua dos Beija Flores',
                        'numero' => '30',
                        'bairro' => 'MIRANTE',
                        'codigoMunicipio' => '2925303',
                        'uf' => 'BA',
                        'cep' => '45810000',
                    ],
                ],
            ]]
        ];
    }
    }