<?php
namespace NFSePrefeitura\NFSe;

use SoapClient;
use SoapFault;
use Exception;
/**
 * Classe Natal - Implementação do padrão ABRASF para a Prefeitura de Natal - RN
 * Autor: Adaptado por Salvador BBA
 * Esta classe faz parte da biblioteca NFSePrefeituras para integração com o padrão ABRASF para Natal.
 *
 * Observações:
 * - O XML gerado segue rigorosamente o layout ABRASF/Natal.
 * - O método gerarXmlLoteRps NÃO inclui tags de assinatura digital, deixando o XML pronto para ser assinado externamente.
 * - Para detalhes do layout, consulte os exemplos em src/documetos/Natal.
 */
class Natal
{
    private const WSDL = 'https://wsnfsev1.natal.rn.gov.br:8444/axis2/services/NfseWSServiceV1?wsdl';

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

    public function enviarLoteRps(array $dados): object
    {
        $client = $this->client();
        $xml    = $this->gerarXmlLoteRps($dados);

        $cabecalho = '<cabecalho xmlns="http://www.abrasf.org.br/nfse.xsd" versao="2.04"><versaoDados>2.02</versaoDados></cabecalho>';
        $params = [
            'nfseCabecMsg' => $cabecalho,
            'nfseDadosMsg' => $xml
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

    public function gerarXmlLoteRps(array $dados): string
    {
        $xml  = '<?xml version="1.0" encoding="utf-8"?>';
        $xml .= '<EnviarLoteRpsEnvio xmlns="http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd">';
        $xml .= '<LoteRps Id="' . $dados['lote_id'] . '">';
        $xml .= '<NumeroLote>' . $dados['numeroLote'] . '</NumeroLote>';
        $xml .= '<Cnpj>' . $dados['cnpjPrestador'] . '</Cnpj>';
        $xml .= '<InscricaoMunicipal>' . $dados['inscricaoMunicipal'] . '</InscricaoMunicipal>';
        $xml .= '<QuantidadeRps>' . $dados['quantidadeRps'] . '</QuantidadeRps>';
        $xml .= '<ListaRps>';

        foreach ($dados['rps'] as $rps) {
            $xml .= '<Rps>';
            $xml .= '<InfRps Id="' . $rps['inf_id'] . '">';
            $xml .= '<IdentificacaoRps>';
            $xml .= '<Numero>' . $rps['infRps']['numero'] . '</Numero>';
            $xml .= '<Serie>' . $rps['infRps']['serie'] . '</Serie>';
            $xml .= '<Tipo>' . $rps['infRps']['tipo'] . '</Tipo>';
            $xml .= '</IdentificacaoRps>';
            $xml .= '<DataEmissao>' . $rps['infRps']['dataEmissao'] . '</DataEmissao>';
            $xml .= '<NaturezaOperacao>' . $rps['naturezaOperacao'] . '</NaturezaOperacao>';
            $xml .= '<OptanteSimplesNacional>' . $rps['optanteSimplesNacional'] . '</OptanteSimplesNacional>';
            $xml .= '<IncentivadorCultural>' . $rps['incentivadorCultural'] . '</IncentivadorCultural>';
            $xml .= '<Status>' . $rps['status'] . '</Status>';
            $xml .= '<Servico>';
            $xml .= '<Valores>';
            $xml .= '<ValorServicos>' . number_format($rps['valorServicos'], 2, '.', '') . '</ValorServicos>';
            $xml .= '<ValorPis>' . number_format($rps['valorPis'], 2, '.', '') . '</ValorPis>';
            $xml .= '<ValorCofins>' . number_format($rps['valorCofins'], 2, '.', '') . '</ValorCofins>';
            $xml .= '<ValorCsll>' . number_format($rps['valorCsll'], 2, '.', '') . '</ValorCsll>';
            $xml .= '<IssRetido>' . $rps['issRetido'] . '</IssRetido>';
            $xml .= '<ValorIss>' . number_format($rps['valorIss'], 2, '.', '') . '</ValorIss>';
            $xml .= '<ValorIssRetido>' . number_format($rps['valorIssRetido'], 2, '.', '') . '</ValorIssRetido>';
            $xml .= '<BaseCalculo>' . number_format($rps['baseCalculo'], 2, '.', '') . '</BaseCalculo>';
            $xml .= '<Aliquota>' . number_format($rps['aliquota'], 2, '.', '') . '</Aliquota>';
            $xml .= '<DescontoIncondicionado>' . number_format($rps['descontoIncondicionado'], 2, '.', '') . '</DescontoIncondicionado>';
            $xml .= '<DescontoCondicionado>' . number_format($rps['descontoCondicionado'], 2, '.', '') . '</DescontoCondicionado>';
            $xml .= '</Valores>';
            $xml .= '<ItemListaServico>' . $rps['itemListaServico'] . '</ItemListaServico>';
            $xml .= '<CodigoTributacaoMunicipio>' . $rps['codigoTributacaoMunicipio'] . '</CodigoTributacaoMunicipio>';
            $xml .= '<Discriminacao>' . htmlspecialchars($rps['discriminacao'], ENT_XML1) . '</Discriminacao>';
            $xml .= '<CodigoMunicipio>' . $rps['codigoMunicipio'] . '</CodigoMunicipio>';
            $xml .= '</Servico>';
            $xml .= '<Prestador>';
            $xml .= '<Cnpj>' . $dados['cnpjPrestador'] . '</Cnpj>';
            $xml .= '<InscricaoMunicipal>' . $dados['inscricaoMunicipal'] . '</InscricaoMunicipal>';
            $xml .= '</Prestador>';
            $xml .= '<Tomador>';
            $xml .= '<IdentificacaoTomador>';
            $xml .= '<CpfCnpj>';
            $xml .= '<Cnpj>' . $rps['tomador']['cpfCnpj'] . '</Cnpj>';
            $xml .= '</CpfCnpj>';
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