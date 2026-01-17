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
     * CLIENT SOAP
     * ========================================================= */
    private function getSoapClient(array $options = []): SoapClient
    {
        $default = [
            'soap_version' => SOAP_1_1,
            'trace'        => true,
            'exceptions'   => true,
            'cache_wsdl'   => WSDL_CACHE_NONE,
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                    'local_cert'        => $this->certPath,
                    'passphrase'        => $this->certPassword,
                ]
            ])
        ];

        return new SoapClient(self::WSDL, array_merge($default, $options));
    }

    /* =========================================================
     * ENVIO GERAR NFSE
     * ========================================================= */
    public function gerarNfse(array $dados): object
    {
        $client = $this->getSoapClient();

        $params = [
            'nfseCabecMsg' => $this->gerarCabecalho(),
            'nfseDadosMsg' => $this->gerarXmlGerarNfse($dados)
        ];

        try {
            return $client->__soapCall('GerarNfse', [$params]);
        } catch (SoapFault $e) {
            throw new Exception(
                "Erro SOAP: {$e->getMessage()}\n\nREQUEST:\n{$client->__getLastRequest()}\n\nRESPONSE:\n{$client->__getLastResponse()}",
                0,
                $e
            );
        }
    }

    /* =========================================================
     * CABEÇALHO NFSE
     * ========================================================= */
    private function gerarCabecalho(): string
    {
        return <<<XML
<cabecalho versao="2.02">
    <versaoDados>2.02</versaoDados>
</cabecalho>
XML;
    }

    /* =========================================================
     * XML GERAR NFSE (ABRASF 2.04)
     * ========================================================= */
    private function gerarXmlGerarNfse(array $dados): string
    {
        $xml  = '<?xml version="1.0" encoding="utf-8"?>';
        $xml .= '<GerarNfseEnvio xmlns="http://www.abrasf.org.br/nfse.xsd">';

        foreach ($dados['rps'] as $i => $rps) {

            $rpsId = 'RPS' . ($i + 1);
            $dpsId = 'DPS' . ($i + 1);

            $xml .= '<Rps>';

            /* ================= INF RPS ================= */
            $xml .= "<InfRps Id=\"{$rpsId}\">";
            $xml .= '<IdentificacaoRps>';
            $xml .= "<Numero>{$rps['infRps']['numero']}</Numero>";
            $xml .= "<Serie>{$rps['infRps']['serie']}</Serie>";
            $xml .= "<Tipo>{$rps['infRps']['tipo']}</Tipo>";
            $xml .= '</IdentificacaoRps>';
            $xml .= "<DataEmissao>{$rps['infRps']['dataEmissao']}</DataEmissao>";
            $xml .= '<Status>1</Status>';
            $xml .= '</InfRps>';

            /* ========== DECLARAÇÃO DE SERVIÇO ========== */
            $xml .= "<InfDeclaracaoPrestacaoServico Id=\"{$dpsId}\">";

            $xml .= '<Rps>';
            $xml .= '<IdentificacaoRps>';
            $xml .= "<Numero>{$rps['infRps']['numero']}</Numero>";
            $xml .= "<Serie>{$rps['infRps']['serie']}</Serie>";
            $xml .= "<Tipo>{$rps['infRps']['tipo']}</Tipo>";
            $xml .= '</IdentificacaoRps>';
            $xml .= '</Rps>';

            $xml .= "<Competencia>{$rps['competencia']}</Competencia>";

            /* ================= SERVIÇO ================= */
            $xml .= '<Servico>';
            $xml .= '<Valores>';
            $xml .= '<ValorServicos>' . number_format($rps['valorServicos'], 2, '.', '') . '</ValorServicos>';
            $xml .= '</Valores>';
            $xml .= "<ItemListaServico>{$rps['itemListaServico']}</ItemListaServico>";
            $xml .= "<CodigoMunicipio>{$rps['codigoMunicipio']}</CodigoMunicipio>";
            $xml .= '</Servico>';

            /* ================= PRESTADOR ================= */
            $xml .= '<Prestador>';
            $xml .= '<CpfCnpj><Cnpj>' . $dados['cnpjPrestador'] . '</Cnpj></CpfCnpj>';
            $xml .= '<InscricaoMunicipal>' . $dados['inscricaoMunicipal'] . '</InscricaoMunicipal>';
            $xml .= '</Prestador>';

            /* ================= TOMADOR ================= */
            $xml .= '<Tomador>';
            $xml .= '<RazaoSocial>' . htmlspecialchars($rps['tomador']['razaoSocial'], ENT_XML1) . '</RazaoSocial>';
            $xml .= '</Tomador>';

            $xml .= '</InfDeclaracaoPrestacaoServico>';
            $xml .= '</Rps>';
        }

        $xml .= '</GerarNfseEnvio>';

        return $xml;
    }
}
