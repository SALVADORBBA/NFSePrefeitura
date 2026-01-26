<?php
namespace NFSePrefeitura\NFSe\PFChapeco;

use SoapClient;
use SoapFault;
use Exception;

/**
 * Classe Chapeco - Implementação do padrão ABRASF v2.04 para a Prefeitura de Chapecó - SC
 * Refatorada para melhor clareza, organização e manutenção.
 */
class Chapeco
{
    private const WSDL = 'https://chapeco.sc.gov.br/nfse/ws/nfse.asmx?WSDL';

    private string $certPath;
    private string $certPassword;

    /**
     * Construtor da classe
     */
    public function __construct(string $certPath, string $certPassword)
    {
        $this->certPath     = $certPath;
        $this->certPassword = $certPassword;
    }

    /**
     * Cria e retorna o SoapClient configurado
     */
    private function getSoapClient(): SoapClient
    {
        $options = [
            'local_cert'    => $this->certPath,
            'passphrase'    => $this->certPassword,
            'trace'         => 1,
            'exceptions'    => true,
            'cache_wsdl'    => WSDL_CACHE_NONE,
            'soap_version'  => SOAP_1_1,
            'encoding'      => 'UTF-8'
        ];
        return new SoapClient(self::WSDL, $options);
    }

    /**
     * Transmite o XML assinado para o WebService da prefeitura
     * Retorna apenas o XML puro de resposta
     */
    public function transmitirLoteRps(string $xmlAssinado): string
    {
        $client = $this->getSoapClient();
        $params = ['xml' => $xmlAssinado];
        try {
            $response = $client->__soapCall('RecepcionarLoteRps', [$params]);
            if (is_object($response) && isset($response->RecepcionarLoteRpsResponse->outputXML)) {
                return $response->RecepcionarLoteRpsResponse->outputXML;
            }
            return (string) $response;
        } catch (SoapFault $e) {
            throw new Exception('Erro ao transmitir lote RPS: ' . $e->getMessage());
        }
    }

