<?php

namespace NFSePrefeitura\NFSe\PFSalvador;

use SoapClient;
use SoapFault;
use Exception;

/**
 * Classe Salvador - Implementação do padrão ABRASF v2.04 para a Prefeitura de Salvador - BA
 * Baseada nas implementações de Chapecó-SC e Natal-RN
 */
class Salvador
{
    private const VERSAO = '2.04';
    private string $certPath;
    private string $certPassword;
    private string $wsdl;

    /**
     * Construtor da classe
     * 
     * @param string $certPath Caminho do certificado digital
     * @param string $certPassword Senha do certificado
     * @param string $ambiente 'homologacao' ou 'producao'
     */
    public function __construct(string $certPath, string $certPassword, string $ambiente = 'homologacao')
    {
        $this->certPath = $certPath;
        $this->certPassword = $certPassword;
        
        // Define o WSDL baseado no ambiente
        if ($ambiente === 'producao') {
            $this->wsdl = 'https://nfse.salvador.ba.gov.br/rps/ENVIOLOTERPS/EnvioLoteRPS.svc?wsdl'; // URL de produção oficial
        } else {
            $this->wsdl = 'https://notahml.salvador.ba.gov.br/rps/ENVIOLOTERPS/EnvioLoteRPS.svc?wsdl'; // URL de homologação oficial
        }
    }

    /**
     * Cria e retorna o SoapClient configurado
     */
    private function getSoapClient(): SoapClient
    {
        $options = [
            'local_cert' => $this->certPath,
            'passphrase' => $this->certPassword,
            'trace' => 1,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'soap_version' => SOAP_1_1,
            'encoding' => 'UTF-8'
        ];
        
        return new SoapClient($this->wsdl, $options);
    }

    /**
     * Transmite o XML assinado para o WebService da prefeitura
     * Retorna o XML de resposta
     */
    public function transmitirLoteRps(string $xmlAssinado): string
    {
        $client = $this->getSoapClient();
        
        // Estrutura do envelope SOAP para Salvador-BA
        $cabecalho = '<?xml version="1.0" encoding="UTF-8"?>';
        $cabecalho .= '<cabecalho xmlns="http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd" versao="' . self::VERSAO . '">';
        $cabecalho .= '<versaoDados>2</versaoDados>';
        $cabecalho .= '</cabecalho>';
        
        try {
            $params = [
                'nfseCabecMsg' => $cabecalho,
                'nfseDadosMsg' => $xmlAssinado
            ];
            
            $response = $client->__soapCall('RecepcionarLoteRps', [$params]);
            
            if (is_object($response) && isset($response->outputXML)) {
                return $response->outputXML;
            }
            
            return (string) $response;
        } catch (SoapFault $e) {
            throw new Exception('Erro ao transmitir lote RPS para Salvador-BA: ' . $e->getMessage());
        }
    }

    /**
     * Consulta a situação de um lote RPS
     */
    public function consultarSituacaoLoteRps(string $cnpj, string $inscricaoMunicipal, string $protocolo): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<ConsultarSituacaoLoteRpsEnvio xmlns="http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd">';
        $xml .= '<Prestador>';
        $xml .= '<Cnpj>' . htmlspecialchars($cnpj) . '</Cnpj>';
        $xml .= '<InscricaoMunicipal>' . htmlspecialchars($inscricaoMunicipal) . '</InscricaoMunicipal>';
        $xml .= '</Prestador>';
        $xml .= '<Protocolo>' . htmlspecialchars($protocolo) . '</Protocolo>';
        $xml .= '</ConsultarSituacaoLoteRpsEnvio>';
        
        $client = $this->getSoapClient();
        
        $cabecalho = '<?xml version="1.0" encoding="UTF-8"?>';
        $cabecalho .= '<cabecalho xmlns="http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd" versao="' . self::VERSAO . '">';
        $cabecalho .= '<versaoDados>2</versaoDados>';
        $cabecalho .= '</cabecalho>';
        
