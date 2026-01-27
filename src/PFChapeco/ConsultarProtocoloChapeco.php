<?php

namespace NFSePrefeitura\NFSe\PFChapeco;

use SoapClient;
use SoapFault;
use Exception;

/**
 * Classe para consultar o protocolo da NFS-e de Chapecó/SC
 * Segue o padrão ABRASF para consulta de lote RPS
 */
class ConsultarProtocoloChapeco
{
    /**
     * Constante com o WSDL principal (único para Chapecó)
     */
    private const WSDL = 'https://chapeco.meumunicipio.online/abrasf/ws/nfs?wsdl';
    
    /**
     * URLs alternativas para WSDL (backup)
     */
    private const WSDL_ALTERNATIVOS = [
        'https://chapeco.sc.gov.br/nfse/ws/nfse.asmx?WSDL'
    ];
    
    /**
     * Endpoint SOAP para modo non-WSDL
     */
    private const SOAP_ENDPOINT = 'https://chapeco.meumunicipio.online/abrasf/ws/nfs';
    
    /**
     * Namespace para SOAP
     */
    private const SOAP_NAMESPACE = 'http://www.abrasf.org.br/nfse.xsd';
    
    private string $certPath;
    private string $certPassword;
    
    private ?string $lastRequest = null;
    private ?string $lastResponse = null;
    
    public function __construct(string $certPath, string $certPassword)
    {
        if (!file_exists($certPath)) {
            throw new \InvalidArgumentException("Certificado não encontrado: {$certPath}");
        }
        
        $this->certPath = $certPath;
        $this->certPassword = $certPassword;
    }
    
    /**
     * Retorna a última requisição SOAP enviada
     */
    public function getLastRequest(): ?string
    {
        return $this->lastRequest;
    }
    
    /**
     * Retorna a última resposta SOAP recebida
     */
    public function getLastResponse(): ?string
    {
        return $this->lastResponse;
    }
    
