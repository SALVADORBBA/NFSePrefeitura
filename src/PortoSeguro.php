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
    public function gerarXmlLoteRps(array $dados): string
    {
        $xml  = '<?xml version="1.0" encoding="utf-8"?>';
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
            $xml .= '<CpfCnpj><Cnpj>' . $rps['tomador']['cpfCnpj'] . '</Cnpj></CpfCnpj>';
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