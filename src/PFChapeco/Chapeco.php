<?php
namespace NFSePrefeitura\NFSe\PFChapeco;

use SoapClient;
use SoapFault;
use Exception;
use NFSePrefeitura\NFSe\PFChapeco\AssinaturaChapeco;

/**
 * Classe Chapeco - Implementação do padrão ABRASF v2.04 para a Prefeitura de Chapecó - SC
 * Refatorada para melhor clareza, organização e manutenção.
 */
class Chapeco
{
    private const WSDL = 'https://chapeco.meumunicipio.online/abrasf/ws/nfs?wsdl';

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
     * Gera, assina e transmite o lote RPS completo
     * Retorna array com XML gerado, assinado e resposta
     */
    public function gerarAssinarTransmitirLoteRps(array $dadosLote): array
    {
        // Gera XML
        $xmlGerado = $this->gerarXmlLoteRps($dadosLote);
        
        // Assina XML
        $assinador = new AssinaturaChapeco($this->certPath, $this->certPassword);
        $xmlAssinado = $assinador->assinarLoteRps($xmlGerado);
        
        // Transmite XML
        $xmlResposta = $this->transmitirLoteRps($xmlAssinado);
        
        return [
            'xml_gerado' => $xmlGerado,
            'xml_assinado' => $xmlAssinado,
            'xml_resposta' => $xmlResposta
        ];
    }

