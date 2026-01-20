<?php
namespace NFSePrefeitura\NFSe\PFPartina;

use SoapClient;
use SoapFault;
use Exception;
use InvalidArgumentException;

class Partina
{
    private const WSDL = 'https://wsnfse.partina.rn.gov.br/axis2/services/NfseWSServiceV1?wsdl';

    private string $certPath;
    private string $certPassword;

    public function __construct(string $certPath, string $certPassword)
    {
        $this->certPath     = $certPath;
        $this->certPassword = $certPassword;
    }

    private function client(): SoapClient
    {
        return new SoapClient(self::WSDL, [
            'soap_version' => SOAP_1_1,
            'trace'        => true,
            'exceptions'   => true,
            'cache_wsdl'   => WSDL_CACHE_NONE,
            'stream_context' => stream_context_create([
                'ssl' => [
                    'local_cert'        => $this->certPath,
                    'passphrase'        => $this->certPassword,
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ]
            ])
        ]);
    }

    public function enviarLoteRps(string $xmlAssinado): object
    {
        $client = $this->client();

        $cabecalho = '<cabecalho xmlns="http://www.abrasf.org.br/nfse.xsd" versao="2.04"><versaoDados>2.02</versaoDados></cabecalho>';
        $params = [
            'nfseCabecMsg' => $cabecalho,
            'nfseDadosMsg' => $xmlAssinado
        ];

        try {
            return $client->__soapCall('RecepcionarLoteRps', [$params]);
        } catch (SoapFault $e) {
            throw new Exception(
                "ERRO SOAP:\n{$e->getMessage()}\n\nREQUEST:\n{$client->__getLastRequest()}\n\nRESPONSE:\n{$client->__getLastResponse()}",
                0,
                $e
            );
        }
    }

    public function consultarSituacaoLoteRps(string $protocolo, string $cnpj, string $inscricaoMunicipal): object
    {
        $client = $this->client();
        
        $cabecalho = '<cabecalho xmlns="http://www.abrasf.org.br/nfse.xsd" versao="2.04"><versaoDados>2.02</versaoDados></cabecalho>';
        
        $xml = '<?xml version="1.0"?>';
        $xml .= '<ConsultarSituacaoLoteRpsEnvio xmlns="http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd">';
        $xml .= '<Prestador>';
        $xml .= '<Cnpj>' . $cnpj . '</Cnpj>';
        $xml .= '<InscricaoMunicipal>' . $inscricaoMunicipal . '</InscricaoMunicipal>';
        $xml .= '</Prestador>';
        $xml .= '<Protocolo>' . $protocolo . '</Protocolo>';
        $xml .= '</ConsultarSituacaoLoteRpsEnvio>';

        $params = [
            'nfseCabecMsg' => $cabecalho,
            'nfseDadosMsg' => $xml
        ];

        try {
            return $client->__soapCall('ConsultarSituacaoLoteRps', [$params]);
        } catch (SoapFault $e) {
            throw new Exception(
                "ERRO SOAP:\n{$e->getMessage()}\n\nREQUEST:\n{$client->__getLastRequest()}\n\nRESPONSE:\n{$client->__getLastResponse()}",
                0,
                $e
            );
        }
    }
}