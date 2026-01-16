<?php
namespace DevelopApi;

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
     * Gera XML para envio de lote RPS
     * @param array $dados
     * @return string
     */
    private static function gerarXmlLoteRps($dados)
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><EnviarLoteRpsEnvio xmlns="http://www.abrasf.org.br/nfse.xsd" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.abrasf.org.br/nfse.xsd EnviarLoteRpsEnvio.xsd"></EnviarLoteRpsEnvio>');

        // Adiciona elemento LoteRps com atributos obrigatórios
        $loteRps = $xml->addChild('LoteRps');
        $loteRps->addAttribute('Id', 'Lote_' . $dados['numeroLote']);
        $loteRps->addAttribute('versao', '2.02');
        
        $loteRps->addChild('NumeroLote', $dados['numeroLote']);
        
        $cpfCnpj = $loteRps->addChild('CpfCnpj');
        $cpfCnpj = $loteRps->addChild('CpfCnpj');
        $cpfCnpj->addChild('Cnpj', $dados['cnpjPrestador']);
        
        $loteRps->addChild('InscricaoMunicipal', $dados['inscricaoMunicipal']);
        $loteRps->addChild('QuantidadeRps', $dados['quantidadeRps']);
        
        $listaRps = $loteRps->addChild('ListaRps');
        
        foreach ($dados['rps'] as $index => $rps) {
            $rpsNode = $listaRps->addChild('Rps');
            
            $infRps = $rpsNode->addChild('InfDeclaracaoPrestacaoServico');
            $infRps->addAttribute('Id', 'loteRPS_' . $index);
            
            $rpsChild = $infRps->addChild('Rps');
            
            $identificacao = $rpsChild->addChild('IdentificacaoRps');
            $identificacao->addChild('Numero', $rps['infRps']['numero']);
            $identificacao->addChild('Serie', $rps['infRps']['serie']);
            $identificacao->addChild('Tipo', $rps['infRps']['tipo']);
            
            $rpsChild->addChild('DataEmissao', $rps['infRps']['dataEmissao']);
            $rpsChild->addChild('Status', '1');
            
            $infRps->addChild('Competencia', $rps['competencia']);
            
            $servico = $infRps->addChild('Servico');
            
            $valores = $servico->addChild('Valores');
            $valores->addChild('ValorServicos', $rps['valorServicos'] ?? '0.00');
            
            $servico->addChild('IssRetido', $rps['issRetido'] ?? '2');
            $servico->addChild('ItemListaServico', $rps['itemListaServico']);
            $servico->addChild('CodigoCnae', $rps['codigoCnae'] ?? '');
            $servico->addChild('CodigoTributacaoMunicipio', $rps['codigoTributacaoMunicipio']);
            $servico->addChild('Discriminacao', $rps['discriminacao']);
            $servico->addChild('CodigoMunicipio', $rps['codigoMunicipio']);
            $servico->addChild('ExigibilidadeISS', $rps['exigibilidadeISS'] ?? '1');
            $servico->addChild('MunicipioIncidencia', $rps['codigoMunicipio']);
            
            $prestador = $infRps->addChild('Prestador');
            $prestadorCpfCnpj = $prestador->addChild('CpfCnpj');
            // Prestador com estrutura completa
            $prestador->addChild('RazaoSocial', $dados['prestador']['razao_social']);
            $enderecoPrestador = $prestador->addChild('Endereco');
            $enderecoPrestador->addChild('Logradouro', $dados['prestador']['logradouro']);
            $enderecoPrestador->addChild('Numero', $dados['prestador']['numero']);
            $enderecoPrestador->addChild('Bairro', $dados['prestador']['bairro']);
            $enderecoPrestador->addChild('CodigoMunicipio', $dados['prestador']['codigo_municipio']);
            $enderecoPrestador->addChild('UF', $dados['prestador']['uf']);
            $enderecoPrestador->addChild('CEP', $dados['prestador']['cep']);
            
            $tomador = $infRps->addChild('Tomador');
            $identificacaoTomador = $tomador->addChild('IdentificacaoTomador');
            $tomadorCpfCnpj = $identificacaoTomador->addChild('CpfCnpj');
            $tomadorCpfCnpj->addChild('Cnpj', $rps['tomador']['cpfCnpj']);
            // Dados completos do tomador
            $tomador->addChild('RazaoSocial', $rps['tomador']['razaoSocial']);
            $enderecoTomador = $tomador->addChild('Endereco');
            $enderecoTomador->addChild('Logradouro', $rps['tomador']['endereco']['logradouro']);
            $enderecoTomador->addChild('Numero', $rps['tomador']['endereco']['numero']);
            $enderecoTomador->addChild('Bairro', $rps['tomador']['endereco']['bairro']);
            $enderecoTomador->addChild('CodigoMunicipio', $rps['tomador']['endereco']['codigoMunicipio']);
            $enderecoTomador->addChild('UF', $rps['tomador']['endereco']['uf']);
            $enderecoTomador->addChild('CEP', $rps['tomador']['endereco']['cep']);
            $contatoTomador = $tomador->addChild('Contato');
            $contatoTomador->addChild('Telefone', $rps['tomador']['telefone'] ?? '');
            $contatoTomador->addChild('Email', $rps['tomador']['email'] ?? '');
            $tomador->addChild('RazaoSocial', $rps['tomador']['razaoSocial']);
            
            // Removendo bloco duplicado de endereço/contato
            
            $infRps->addChild('OptanteSimplesNacional', $rps['optanteSimplesNacional'] ?? '2');
            $infRps->addChild('IncentivoFiscal', $rps['incentivoFiscal'] ?? '2');
        }
        
        return $xml->asXML();
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
                    'dataEmissao' => date('Y-m-d\TH:i:s'),
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