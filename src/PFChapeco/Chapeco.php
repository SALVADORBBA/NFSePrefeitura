<?php
namespace NFSePrefeitura\NFSe\PFChapeco;

use SoapClient;
use SoapFault;
use Exception;
/**
 * Classe Chapeco - Implementação do padrão ABRASF v2.04 para a Prefeitura de Chapecó - SC
 * Autor: [Seu Nome]
 * Esta classe faz parte da biblioteca NFSePrefeituras para integração com o padrão ABRASF versão 2.04.
 *
 * Observações:
 * - O XML gerado segue rigorosamente o layout ABRASF v2.04.
 * - Os namespaces utilizados são:
 *   - xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
 *   - xmlns:xsd="http://www.w3.org/2001/XMLSchema"
 *   - xmlns="http://www.abrasf.org.br/nfse.xsd"
 * - O método gerarXmlLoteRps NÃO inclui tags de assinatura digital, deixando o XML pronto para ser assinado externamente.
 * - Para detalhes do layout, consulte o Manual de Integração ABRASF v2.04.
 */
class Chapeco
{
    private const WSDL = 'https://chapeco.sc.gov.br/nfse/ws/nfse.asmx?WSDL'; // Atualize se necessário

    private string $certPath;
    private string $certPassword;

    public function __construct(string $certPath, string $certPassword)
    {
        $this->certPath     = $certPath;
        $this->certPassword = $certPassword;
    }