    /**
     * Cria e configura o cliente SOAP
     */
    private function createSoapClient(): SoapClient
    {
        $contextOptions = [
            'ssl' => [
                'local_cert' => $this->certPath,
                'passphrase' => $this->certPassword,
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];
        
        $streamContext = stream_context_create($contextOptions);
        
        $options = [
            'stream_context' => $streamContext,
            'trace' => 1,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'soap_version' => SOAP_1_1,
            'encoding' => 'UTF-8',
            'connection_timeout' => 60,
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ];
        
        // Tenta criar o cliente SOAP com diferentes abordagens
        $wsdlUrls = array_merge([self::WSDL], self::WSDL_ALTERNATIVOS);
        
        $lastError = null;
        foreach ($wsdlUrls as $wsdlUrl) {
            try {
                return new SoapClient($wsdlUrl, $options);
            } catch (SoapFault $e) {
                $lastError = $e;
                // Tenta o próximo URL
                continue;
            }
        }
        
        // Se nenhum URL funcionou, tenta criar um cliente SOAP sem WSDL (modo non-WSDL)
        try {
            return $this->createNonWsdlClient();
        } catch (\Exception $e) {
            throw new Exception(
                "Erro ao criar cliente SOAP. Todos os URLs WSDL falharam.\n" .
                "Último erro: " . ($lastError ? $lastError->getMessage() : 'Desconhecido') . "\n" .
                "Erro modo non-WSDL: " . $e->getMessage()
            );
        }
    }
    
    /**
     * Cria cliente SOAP sem WSDL (modo non-WSDL)
     */
    private function createNonWsdlClient(): SoapClient
    {
        $contextOptions = [
            'ssl' => [
                'local_cert' => $this->certPath,
                'passphrase' => $this->certPassword,
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];
        
        $streamContext = stream_context_create($contextOptions);
        
        $options = [
            'stream_context' => $streamContext,
            'trace' => 1,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'soap_version' => SOAP_1_1,
            'encoding' => 'UTF-8',
            'connection_timeout' => 60,
            'location' => self::SOAP_ENDPOINT,
            'uri' => self::SOAP_NAMESPACE,
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ];
        
        return new SoapClient(null, $options);
    }
    
    /**
     * Cria o XML de consulta do protocolo no padrão ABRASF
     */
    private function criarXmlConsulta(string $cnpj, string $inscricaoMunicipal, string $protocolo): string
    {
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        $inscricaoMunicipal = preg_replace('/[^0-9]/', '', $inscricaoMunicipal);
        $protocolo = htmlspecialchars($protocolo, ENT_QUOTES | ENT_XML1, 'UTF-8');
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<ConsultarLoteRpsEnvio xmlns="http://www.abrasf.org.br/nfse.xsd">';
        $xml .= '<Prestador>';
        $xml .= "<Cnpj>{$cnpj}</Cnpj>";
        $xml .= "<InscricaoMunicipal>{$inscricaoMunicipal}</InscricaoMunicipal>";
        $xml .= '</Prestador>';
        $xml .= "<Protocolo>{$protocolo}</Protocolo>";
        $xml .= '</ConsultarLoteRpsEnvio>';
        
        return $xml;
    }
    
    /**
     * Cria o cabeçalho SOAP no padrão ABRASF
     */
    private function criarCabecalho(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<cabecalho versao="2.04" xmlns="http://www.abrasf.org.br/nfse.xsd">'
            . '<versaoDados>2.04</versaoDados>'
            . '</cabecalho>';
    }
    
    /**
     * Consulta o protocolo do lote RPS
     * 
     * @param string $cnpj CNPJ do prestador (apenas números)
     * @param string $inscricaoMunicipal Inscrição municipal do prestador
     * @param string $protocolo Protocolo a ser consultado
     * @return array Array com os dados da consulta
     * @throws Exception em caso de erro
     */
    public function consultarProtocolo(string $cnpj, string $inscricaoMunicipal, string $protocolo): array
    {
        // Validações básicas
        $cnpjLimpo = preg_replace('/[^0-9]/', '', $cnpj);
        if (strlen($cnpjLimpo) !== 14) {
            throw new \InvalidArgumentException('CNPJ inválido. Deve conter 14 dígitos.');
        }
        
        if (empty($protocolo)) {
            throw new \InvalidArgumentException('Protocolo não pode estar vazio.');
        }
        
        try {
            $client = $this->createSoapClient();
            
            $xmlConsulta = $this->criarXmlConsulta($cnpj, $inscricaoMunicipal, $protocolo);
            $cabecalho = $this->criarCabecalho();
            
            $params = [
                'nfseCabecMsg' => $cabecalho,
                'nfseDadosMsg' => $xmlConsulta
            ];
            
            // Realiza a chamada SOAP
            try {
                $response = $client->__soapCall('ConsultarLoteRps', [$params]);
            } catch (SoapFault $soapFault) {
                // Se falhar com WSDL, tenta com non-WSDL
                if (strpos($soapFault->getMessage(), 'WSDL') !== false || 
                    strpos($soapFault->getMessage(), 'Function') !== false) {
                    $response = $this->callSoapNonWsdl($client, $params);
                } else {
                    throw $soapFault;
                }
            }
            
            // Armazena a requisição e resposta para debug
            $this->lastRequest = $client->__getLastRequest() ?? '';
            $this->lastResponse = $client->__getLastResponse() ?? '';
            
            // Processa a resposta
            return $this->processarResposta($response);
            
        } catch (SoapFault $e) {
            throw new Exception(
                "Erro ao consultar protocolo: " . $e->getMessage() . "\n" .
                "REQUEST: " . $this->lastRequest . "\n" .
                "RESPONSE: " . $this->lastResponse
            );
        } catch (\Exception $e) {
            throw new Exception("Erro geral na consulta: " . $e->getMessage());
        }
    }
    
    /**
     * Realiza chamada SOAP no modo non-WSDL
     */
    private function callSoapNonWsdl(SoapClient $client, array $params): mixed
    {
        $xmlRequest = $this->createSoapEnvelope($params['nfseCabecMsg'], $params['nfseDadosMsg']);
        
        $location = self::SOAP_ENDPOINT;
        $action = self::SOAP_NAMESPACE . '/ConsultarLoteRps';
        
        try {
            $response = $client->__doRequest($xmlRequest, $location, $action, SOAP_1_1);
            
            if ($response === false) {
                throw new Exception('Falha na requisição SOAP');
            }
            
            return $response;
            
        } catch (\Exception $e) {
            throw new Exception("Erro na chamada non-WSDL: " . $e->getMessage());
        }
    }
    
    /**
     * Cria envelope SOAP manualmente
     */
    private function createSoapEnvelope(string $cabecalho, string $dadosMsg): string
    {
        $envelope = '<?xml version="1.0" encoding="UTF-8"?>';
        $envelope .= '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $envelope .= '<soap:Body>';
        $envelope .= '<ConsultarLoteRpsRequest xmlns="http://www.abrasf.org.br/nfse.xsd">';
        $envelope .= '<nfseCabecMsg><![CDATA[' . $cabecalho . ']]></nfseCabecMsg>';
        $envelope .= '<nfseDadosMsg><![CDATA[' . $dadosMsg . ']]></nfseDadosMsg>';
        $envelope .= '</ConsultarLoteRpsRequest>';
        $envelope .= '</soap:Body>';
        $envelope .= '</soap:Envelope>';
        
        return $envelope;
    }
    
    /**
     * Processa a resposta da consulta
     */
    private function processarResposta($response): array
    {
        // Se a resposta já for um array, retorna direto
        if (is_array($response)) {
            return $response;
        }
        
        // Se for objeto, converte para array
        if (is_object($response)) {
            return json_decode(json_encode($response), true);
        }
        
        // Se for string XML, tenta parsear
        if (is_string($response)) {
            return $this->parseXmlResposta($response);
        }
        
        return ['raw_response' => $response];
    }
    
    /**
     * Parseia o XML de resposta
     */
    private function parseXmlResposta(string $xml): array
    {
        $dom = new \DOMDocument();
        if (!@$dom->loadXML($xml)) {
            return ['xml_raw' => $xml];
        }
        
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('ns', 'http://www.abrasf.org.br/nfse.xsd');
        
        $resultado = [];
        
        // Extrai informações básicas da resposta
        $numeroLote = $xpath->query('//ns:NumeroLote')->item(0);
        if ($numeroLote) {
            $resultado['numero_lote'] = $numeroLote->nodeValue;
        }
        
        $situacao = $xpath->query('//ns:Situacao')->item(0);
        if ($situacao) {
            $resultado['situacao'] = $situacao->nodeValue;
        }
        
        $dataHora = $xpath->query('//ns:DataHora')->item(0);
        if ($dataHora) {
            $resultado['data_hora'] = $dataHora->nodeValue;
        }
        
        $motivo = $xpath->query('//ns:Motivo')->item(0);
        if ($motivo) {
            $resultado['motivo'] = $motivo->nodeValue;
        }
        
        // Se houver notas fiscais na resposta
        $notas = $xpath->query('//ns:NotaFiscal');
        if ($notas->length > 0) {
            $resultado['notas_fiscais'] = [];
            foreach ($notas as $nota) {
                $notaData = [];
                
                $numero = $xpath->query('.//ns:Numero', $nota)->item(0);
                if ($numero) {
                    $notaData['numero'] = $numero->nodeValue;
                }
                
                $codVerificacao = $xpath->query('.//ns:CodigoVerificacao', $nota)->item(0);
                if ($codVerificacao) {
                    $notaData['codigo_verificacao'] = $codVerificacao->nodeValue;
                }
                
                $dataEmissao = $xpath->query('.//ns:DataEmissao', $nota)->item(0);
                if ($dataEmissao) {
                    $notaData['data_emissao'] = $dataEmissao->nodeValue;
                }
                
                $resultado['notas_fiscais'][] = $notaData;
            }
        }
        
        return $resultado;
    }
    
    /**
     * Retorna a situação do lote como string legível
     */
    public static function getSituacaoTexto(string $situacao): string
    {
        $situacoes = [
            '1' => 'Lote não processado',
            '2' => 'Lote processado com sucesso',
            '3' => 'Lote processado com erros',
            '4' => 'Lote processado com alertas'
        ];
        
        return $situacoes[$situacao] ?? "Situação desconhecida ({$situacao})";
    }
}