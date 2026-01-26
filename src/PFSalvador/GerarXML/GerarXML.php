<?php

namespace NFSePrefeitura\NFSe\GerarXML;

class GerarXML
{
    /**
     * Gera o XML do lote RPS conforme o padrão ABRASF
     */
    public function gerarXml(array $dados): string
    {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<EnviarLoteRpsEnvio xmlns="http://www.abrasf.org.br/nfse.xsd">';
        $xml .= '<LoteRps Id="lote' . $dados['lote_id'] . '" versao="2.04">';
        $xml .= '<NumeroLote>' . $dados['numeroLote'] . '</NumeroLote>';
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
            $xml .= '</InfDeclaracaoPrestacaoServico>';
            $xml .= '</Rps>';
        }
        $xml .= '</ListaRps>';
        $xml .= '</LoteRps>';
        $xml .= '</EnviarLoteRpsEnvio>';
        return $xml;
    }
}
