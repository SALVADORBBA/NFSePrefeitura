<?php

namespace NFSePrefeitura\NFSe\PFChapeco;

/**
 * Classe para transmitir o XML assinado para a prefeitura de Chapecó via SOAP
 */
class TransmitirChapeco
{
    public static function transmitirLote(string $xmlAssinado, string $wsdl ='https://chapeco.meumunicipio.online/abrasf/ws/nfs?wsdl')
    {
        // Cabeçalho padrão ABRASF
        $cabecalho = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<cabecalho versao="2.04" xmlns="http://www.abrasf.org.br/nfse.xsd">'
            . '<versaoDados>2.04</versaoDados>'
            . '</cabecalho>';

        try {
            echo "[TRANSMissão] Conectando ao WSDL: $wsdl\n";
            
            $client = new \SoapClient($wsdl, [
                'trace' => true,
                'exceptions' => true,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'connection_timeout' => 60,
                'stream_context' => stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ])
            ]);

            echo "[TRANSMissão] Conexão SOAP estabelecida com sucesso\n";

            $params = [
                'nfseCabecMsg' => $cabecalho,
                'nfseDadosMsg' => $xmlAssinado,
            ];

            echo "[TRANSMissão] Enviando lote RPS...\n";
            $response = $client->__soapCall('RecepcionarLoteRps', [$params]);
            
            // Debug: vamos ver o que está vindo
            $debug_info = "=== DEBUG SOAP ===\n";
            $debug_info .= "REQUEST HEADERS:\n" . $client->__getLastRequestHeaders() . "\n";
            $debug_info .= "REQUEST:\n" . $client->__getLastRequest() . "\n";
            $debug_info .= "RESPONSE HEADERS:\n" . $client->__getLastResponseHeaders() . "\n";
            $debug_info .= "RESPONSE:\n" . $client->__getLastResponse() . "\n";
            
            file_put_contents(__DIR__ . '/debug_soap.txt', $debug_info, FILE_APPEND);
            echo "[TRANSMissão] Debug SOAP salvo em debug_soap.txt\n";
            
            // Processa a resposta
            if (is_object($response)) {
                if (isset($response->outputXML)) {
                    echo "[TRANSMissão] Resposta recebida com outputXML\n";
                    return $response->outputXML;
                } elseif (isset($response->RecepcionarLoteRpsResponse->outputXML)) {
                    echo "[TRANSMissão] Resposta recebida com RecepcionarLoteRpsResponse->outputXML\n";
                    return $response->RecepcionarLoteRpsResponse->outputXML;
                } else {
                    $resposta = print_r($response, true);
                    echo "[TRANSMissão] Resposta recebida (formato diferente): $resposta\n";
                    return $resposta;
                }
            } else {
                $resposta = (string) $response;
                echo "[TRANSMissão] Resposta recebida (string): $resposta\n";
                return $resposta;
            }
            
        } catch (\SoapFault $e) {
            // Salva erro em arquivo de debug
            $erro = "ERRO NA TRANSMissão (SoapFault):\n";
            $erro .= "Mensagem: " . $e->getMessage() . "\n";
            $erro .= "Código: " . $e->getCode() . "\n";
            $erro .= "Arquivo: " . $e->getFile() . "\n";
            $erro .= "Linha: " . $e->getLine() . "\n";
            
            if (isset($client)) {
                $erro .= "REQUEST HEADERS:\n" . $client->__getLastRequestHeaders() . "\n";
                $erro .= "REQUEST:\n" . $client->__getLastRequest() . "\n";
                $erro .= "RESPONSE HEADERS:\n" . $client->__getLastResponseHeaders() . "\n";
                $erro .= "RESPONSE:\n" . $client->__getLastResponse() . "\n";
            }
            
            file_put_contents(__DIR__ . '/debug_transmissao.txt', $erro . "\n", FILE_APPEND);
            echo "[TRANSMissão] Erro SOAP detalhado salvo em debug_transmissao.txt\n";
            
            throw new \Exception("Erro na transmissão SOAP: " . $e->getMessage());
            
        } catch (\Exception $e) {
            // Salva erro em arquivo de debug
            $erro = "ERRO NA TRANSMissão (Exception):\n";
            $erro .= "Mensagem: " . $e->getMessage() . "\n";
            $erro .= "Código: " . $e->getCode() . "\n";
            $erro .= "Arquivo: " . $e->getFile() . "\n";
            $erro .= "Linha: " . $e->getLine() . "\n";
            $erro .= "XML Enviado: " . substr($xmlAssinado, 0, 500) . "...\n";
            
            file_put_contents(__DIR__ . '/debug_transmissao.txt', $erro . "\n", FILE_APPEND);
            echo "[TRANSMissão] Erro detalhado salvo em debug_transmissao.txt\n";
            
            throw $e;
        }
    }
}