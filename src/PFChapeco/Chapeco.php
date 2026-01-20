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
        $xml .= '<GerarNfseEnvio xmlns="http://www.abrasf.org.br/nfse.xsd">';
        
        foreach ($dados['rps'] as $rps) {
            $xml .= '<Rps>';
            $xml .= '<InfDeclaracaoPrestacaoServico>';
            $xml .= '<Rps>';
            $xml .= '<IdentificacaoRps>';
            $xml .= '<Numero>' . $rps['infRps']['numero'] . '</Numero>';
            $xml .= '<Serie>' . $rps['infRps']['serie'] . '</Serie>';
            $xml .= '<Tipo>' . $rps['infRps']['tipo'] . '</Tipo>';
            $xml .= '</IdentificacaoRps>';
            $xml .= '<DataEmissao>' . $rps['infRps']['dataEmissao'] . '</DataEmissao>';
            $xml .= '<Status>' . ($rps['status'] ?? '1') . '</Status>';
            $xml .= '</Rps>';
            $xml .= '<Competencia>' . $rps['competencia'] . '</Competencia>';
            
            $xml .= '<Servico>';
            $xml .= '<Valores>';
            $xml .= '<ValorServicos>' . $rps['valorServicos'] . '</ValorServicos>';
            $xml .= '<ValorDeducoes>' . ($rps['valorDeducoes'] ?? '0') . '</ValorDeducoes>';
            $xml .= '<ValorPis>' . ($rps['valorPis'] ?? '0') . '</ValorPis>';
            $xml .= '<ValorCofins>' . ($rps['valorCofins'] ?? '0') . '</ValorCofins>';
            $xml .= '<ValorInss>' . ($rps['valorInss'] ?? '0') . '</ValorInss>';
            $xml .= '<ValorIr>' . ($rps['valorIr'] ?? '0') . '</ValorIr>';
            $xml .= '<ValorCsll>' . ($rps['valorCsll'] ?? '0') . '</ValorCsll>';
            $xml .= '<OutrasRetencoes>' . ($rps['outrasRetencoes'] ?? '0') . '</OutrasRetencoes>';
            $xml .= '<ValTotTributos>' . ($rps['valTotTributos'] ?? '0') . '</ValTotTributos>';
            $xml .= '<ValorIss>' . $rps['valorIss'] . '</ValorIss>';
            $xml .= '<Aliquota>' . $rps['aliquota'] . '</Aliquota>';
            $xml .= '<DescontoIncondicionado>' . ($rps['descontoIncondicionado'] ?? '0') . '</DescontoIncondicionado>';
            $xml .= '<DescontoCondicionado>' . ($rps['descontoCondicionado'] ?? '0') . '</DescontoCondicionado>';
            $xml .= '</Valores>';
            $xml .= '<IssRetido>' . $rps['issRetido'] . '</IssRetido>';
            $xml .= '<ResponsavelRetencao>' . ($rps['responsavelRetencao'] ?? '1') . '</ResponsavelRetencao>';
            $xml .= '<ItemListaServico>' . $rps['itemListaServico'] . '</ItemListaServico>';
            $xml .= '<CodigoCnae>' . ($rps['codigoCnae'] ?? '') . '</CodigoCnae>';
            $xml .= '<Discriminacao>' . htmlspecialchars($rps['discriminacao'], ENT_XML1) . '</Discriminacao>';
            $xml .= '<CodigoMunicipio>' . $rps['codigoMunicipio'] . '</CodigoMunicipio>';
            $xml .= '<CodigoPais>' . ($rps['codigoPais'] ?? '1058') . '</CodigoPais>';
            $xml .= '<ExigibilidadeISS>' . $rps['exigibilidadeISS'] . '</ExigibilidadeISS>';
            $xml .= '<MunicipioIncidencia>' . ($rps['municipioIncidencia'] ?? $rps['codigoMunicipio']) . '</MunicipioIncidencia>';
            $xml .= '<cNBS>' . ($rps['cNBS'] ?? '') . '</cNBS>';
            $xml .= '</Servico>';
            $xml .= '<Prestador>';
            $xml .= '<CpfCnpj><Cnpj>' . $dados['cnpjPrestador'] . '</Cnpj></CpfCnpj>';
            $xml .= '<InscricaoMunicipal>' . $dados['inscricaoMunicipal'] . '</InscricaoMunicipal>';
            $xml .= '</Prestador>';
            $xml .= '<TomadorServico>';
            $xml .= '<IdentificacaoTomador>';
            $xml .= '<CpfCnpj><Cnpj>' . $rps['tomador']['cpfCnpj'] . '</Cnpj></CpfCnpj>';
            $xml .= '</IdentificacaoTomador>';
            $xml .= '<RazaoSocial>' . htmlspecialchars($rps['tomador']['razaoSocial'], ENT_XML1) . '</RazaoSocial>';
            $xml .= '<Endereco>';
            $xml .= '<Endereco>' . htmlspecialchars($rps['tomador']['endereco']['logradouro'], ENT_XML1) . '</Endereco>';
            $xml .= '<Numero>' . $rps['tomador']['endereco']['numero'] . '</Numero>';
            $xml .= '<Complemento>' . ($rps['tomador']['endereco']['complemento'] ?? '') . '</Complemento>';
            $xml .= '<Bairro>' . htmlspecialchars($rps['tomador']['endereco']['bairro'], ENT_XML1) . '</Bairro>';
            $xml .= '<CodigoMunicipio>' . $rps['tomador']['endereco']['codigoMunicipio'] . '</CodigoMunicipio>';
            $xml .= '<Uf>' . $rps['tomador']['endereco']['uf'] . '</Uf>';
            $xml .= '<Cep>' . $rps['tomador']['endereco']['cep'] . '</Cep>';
            $xml .= '</Endereco>';
            $xml .= '<Contato>';
            $xml .= '<Telefone>' . $rps['tomador']['telefone'] . '</Telefone>';
            $xml .= '<Email>' . htmlspecialchars($rps['tomador']['email'], ENT_XML1) . '</Email>';
            $xml .= '</Contato>';
            $xml .= '</TomadorServico>';
            $xml .= '<RegimeEspecialTributacao>' . $rps['regimeEspecialTributacao'] . '</RegimeEspecialTributacao>';
            $xml .= '<OptanteSimplesNacional>' . $rps['optanteSimplesNacional'] . '</OptanteSimplesNacional>';
            $xml .= '<IncentivoFiscal>' . $rps['incentivoFiscal'] . '</IncentivoFiscal>';
            $xml .= '<InformacoesComplementares>' . ($rps['informacoesComplementares'] ?? '') . '</InformacoesComplementares>';
            // IBSCBS campos opcionais
            if (isset($rps['IBSCBS'])) {
                $ibscbs = $rps['IBSCBS'];
                $xml .= '<IBSCBS>';
                $xml .= '<cLocalidadeIncid>' . ($ibscbs['cLocalidadeIncid'] ?? '') . '</cLocalidadeIncid>';
                $xml .= '<xLocalidadeIncid>' . ($ibscbs['xLocalidadeIncid'] ?? '') . '</xLocalidadeIncid>';
                $xml .= '<valores>';
                $xml .= '<vBC>' . ($ibscbs['valores']['vBC'] ?? '0.00') . '</vBC>';
                $xml .= '<uf>';
                $xml .= '<pIBSUF>' . ($ibscbs['valores']['uf']['pIBSUF'] ?? '0.00') . '</pIBSUF>';
                $xml .= '<pAliqEfetUF>' . ($ibscbs['valores']['uf']['pAliqEfetUF'] ?? '0.00') . '</pAliqEfetUF>';
                $xml .= '</uf>';
                $xml .= '<mun>';
                $xml .= '<pIBSMun>' . ($ibscbs['valores']['mun']['pIBSMun'] ?? '0.00') . '</pIBSMun>';
                $xml .= '<pAliqEfetMun>' . ($ibscbs['valores']['mun']['pAliqEfetMun'] ?? '0.00') . '</pAliqEfetMun>';
                $xml .= '</mun>';
                $xml .= '<fed>';
                $xml .= '<pCBS>' . ($ibscbs['valores']['fed']['pCBS'] ?? '0.00') . '</pCBS>';
                $xml .= '<pAliqEfetCBS>' . ($ibscbs['valores']['fed']['pAliqEfetCBS'] ?? '0.00') . '</pAliqEfetCBS>';
                $xml .= '</fed>';
                $xml .= '</valores>';
                $xml .= '<totCIBS>';
                $xml .= '<vTotNF>' . ($ibscbs['totCIBS']['vTotNF'] ?? '0.00') . '</vTotNF>';
                $xml .= '<gIBS>';
                $xml .= '<vIBSTot>' . ($ibscbs['totCIBS']['gIBS']['vIBSTot'] ?? '0.00') . '</vIBSTot>';
                $xml .= '<gIBSUFTot>';
                $xml .= '<vIBSUF>' . ($ibscbs['totCIBS']['gIBS']['gIBSUFTot']['vIBSUF'] ?? '0.00') . '</vIBSUF>';
                $xml .= '</gIBSUFTot>';
                $xml .= '<gIBSMunTot>';
                $xml .= '<vIBSMun>' . ($ibscbs['totCIBS']['gIBS']['gIBSMunTot']['vIBSMun'] ?? '0.00') . '</vIBSMun>';
                $xml .= '</gIBSMunTot>';
                $xml .= '</gIBS>';
                $xml .= '<gCBS>';
                $xml .= '<vCBS>' . ($ibscbs['totCIBS']['gCBS']['vCBS'] ?? '0.00') . '</vCBS>';
                $xml .= '</gCBS>';
                $xml .= '</totCIBS>';
                $xml .= '</IBSCBS>';
            }
            $xml .= '</InfDeclaracaoPrestacaoServico>';
            $xml .= '</Rps>';
        }
        $xml .= '</Rps>';
        $xml .= '</GerarNfseEnvio>';
        return $xml;
    }
}