        try {
            $params = [
                'nfseCabecMsg' => $cabecalho,
                'nfseDadosMsg' => $xml
            ];
            
            $response = $client->__soapCall('ConsultarSituacaoLoteRps', [$params]);
            
            if (is_object($response) && isset($response->outputXML)) {
                return $response->outputXML;
            }
            
            return (string) $response;
        } catch (SoapFault $e) {
            throw new Exception('Erro ao consultar situação do lote RPS: ' . $e->getMessage());
        }
    }

    /**
     * Consulta um lote RPS já processado
     */
    public function consultarLoteRps(string $cnpj, string $inscricaoMunicipal, string $protocolo): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<ConsultarLoteRpsEnvio xmlns="http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd">';
        $xml .= '<Prestador>';
        $xml .= '<Cnpj>' . htmlspecialchars($cnpj) . '</Cnpj>';
        $xml .= '<InscricaoMunicipal>' . htmlspecialchars($inscricaoMunicipal) . '</InscricaoMunicipal>';
        $xml .= '</Prestador>';
        $xml .= '<Protocolo>' . htmlspecialchars($protocolo) . '</Protocolo>';
        $xml .= '</ConsultarLoteRpsEnvio>';
        
        $client = $this->getSoapClient();
        
        $cabecalho = '<?xml version="1.0" encoding="UTF-8"?>';
        $cabecalho .= '<cabecalho xmlns="http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd" versao="' . self::VERSAO . '">';
        $cabecalho .= '<versaoDados>2</versaoDados>';
        $cabecalho .= '</cabecalho>';
        
        try {
            $params = [
                'nfseCabecMsg' => $cabecalho,
                'nfseDadosMsg' => $xml
            ];
            
            $response = $client->__soapCall('ConsultarLoteRps', [$params]);
            
            if (is_object($response) && isset($response->outputXML)) {
                return $response->outputXML;
            }
            
            return (string) $response;
        } catch (SoapFault $e) {
            throw new Exception('Erro ao consultar lote RPS: ' . $e->getMessage());
        }
    }

    /**
     * Cancela uma NFSe
     */
    public function cancelarNfse(string $cnpj, string $inscricaoMunicipal, string $numeroNfse, string $codigoCancelamento, string $justificativa = ''): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<CancelarNfseEnvio xmlns="http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd">';
        $xml .= '<Pedido>';
        $xml .= '<InfPedidoCancelamento Id="cancelamento_' . htmlspecialchars($numeroNfse) . '">';
        $xml .= '<IdentificacaoNfse>';
        $xml .= '<Numero>' . htmlspecialchars($numeroNfse) . '</Numero>';
        $xml .= '<Cnpj>' . htmlspecialchars($cnpj) . '</Cnpj>';
        $xml .= '<InscricaoMunicipal>' . htmlspecialchars($inscricaoMunicipal) . '</InscricaoMunicipal>';
        $xml .= '<CodigoMunicipio>2927408</CodigoMunicipio>'; // Código de Salvador-BA
        $xml .= '</IdentificacaoNfse>';
        $xml .= '<CodigoCancelamento>' . htmlspecialchars($codigoCancelamento) . '</CodigoCancelamento>';
        if (!empty($justificativa)) {
            $xml .= '<Justificativa>' . htmlspecialchars($justificativa) . '</Justificativa>';
        }
        $xml .= '</InfPedidoCancelamento>';
        $xml .= '</Pedido>';
        $xml .= '</CancelarNfseEnvio>';
        
        $client = $this->getSoapClient();
        
        $cabecalho = '<?xml version="1.0" encoding="UTF-8"?>';
        $cabecalho .= '<cabecalho xmlns="http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd" versao="' . self::VERSAO . '">';
        $cabecalho .= '<versaoDados>2</versaoDados>';
        $cabecalho .= '</cabecalho>';
        
        try {
            $params = [
                'nfseCabecMsg' => $cabecalho,
                'nfseDadosMsg' => $xml
            ];
            
            $response = $client->__soapCall('CancelarNfse', [$params]);
            
            if (is_object($response) && isset($response->outputXML)) {
                return $response->outputXML;
            }
            
            return (string) $response;
        } catch (SoapFault $e) {
            throw new Exception('Erro ao cancelar NFSe: ' . $e->getMessage());
        }
    }

    /**
     * Gera, assina e transmite o lote RPS completo
     * Retorna array com XML gerado, assinado e resposta
     */
    public function gerarAssinarTransmitirLoteRps(array $dadosLote): array
    {
        // Gera XML
        $gerador = new SalvadorGeradorXML();
        $xmlGerado = $gerador->gerarXmlLoteRps($dadosLote);
        
        // Assina XML (será implementado quando houver a classe de assinatura)
        // $assinador = new SalvadorAssinatura($this->certPath, $this->certPassword);
        // $xmlAssinado = $assinador->assinarLoteRps($xmlGerado);
        
        // Por enquanto, usa o XML gerado como assinado
        $xmlAssinado = $xmlGerado;
        
        // Transmite XML
        $xmlResposta = $this->transmitirLoteRps($xmlAssinado);
        
        return [
            'xml_gerado' => $xmlGerado,
            'xml_assinado' => $xmlAssinado,
            'xml_resposta' => $xmlResposta
        ];
    }
}