    private function client(): SoapClient
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
        return new SoapClient(self::WSDL, $options);
    }

    public function enviarLoteRps($xmlAssinado)
    {
        $client = $this->client();
        $params = [
            'xml' => $xmlAssinado
        ];
        try {
            $response = $client->__soapCall('RecepcionarLoteRps', [$params]);
            return $response;
        } catch (SoapFault $e) {
            throw new Exception('Erro ao enviar lote RPS: ' . $e->getMessage());
        }
    }

    public function gerarXmlLoteRps($dados)
    {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<EnviarLoteRpsEnvio xmlns="http://www.abrasf.org.br/nfse.xsd">';
        $xml .= '<LoteRps Id="lote' . $dados['lote_id'] . '" versao="2.04">';
        $xml .= '<NumeroLote>' . $dados['numeroLote'] . '</NumeroLote>';
        $xml .= '<Cnpj>' . $dados['cnpjPrestador'] . '</Cnpj>';
        $xml .= '<InscricaoMunicipal>' . $dados['inscricaoMunicipal'] . '</InscricaoMunicipal>';
        $xml .= '<QuantidadeRps>' . $dados['quantidadeRps'] . '</QuantidadeRps>';
        $xml .= '<ListaRps>';
        foreach ($dados['rps'] as $rps) {
            $xml .= '<Rps>';
            $xml .= '<InfDeclaracaoPrestacaoServico Id="rps' . $rps['numero'] . '">';
            $xml .= '<Rps>';
            $xml .= '<IdentificacaoRps>';
            $xml .= '<Numero>' . $rps['numero'] . '</Numero>';
            $xml .= '<Serie>' . $rps['serie'] . '</Serie>';
            $xml .= '<Tipo>' . $rps['tipo'] . '</Tipo>';
            $xml .= '</IdentificacaoRps>';
            $xml .= '<DataEmissao>' . $rps['dataEmissao'] . '</DataEmissao>';
            $xml .= '<Status>' . $rps['status'] . '</Status>';
            $xml .= '</Rps>';
            $xml .= '<Competencia>' . $rps['competencia'] . '</Competencia>';
            $xml .= '<Servico>';
            $xml .= '<Valores>';
            $xml .= '<ValorServicos>' . $rps['valorServicos'] . '</ValorServicos>';
            $xml .= '<ValorDeducoes>' . $rps['valorDeducoes'] . '</ValorDeducoes>';
            $xml .= '<ValorPis>' . $rps['valorPis'] . '</ValorPis>';
            $xml .= '<ValorCofins>' . $rps['valorCofins'] . '</ValorCofins>';
            $xml .= '<ValorInss>' . $rps['valorInss'] . '</ValorInss>';
            $xml .= '<ValorIr>' . $rps['valorIr'] . '</ValorIr>';
            $xml .= '<ValorCsll>' . $rps['valorCsll'] . '</ValorCsll>';
            $xml .= '<OutrasRetencoes>' . $rps['outrasRetencoes'] . '</OutrasRetencoes>';
            $xml .= '<ValTotTributos>' . $rps['valTotTributos'] . '</ValTotTributos>';
            $xml .= '<ValorIss>' . $rps['valorIss'] . '</ValorIss>';
            $xml .= '<Aliquota>0.00</Aliquota>';
            $xml .= '<DescontoIncondicionado>0.00</DescontoIncondicionado>';
            $xml .= '<DescontoCondicionado>0.00</DescontoCondicionado>';
            $xml .= '</Valores>';
            $xml .= '<IssRetido>' . $rps['issRetido'] . '</IssRetido>';
            $xml .= '<ResponsavelRetencao>1</ResponsavelRetencao>';
            $xml .= '<ItemListaServico>' . $rps['itemListaServico'] . '</ItemListaServico>';
            $xml .= '<CodigoCnae>' . $rps['codigoCnae'] . '</CodigoCnae>';
            $xml .= '<Discriminacao>' . $rps['discriminacao'] . '</Discriminacao>';
            $xml .= '<CodigoMunicipio>' . $rps['codigoMunicipio'] . '</CodigoMunicipio>';
            $xml .= '<cNBS>' . $rps['cNBS'] . '</cNBS>';
            $xml .= '<CodigoPais>1058</CodigoPais>';
            $xml .= '<ExigibilidadeISS>1</ExigibilidadeISS>';
            $xml .= '<MunicipioIncidencia>' . $rps['municipioIncidencia'] . '</MunicipioIncidencia>';
            $xml .= '<LocalidadeIncidencia>' . $rps['localidadeIncidencia'] . '</LocalidadeIncidencia>';
            $xml .= '</Servico>';
            $xml .= '<Prestador>';
            $xml .= '<CpfCnpj><Cnpj>' . $dados['cnpjPrestador'] . '</Cnpj></CpfCnpj>';
            $xml .= '<InscricaoMunicipal>' . $dados['inscricaoMunicipal'] . '</InscricaoMunicipal>';
            $xml .= '</Prestador>';
            $xml .= '<TomadorServico>';
            $xml .= '<IdentificacaoTomador>';
            $xml .= '<CpfCnpj><Cnpj>' . $rps['tomador']['cnpj'] . '</Cnpj></CpfCnpj>';
            $xml .= '</IdentificacaoTomador>';
            $xml .= '<RazaoSocial>' . $rps['tomador']['razaoSocial'] . '</RazaoSocial>';
            $xml .= '<Endereco>';
            $xml .= '<Endereco>' . $rps['tomador']['endereco'] . '</Endereco>';
            $xml .= '<Numero>' . $rps['tomador']['numero'] . '</Numero>';
            $xml .= '<Complemento>' . $rps['tomador']['complemento'] . '</Complemento>';
            $xml .= '<Bairro>' . $rps['tomador']['bairro'] . '</Bairro>';
            $xml .= '<CodigoMunicipio>' . $rps['tomador']['codigoMunicipio'] . '</CodigoMunicipio>';
            $xml .= '<Uf>' . $rps['tomador']['uf'] . '</Uf>';
            $xml .= '<Cep>' . $rps['tomador']['cep'] . '</Cep>';
            $xml .= '</Endereco>';
            $xml .= '<Contato>';
            $xml .= '<Telefone>' . $rps['tomador']['telefone'] . '</Telefone>';
            $xml .= '<Email>' . $rps['tomador']['email'] . '</Email>';
            $xml .= '</Contato>';
            $xml .= '</TomadorServico>';
            $xml .= '<RegimeEspecialTributacao>1</RegimeEspecialTributacao>';
            $xml .= '<OptanteSimplesNacional>1</OptanteSimplesNacional>';
            $xml .= '<IncentivoFiscal>2</IncentivoFiscal>';
            $xml .= '<InformacoesComplementares>nada</InformacoesComplementares>';
            if (isset($rps['IBSCBS'])) {
                $ibscbs = $rps['IBSCBS'];
                $xml .= '<IBSCBS>';
                $xml .= '<finNFSe>0</finNFSe>';
                $xml .= '<indFinal>0</indFinal>';
                $xml .= '<cIndOp>010100</cIndOp>';
                $xml .= '<indDest>0</indDest>';
                $xml .= '<valores>';
                $xml .= '<trib>';
                $xml .= '<gIBSCBS>';
                $xml .= '<CST>' . $ibscbs['CST'] . '</CST>';
                $xml .= '<cClassTrib>' . $ibscbs['cClassTrib'] . '</cClassTrib>';
                $xml .= '</gIBSCBS>';
                $xml .= '<vBC>' . $ibscbs['vBC'] . '</vBC>';
                $xml .= '<gIBSMun>';
                $xml .= '<pIBSMun>' . $ibscbs['pIBSMun'] . '</pIBSMun>';
                $xml .= '<pRedAliqIBSMU>0.00</pRedAliqIBSMU>';
                $xml .= '<vIBSMun>' . $ibscbs['vIBSMun'] . '</vIBSMun>';
                $xml .= '</gIBSMun>';
                $xml .= '<gIBSUF>';
                $xml .= '<pIBSUF>' . $ibscbs['pIBSUF'] . '</pIBSUF>';
                $xml .= '<pRedAliqIBSUF>0.00</pRedAliqIBSUF>';
                $xml .= '<vIBSUF>' . $ibscbs['vIBSUF'] . '</vIBSUF>';
                $xml .= '</gIBSUF>';
                $xml .= '<vIBSTot>' . $ibscbs['vIBSTot'] . '</vIBSTot>';
                $xml .= '<gCBS>';
                $xml .= '<pIBSCBS>' . $ibscbs['pIBSCBS'] . '</pIBSCBS>';
                $xml .= '<pRedAliqCBS>0.00</pRedAliqCBS>';
                $xml .= '<vIBSCBS>' . $ibscbs['vIBSCBS'] . '</vIBSCBS>';
                $xml .= '</gCBS>';
                $xml .= '</trib>';
                $xml .= '</valores>';
                $xml .= '</IBSCBS>';
            }
            $xml .= '</InfDeclaracaoPrestacaoServico>';
            $xml .= '</Rps>';
        }
        $xml .= '</ListaRps>';
        $xml .= '</LoteRps>';
        $xml .= '</EnviarLoteRpsEnvio>';
        return $xml;
    }
}