<?php
namespace NFSePrefeitura\NFSe;

use SoapClient;
use SoapFault;
use Exception;

class PortoSeguro
{
    private const WSDL = 'https://portoseguroba.gestaoiss.com.br/ws/nfse.asmx?WSDL';

    private string $certPath;
    private string $certPassword;

    public function __construct(string $certPath, string $certPassword)
    {
        $this->certPath     = $certPath;
        $this->certPassword = $certPassword;
    }

    /* =========================================================
     * SOAP CLIENT
     * ========================================================= */
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

    /* =========================================================
     * ENVIO LOTE RPS
     * ========================================================= */
    public function enviarLoteRps(array $dados): object
    {
        $client = $this->client();
        $xml    = $this->xmlEnviarLoteRps($dados);

        try {
            return $client->__soapCall('EnviarLoteRps', [[
                'xml' => $xml
            ]]);
        } catch (SoapFault $e) {
            throw new Exception(
                "ERRO SOAP:\n{$e->getMessage()}\n\nREQUEST:\n{$client->__getLastRequest()}\n\nRESPONSE:\n{$client->__getLastResponse()}",
                0,
                $e
            );
        }
    }

    /* =========================================================
     * XML ENVIAR LOTE RPS (ABRASF 1.00)
     * ========================================================= */
    private function xmlEnviarLoteRps(array $dados): string
    {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<EnviarLoteRpsEnvio xmlns="http://www.abrasf.org.br/nfse.xsd">';
        $xml .= '<LoteRps Id="Lote1">';
        $xml .= "<NumeroLote>{$dados['numeroLote']}</NumeroLote>";

        $xml .= '<CpfCnpj><Cnpj>' . $dados['cnpjPrestador'] . '</Cnpj></CpfCnpj>';
        $xml .= "<InscricaoMunicipal>{$dados['inscricaoMunicipal']}</InscricaoMunicipal>";
        $xml .= "<QuantidadeRps>" . count($dados['rps']) . "</QuantidadeRps>";
        $xml .= '<ListaRps>';

        foreach ($dados['rps'] as $i => $rps) {
            $id = 'Rps' . ($i + 1);

            $xml .= '<Rps>';
            $xml .= "<InfRps Id=\"{$id}\">";

            $xml .= '<IdentificacaoRps>';
            $xml .= "<Numero>{$rps['numero']}</Numero>";
            $xml .= "<Serie>{$rps['serie']}</Serie>";
            $xml .= "<Tipo>1</Tipo>";
            $xml .= '</IdentificacaoRps>';

            $xml .= "<DataEmissao>{$rps['dataEmissao']}</DataEmissao>";
            $xml .= '<Status>1</Status>';

            $xml .= '<Servico>';
            $xml .= '<Valores>';
            $xml .= '<ValorServicos>' . number_format($rps['valor'], 2, '.', '') . '</ValorServicos>';
            $xml .= '</Valores>';
            $xml .= "<ItemListaServico>{$rps['itemListaServico']}</ItemListaServico>";
            $xml .= "<CodigoMunicipio>2925303</CodigoMunicipio>";
            $xml .= "<Discriminacao>{$rps['descricao']}</Discriminacao>";
            $xml .= '</Servico>';

            $xml .= '<Prestador>';
            $xml .= '<CpfCnpj><Cnpj>' . $dados['cnpjPrestador'] . '</Cnpj></CpfCnpj>';
            $xml .= "<InscricaoMunicipal>{$dados['inscricaoMunicipal']}</InscricaoMunicipal>";
            $xml .= '</Prestador>';

            $xml .= '<Tomador>';
            $xml .= "<RazaoSocial>{$rps['tomador']}</RazaoSocial>";
            $xml .= '</Tomador>';

            $xml .= '</InfRps>';
            $xml .= '</Rps>';
        }

        $xml .= '</ListaRps>';
        $xml .= '</LoteRps>';
        $xml .= '</EnviarLoteRpsEnvio>';

        return $xml;
    }
}