    /**
     * Gera o XML do lote RPS conforme o padrão ABRASF v2.04
     */
    /**
     * Gera o XML do lote RPS conforme o modelo fornecido pelo usuário (sem LoteRps)
     */
    public function gerarXmlLoteRps(array $dados): string
    {
        $rps = $dados['rps'][0]; // Considerando apenas um RPS conforme modelo
        $xml  = '<?xml version="1.0" encoding="utf-8"?>';
        $xml .= '<GerarNfseEnvio xmlns="http://www.abrasf.org.br/nfse.xsd">';
        $xml .= '<Rps>';
        $xml .= '<InfDeclaracaoPrestacaoServico>';
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
        $xml .= '<Aliquota>' . $rps['aliquota'] . '</Aliquota>';
        // Garantir valor válido para DescontoIncondicionado
        $valorDescontoIncondicionado = isset($rps['descontoIncondicionado']) && is_numeric($rps['descontoIncondicionado']) && $rps['descontoIncondicionado'] !== ''
            ? number_format((float)$rps['descontoIncondicionado'], 2, '.', '')
            : '0.00';
        $xml .= '<DescontoIncondicionado>' . $valorDescontoIncondicionado . '</DescontoIncondicionado>';
        // Garantir valor válido para DescontoCondicionado
        $valorDescontoCondicionado = isset($rps['descontoCondicionado']) && is_numeric($rps['descontoCondicionado']) && $rps['descontoCondicionado'] !== ''
            ? number_format((float)$rps['descontoCondicionado'], 2, '.', '')
            : '0.00';
        $xml .= '<DescontoCondicionado>' . $valorDescontoCondicionado . '</DescontoCondicionado>';
        $xml .= '</Valores>';
        $xml .= '<IssRetido>' . $rps['issRetido'] . '</IssRetido>';
        $xml .= '<ResponsavelRetencao>' . $rps['responsavelRetencao'] . '</ResponsavelRetencao>';
        $xml .= '<ItemListaServico>' . $rps['itemListaServico'] . '</ItemListaServico>';
        $xml .= '<CodigoCnae>' . $rps['codigoCnae'] . '</CodigoCnae>';
        $xml .= '<Discriminacao>' . $rps['discriminacao'] . '</Discriminacao>';
        $xml .= '<CodigoMunicipio>' . $rps['codigoMunicipio'] . '</CodigoMunicipio>';
        $xml .= '<CodigoPais>' . $rps['codigoPais'] . '</CodigoPais>';
        $xml .= '<ExigibilidadeISS>' . $rps['exigibilidadeISS'] . '</ExigibilidadeISS>';
        $xml .= '<MunicipioIncidencia>' . $rps['municipioIncidencia'] . '</MunicipioIncidencia>';
        $xml .= '<cNBS>' . $rps['cNBS'] . '</cNBS>';
        $xml .= '</Servico>';
        $xml .= '<Prestador>';
        $xml .= '<CpfCnpj><Cnpj>' . $dados['cnpjPrestador'] . '</Cnpj></CpfCnpj>';
        $xml .= '<InscricaoMunicipal>' . $dados['inscricaoMunicipal'] . '</InscricaoMunicipal>';
        $xml .= '</Prestador>';
        $xml .= '<TomadorServico>';
        $xml .= '<IdentificacaoTomador>';
        $docTomador = '';
        $tipoDoc = '';
        if (isset($rps['tomador']['cnpj'])) {
            if (strlen($rps['tomador']['cnpj']) === 11) {
                $docTomador = $rps['tomador']['cnpj'];
                $tipoDoc = 'cpf';
            } elseif (strlen($rps['tomador']['cnpj']) === 14) {
                $docTomador = $rps['tomador']['cnpj'];
                $tipoDoc = 'cnpj';
            }
        } elseif (isset($rps['tomador']['cpf']) && strlen($rps['tomador']['cpf']) === 11) {
            $docTomador = $rps['tomador']['cpf'];
            $tipoDoc = 'cpf';
        } elseif (isset($rps['tomador']['documento'])) {
            if (strlen($rps['tomador']['documento']) === 11) {
                $docTomador = $rps['tomador']['documento'];
                $tipoDoc = 'cpf';
            } elseif (strlen($rps['tomador']['documento']) === 14) {
                $docTomador = $rps['tomador']['documento'];
                $tipoDoc = 'cnpj';
            }
        }
        if ($tipoDoc === 'cpf') {
            $xml .= '<CpfCnpj><Cpf>' . $docTomador . '</Cpf></CpfCnpj>';
        } elseif ($tipoDoc === 'cnpj') {
            $xml .= '<CpfCnpj><Cnpj>' . $docTomador . '</Cnpj></CpfCnpj>';
        } else {
            $xml .= '<CpfCnpj></CpfCnpj>'; // Documento inválido ou ausente
        }
        $xml .= '</IdentificacaoTomador>';
        $xml .= '<RazaoSocial>' . $rps['tomador']['razaoSocial'] . '</RazaoSocial>';
        $xml .= '<Endereco>';
        $xml .= '<Endereco>' . $rps['tomador']['endereco'] . '</Endereco>';
        $xml .= '<Numero>' . $rps['tomador']['numero'] . '</Numero>';
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
        $xml .= '<RegimeEspecialTributacao>' . $rps['regimeEspecialTributacao'] . '</RegimeEspecialTributacao>';
        $xml .= '<OptanteSimplesNacional>' . $rps['optanteSimplesNacional'] . '</OptanteSimplesNacional>';
        $xml .= '<IncentivoFiscal>' . $rps['incentivoFiscal'] . '</IncentivoFiscal>';
        $xml .= '<InformacoesComplementares>' . $rps['informacoesComplementares'] . '</InformacoesComplementares>';
        if (isset($rps['IBSCBS'])) {
            // Removido conforme solicitado: não gerar bloco IBSCBS
        }
        $xml .= '</InfDeclaracaoPrestacaoServico>';
        $xml .= '</Rps>';
        $xml .= '</GerarNfseEnvio>';
        return $xml;
    }
}