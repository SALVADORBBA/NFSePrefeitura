<?php
namespace NFSePrefeitura\NFSe\PFChapeco;

/**
 * Classe para transmitir o XML assinado para a prefeitura de Chapecó via SOAP
 */
class TransmitirChapeco
{
    public static function transmitirLote(string $xmlAssinado, string $wsdl = 'https://chapeco.meumunicipio.online/abrasf/ws/nfs?wsdl')
    {
        // Cabeçalho padrão ABRASF
        $cabecalho = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<cabecalho versao="2.04" xmlns="http://www.abrasf.org.br/nfse.xsd">'
            . '<versaoDados>2.04</versaoDados>'
            . '</cabecalho>';

        try {
            $client = new \SoapClient($wsdl, [
                'trace' => true,
                'exceptions' => true,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'connection_timeout' => 60,
            ]);

            $params = [
                'nfseCabecMsg' => $cabecalho,
                'nfseDadosMsg' => $xmlAssinado,
            ];

            $response = $client->__soapCall('RecepcionarLoteRps', [$params]);
            return $response;
        } catch (\Exception $e) {
            // Salva erro em arquivo de debug
            file_put_contents(__DIR__ . '/debug_transmissao.txt', $e->getMessage() . "\n", FILE_APPEND);
            return false;
        }
    }
}