    /**
     * Gera o XML do lote RPS conforme o modelo fornecido (estrutura 100% igual)
     */
    public function gerarXmlLoteRps(array $dados): string
    {
        $xml = '<?xml version="1.0" encoding="utf-8"?>';
        $xml .= '<EnviarLoteRpsEnvio xmlns="http://www.abrasf.org.br/nfse.xsd">';
        $xml .= '<LoteRps versao="2.04">';
        $xml .= '<NumeroLote>' . $dados['numeroLote'] . '</NumeroLote>';
        $xml .= '<Prestador>';
        $xml .= '<CpfCnpj>';
        $xml .= '<Cnpj>' . $dados['cnpjPrestador'] . '</Cnpj>';
        $xml .= '</CpfCnpj>';
        $xml .= '<InscricaoMunicipal>' . $dados['inscricaoMunicipal'] . '</InscricaoMunicipal>';
        $xml .= '</Prestador>';
        $xml .= '<QuantidadeRps>' . $dados['quantidadeRps'] . '</QuantidadeRps>';
        $xml .= '<ListaRps>';
        
        // Processa cada RPS
        foreach ($dados['rps'] as $rps) {
            $xml .= '<Rps>';
            $xml .= '<InfDeclaracaoPrestacaoServico Id="' . $rps['numero'] . '">';
            
            // Dados do RPS
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
            
            // Serviço
            $xml .= '<Servico>';
            $xml .= '<Valores>';
            $xml .= '<ValorServicos>' . number_format($rps['valorServicos'], 2, '.', '') . '</ValorServicos>';
            $xml .= '<ValorDeducoes>' . number_format($rps['valorDeducoes'], 2, '.', '') . '</ValorDeducoes>';
            $xml .= '<ValorPis>' . number_format($rps['valorPis'], 2, '.', '') . '</ValorPis>';
            $xml .= '<ValorCofins>' . number_format($rps['valorCofins'], 2, '.', '') . '</ValorCofins>';
            $xml .= '<ValorInss>' . number_format($rps['valorInss'], 2, '.', '') . '</ValorInss>';
            $xml .= '<ValorIr>' . number_format($rps['valorIr'], 2, '.', '') . '</ValorIr>';
            $xml .= '<ValorCsll>' . number_format($rps['valorCsll'], 2, '.', '') . '</ValorCsll>';
            $xml .= '<OutrasRetencoes>' . number_format($rps['outrasRetencoes'], 2, '.', '') . '</OutrasRetencoes>';
            $xml .= '<ValTotTributos>' . number_format($rps['valTotTributos'], 2, '.', '') . '</ValTotTributos>';
            $xml .= '<ValorIss>' . number_format($rps['valorIss'], 2, '.', '') . '</ValorIss>';
            $xml .= '<Aliquota>' . number_format($rps['aliquota'], 2, '.', '') . '</Aliquota>';
            
            $descontoIncondicionado = isset($rps['descontoIncondicionado']) && is_numeric($rps['descontoIncondicionado']) 
                ? number_format((float)$rps['descontoIncondicionado'], 2, '.', '') : '0.00';
            $xml .= '<DescontoIncondicionado>' . $descontoIncondicionado . '</DescontoIncondicionado>';
            
            $descontoCondicionado = isset($rps['descontoCondicionado']) && is_numeric($rps['descontoCondicionado']) 
                ? number_format((float)$rps['descontoCondicionado'], 2, '.', '') : '0.00';
            $xml .= '<DescontoCondicionado>' . $descontoCondicionado . '</DescontoCondicionado>';
            
            $xml .= '</Valores>';
            
            $xml .= '<IssRetido>' . $rps['issRetido'] . '</IssRetido>';
            $xml .= '<ResponsavelRetencao>' . $rps['responsavelRetencao'] . '</ResponsavelRetencao>';
            $xml .= '<ItemListaServico>' . $rps['itemListaServico'] . '</ItemListaServico>';
            $xml .= '<CodigoCnae>' . $rps['codigoCnae'] . '</CodigoCnae>';
            $xml .= '<Discriminacao>' . htmlspecialchars($rps['discriminacao']) . '</Discriminacao>';
            $xml .= '<CodigoMunicipio>' . $rps['codigoMunicipio'] . '</CodigoMunicipio>';
            $xml .= '<CodigoPais>' . $rps['codigoPais'] . '</CodigoPais>';
            $xml .= '<ExigibilidadeISS>' . $rps['exigibilidadeISS'] . '</ExigibilidadeISS>';
            $xml .= '<MunicipioIncidencia>' . $rps['municipioIncidencia'] . '</MunicipioIncidencia>';
            $xml .= '<cNBS>' . $rps['cNBS'] . '</cNBS>';
            $xml .= '</Servico>';
            
            // Prestador
            $xml .= '<Prestador>';
            $xml .= '<CpfCnpj>';
            $xml .= '<Cnpj>' . $dados['cnpjPrestador'] . '</Cnpj>';
            $xml .= '</CpfCnpj>';
            $xml .= '<InscricaoMunicipal>' . $dados['inscricaoMunicipal'] . '</InscricaoMunicipal>';
            $xml .= '</Prestador>';
            
            // Tomador
            $xml .= '<TomadorServico>';
            $xml .= '<IdentificacaoTomador>';
            $xml .= '<CpfCnpj>';
            if (strlen($rps['tomador']['cnpj']) <= 11) {
                $xml .= '<Cpf>' . $rps['tomador']['cnpj'] . '</Cpf>';
            } else {
                $xml .= '<Cnpj>' . $rps['tomador']['cnpj'] . '</Cnpj>';
            }
            $xml .= '</CpfCnpj>';
            $xml .= '</IdentificacaoTomador>';
            $xml .= '<RazaoSocial>' . htmlspecialchars($rps['tomador']['razaoSocial']) . '</RazaoSocial>';
            $xml .= '<Endereco>';
            $xml .= '<Endereco>' . htmlspecialchars($rps['tomador']['endereco']) . '</Endereco>';
            $xml .= '<Numero>' . $rps['tomador']['numero'] . '</Numero>';
            if (!empty($rps['tomador']['complemento'])) {
                $xml .= '<Complemento>' . htmlspecialchars($rps['tomador']['complemento']) . '</Complemento>';
            }
            $xml .= '<Bairro>' . htmlspecialchars($rps['tomador']['bairro']) . '</Bairro>';
            $xml .= '<CodigoMunicipio>' . $rps['tomador']['codigoMunicipio'] . '</CodigoMunicipio>';
            $xml .= '<Uf>' . $rps['tomador']['uf'] . '</Uf>';
            $xml .= '<Cep>' . $rps['tomador']['cep'] . '</Cep>';
            $xml .= '</Endereco>';
            if (!empty($rps['tomador']['telefone']) || !empty($rps['tomador']['email'])) {
                $xml .= '<Contato>';
                if (!empty($rps['tomador']['telefone'])) {
                    $xml .= '<Telefone>' . $rps['tomador']['telefone'] . '</Telefone>';
                }
                if (!empty($rps['tomador']['email'])) {
                    $xml .= '<Email>' . htmlspecialchars($rps['tomador']['email']) . '</Email>';
                }
                $xml .= '</Contato>';
            }
            $xml .= '</TomadorServico>';
            
            if (isset($rps['regimeEspecialTributacao'])) {
                $xml .= '<RegimeEspecialTributacao>' . $rps['regimeEspecialTributacao'] . '</RegimeEspecialTributacao>';
            }
            if (isset($rps['optanteSimplesNacional'])) {
                $xml .= '<OptanteSimplesNacional>' . $rps['optanteSimplesNacional'] . '</OptanteSimplesNacional>';
            }
     
                $xml .= '<IncentivoFiscal>' . $rps['incentivoFiscal']   . '</IncentivoFiscal>';
        
            if (isset($rps['informacoesComplementares'])) {
                $xml .= '<InformacoesComplementares>' . htmlspecialchars($rps['informacoesComplementares']) . '</InformacoesComplementares>';
            }
            
            // IBSCBS (opcional)
            if (isset($rps['IBSCBS'])) {
                $ibscbs = $rps['IBSCBS'];
                $xml .= '<IBSCBS>';
                $xml .= '<cLocalidadeIncid>' . ($ibscbs['cLocalidadeIncid'] ?? '') . '</cLocalidadeIncid>';
                $xml .= '<xLocalidadeIncid>' . ($ibscbs['xLocalidadeIncid'] ?? '') . '</xLocalidadeIncid>';
                if (isset($ibscbs['valores'])) {
                    $xml .= '<valores>';
                    $xml .= '<vBC>' . number_format((float)($ibscbs['valores']['vBC'] ?? 0), 2, '.', '') . '</vBC>';
                    if (isset($ibscbs['valores']['uf'])) {
                        $xml .= '<uf>';
                        $xml .= '<pIBSUF>' . number_format((float)($ibscbs['valores']['uf']['pIBSUF'] ?? 0), 2, '.', '') . '</pIBSUF>';
                        $xml .= '<pAliqEfetUF>' . number_format((float)($ibscbs['valores']['uf']['pAliqEfetUF'] ?? 0), 2, '.', '') . '</pAliqEfetUF>';
                        $xml .= '</uf>';
                    }
                    if (isset($ibscbs['valores']['mun'])) {
                        $xml .= '<mun>';
                        $xml .= '<pIBSMun>' . number_format((float)($ibscbs['valores']['mun']['pIBSMun'] ?? 0), 2, '.', '') . '</pIBSMun>';
                        $xml .= '<pAliqEfetMun>' . number_format((float)($ibscbs['valores']['mun']['pAliqEfetMun'] ?? 0), 2, '.', '') . '</pAliqEfetMun>';
                        $xml .= '</mun>';
                    }
                    if (isset($ibscbs['valores']['fed'])) {
                        $xml .= '<fed>';
                        $xml .= '<pCBS>' . number_format((float)($ibscbs['valores']['fed']['pCBS'] ?? 0), 2, '.', '') . '</pCBS>';
                        $xml .= '<pAliqEfetCBS>' . number_format((float)($ibscbs['valores']['fed']['pAliqEfetCBS'] ?? 0), 2, '.', '') . '</pAliqEfetCBS>';
                        $xml .= '</fed>';
                    }
                    $xml .= '</valores>';
                }
                if (isset($ibscbs['totCIBS'])) {
                    $xml .= '<totCIBS>';
                    $xml .= '<vTotNF>' . number_format((float)($ibscbs['totCIBS']['vTotNF'] ?? 0), 2, '.', '') . '</vTotNF>';
                    if (isset($ibscbs['totCIBS']['gIBS'])) {
                        $xml .= '<gIBS>';
                        $xml .= '<vIBSTot>' . number_format((float)($ibscbs['totCIBS']['gIBS']['vIBSTot'] ?? 0), 2, '.', '') . '</vIBSTot>';
                        if (isset($ibscbs['totCIBS']['gIBS']['gIBSUFTot'])) {
                            $xml .= '<gIBSUFTot>';
                            $xml .= '<vIBSUF>' . number_format((float)($ibscbs['totCIBS']['gIBS']['gIBSUFTot']['vIBSUF'] ?? 0), 2, '.', '') . '</vIBSUF>';
                            $xml .= '</gIBSUFTot>';
                        }
                        if (isset($ibscbs['totCIBS']['gIBS']['gIBSMunTot'])) {
                            $xml .= '<gIBSMunTot>';
                            $xml .= '<vIBSMun>' . number_format((float)($ibscbs['totCIBS']['gIBS']['gIBSMunTot']['vIBSMun'] ?? 0), 2, '.', '') . '</vIBSMun>';
                            $xml .= '</gIBSMunTot>';
                        }
                        $xml .= '</gIBS>';
                    }
                    if (isset($ibscbs['totCIBS']['gCBS'])) {
                        $xml .= '<gCBS>';
                        $xml .= '<vCBS>' . number_format((float)($ibscbs['totCIBS']['gCBS']['vCBS'] ?? 0), 2, '.', '') . '</vCBS>';
                        $xml .= '</gCBS>';
                    }
                    $xml .= '</totCIBS>';
                }
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
 

    public function assinarRpsELote(string $xml): string 
    { 
        $this->loadDom($xml); 
        $this->removeAllSignatures();

        // Remove o atributo Id de todos os InfDeclaracaoPrestacaoServico antes de assinar
        $infList = $this->xpath->query('//*[local-name()="InfDeclaracaoPrestacaoServico"]');
        if ($infList) {
            foreach ($infList as $node) {
                if ($node instanceof DOMElement && $node->hasAttribute('Id')) {
                    $node->removeAttribute('Id');
                }
            }
        }

        // 1) Assina todos os RPS (InfDeclaracaoPrestacaoServico)
        $infList = $this->xpath->query('//*[local-name()="InfDeclaracaoPrestacaoServico"]');
        if ($infList) {
            foreach ($infList as $node) {
                if (!$node instanceof DOMElement) {
                    continue;
                }

                $id = $this->ensureId($node, 'Rps');

                $parentRps = $node->parentNode;
                if ($parentRps instanceof DOMElement && $parentRps->localName === 'Rps') {
                    // Cria a assinatura para o InfDeclaracaoPrestacaoServico
                    $sigNode = $this->createSignatureNodeForElement($node, $id);
                    // Insere a assinatura como irmã, logo após o InfDeclaracaoPrestacaoServico, dentro de <Rps>
                    $parentRps->insertBefore($sigNode, $node->nextSibling);
                }
            }
        }

        // 2) Assina o LoteRps (primeiro)
        $loteNodes = $this->xpath->query('//*[local-name()="LoteRps"]');
        if ($loteNodes && $loteNodes->length > 0) {
            $lote = $loteNodes->item(0);
            if ($lote instanceof DOMElement) {
                $id = $this->ensureId($lote, 'Lote');
                $sigNode = $this->createSignatureNodeForElement($lote, $id);
                $this->insertAfter($sigNode, $lote);
            }
        }

        return $this->dom->saveXML();
    }}