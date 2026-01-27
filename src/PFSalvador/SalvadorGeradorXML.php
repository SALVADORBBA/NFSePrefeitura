<?php

namespace NFSePrefeitura\NFSe\PFSalvador;

/**
 * Classe Geradora de XML para NFS-e de Salvador - BA
 * Segue o padrão ABRASF v2.04
 */
class SalvadorGeradorXML
{
    /**
     * Remove BOM, espaços e quebras de linha antes da declaração XML
     */
    private function sanitizeXmlDeclaration(string $xml): string
    {
        // Remove BOM
        $xml = preg_replace('/^\xEF\xBB\xBF/', '', $xml);
        // Remove espaços e quebras de linha antes da declaração
        $xml = preg_replace('/^[\s\n\r]+(<\?xml)/', '$1', $xml);
        return $xml;
    }

    /**
     * Gera o XML do lote RPS conforme padrão ABRASF para Salvador-BA
     */
    public function gerarXmlLoteRps(array $dados): string
    {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<EnviarLoteRpsEnvio xmlns="http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd">';
        $xml .= '<LoteRps Id="lote:' . htmlspecialchars($dados['lote_id'] ?? '') . '" versao="2.04">';
        $xml .= '<NumeroLote>' . htmlspecialchars($dados['numeroLote'] ?? '') . '</NumeroLote>';
        $xml .= '<Cnpj>' . htmlspecialchars($dados['cnpjPrestador'] ?? '') . '</Cnpj>';
        $xml .= '<InscricaoMunicipal>' . htmlspecialchars($dados['inscricaoMunicipal'] ?? '') . '</InscricaoMunicipal>';
        $xml .= '<QuantidadeRps>' . htmlspecialchars($dados['quantidadeRps'] ?? '') . '</QuantidadeRps>';
        $xml .= '<ListaRps>';
        
        // Processa cada RPS
        foreach ($dados['rps'] as $rps) {
            $xml .= $this->buildRps($rps);
        }
        
        $xml .= '</ListaRps>';
        $xml .= '</LoteRps>';
        $xml .= '</EnviarLoteRpsEnvio>';
        
        return $this->sanitizeXmlDeclaration($xml);
    }

    /**
     * Gera o XML de NFSe individual conforme padrão ABRASF para Salvador-BA
     */
    public function gerarXmlNfse(array $dados): string
    {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<CompNfse xmlns="http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd">';
        $xml .= '<Nfse xmlns="http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd">';
        $xml .= $this->buildInfNfse($dados);
        $xml .= '</Nfse>';
        $xml .= '</CompNfse>';
        
        return $this->sanitizeXmlDeclaration($xml);
    }

    /**
     * Constrói a estrutura InfNfse
     */
    private function buildInfNfse(array $d): string
    {
        $xml  = '<InfNfse Id="' . htmlspecialchars($d['inf_id'] ?? '') . '">';
        $xml .= '<Numero>' . htmlspecialchars($d['numero'] ?? '') . '</Numero>';
        $xml .= '<CodigoVerificacao>' . htmlspecialchars($d['codigoVerificacao'] ?? '') . '</CodigoVerificacao>';
        $xml .= '<DataEmissao>' . htmlspecialchars($d['dataEmissao'] ?? '') . '</DataEmissao>';
        $xml .= '<NaturezaOperacao>' . htmlspecialchars($d['naturezaOperacao'] ?? '') . '</NaturezaOperacao>';
        $xml .= '<OptanteSimplesNacional>' . htmlspecialchars($d['optanteSimplesNacional'] ?? '') . '</OptanteSimplesNacional>';
        $xml .= '<IncentivadorCultural>' . htmlspecialchars($d['incentivadorCultural'] ?? '') . '</IncentivadorCultural>';
        $xml .= '<Competencia>' . htmlspecialchars($d['competencia'] ?? '') . '</Competencia>';
        $xml .= $this->buildServico($d['servico'] ?? []);
        $xml .= $this->buildPrestadorServico($d['prestadorServico'] ?? []);
        $xml .= $this->buildTomadorServico($d['tomadorServico'] ?? []);
        $xml .= $this->buildOrgaoGerador($d['orgaoGerador'] ?? []);
        
        // Assinatura pode ser incluída aqui
        if (!empty($d['signature'])) {
            $xml .= $d['signature'];
        }
        
        $xml .= '</InfNfse>';
        return $xml;
    }

    /**
     * Constrói a estrutura de RPS para lote
     */
    private function buildRps(array $rps): string
    {
        $xml  = '<Rps>';
        $xml .= '<InfDeclaracaoPrestacaoServico Id="rps:' . htmlspecialchars($rps['inf_id'] ?? '') . '">';
        
        // Dados do RPS
        $xml .= '<Rps>';
        $xml .= '<IdentificacaoRps>';
        $xml .= '<Numero>' . htmlspecialchars($rps['infRps']['numero'] ?? '') . '</Numero>';
        $xml .= '<Serie>' . htmlspecialchars($rps['infRps']['serie'] ?? '') . '</Serie>';
        $xml .= '<Tipo>' . htmlspecialchars($rps['infRps']['tipo'] ?? '') . '</Tipo>';
        $xml .= '</IdentificacaoRps>';
        $xml .= '<DataEmissao>' . htmlspecialchars($rps['infRps']['dataEmissao'] ?? '') . '</DataEmissao>';
        $xml .= '<Status>' . htmlspecialchars($rps['status'] ?? '') . '</Status>';
        $xml .= '</Rps>';
        
        $xml .= '<Competencia>' . htmlspecialchars($rps['competencia'] ?? '') . '</Competencia>';
        $xml .= '<NaturezaOperacao>' . htmlspecialchars($rps['naturezaOperacao'] ?? '') . '</NaturezaOperacao>';
        $xml .= '<OptanteSimplesNacional>' . htmlspecialchars($rps['optanteSimplesNacional'] ?? '') . '</OptanteSimplesNacional>';
        $xml .= '<IncentivadorCultural>' . htmlspecialchars($rps['incentivadorCultural'] ?? '') . '</IncentivadorCultural>';
        
        $xml .= $this->buildServicoRps($rps);
        $xml .= $this->buildPrestadorRps($rps['prestador'] ?? []);
        $xml .= $this->buildTomadorRps($rps['tomador'] ?? []);
        $xml .= $this->buildConstrucaoCivil($rps['construcaoCivil'] ?? []);
        
        $xml .= '</InfDeclaracaoPrestacaoServico>';
        $xml .= '</Rps>';
        
        return $xml;
    }

    /**
     * Constrói a estrutura de Serviço para NFSe
     */
    private function buildServico(array $s): string
    {
        $xml  = '<Servico>';
        $xml .= '<Valores>';
        $xml .= '<ValorServicos>' . htmlspecialchars($s['valorServicos'] ?? '') . '</ValorServicos>';
        $xml .= '<ValorDeducoes>' . htmlspecialchars($s['valorDeducoes'] ?? '') . '</ValorDeducoes>';
        $xml .= '<IssRetido>' . htmlspecialchars($s['issRetido'] ?? '') . '</IssRetido>';
        $xml .= '<ValorIss>' . htmlspecialchars($s['valorIss'] ?? '') . '</ValorIss>';
        $xml .= '<BaseCalculo>' . htmlspecialchars($s['baseCalculo'] ?? '') . '</BaseCalculo>';
        $xml .= '<Aliquota>' . htmlspecialchars($s['aliquota'] ?? '') . '</Aliquota>';
        $xml .= '</Valores>';
        $xml .= '<ItemListaServico>' . htmlspecialchars($s['itemListaServico'] ?? '') . '</ItemListaServico>';
        $xml .= '<Discriminacao>' . htmlspecialchars($s['discriminacao'] ?? '') . '</Discriminacao>';
        $xml .= '<CodigoMunicipio>' . htmlspecialchars($s['codigoMunicipio'] ?? '') . '</CodigoMunicipio>';
        $xml .= '</Servico>';
        return $xml;
    }

    /**
     * Constrói a estrutura de Serviço para RPS
     */
    private function buildServicoRps(array $rps): string
    {
        $xml  = '<Servico>';
        $xml .= '<Valores>';
        $xml .= '<ValorServicos>' . htmlspecialchars($rps['valorServicos'] ?? '') . '</ValorServicos>';
        $xml .= '<ValorIss>' . htmlspecialchars($rps['valorIss'] ?? '') . '</ValorIss>';
        $xml .= '<BaseCalculo>' . htmlspecialchars($rps['baseCalculo'] ?? '') . '</BaseCalculo>';
        $xml .= '<Aliquota>' . htmlspecialchars($rps['aliquota'] ?? '') . '</Aliquota>';
        $xml .= '<IssRetido>' . htmlspecialchars($rps['issRetido'] ?? '') . '</IssRetido>';
        
        // Valores adicionais conforme padrão Chapecó
        if (isset($rps['valorPis'])) {
            $xml .= '<ValorPis>' . htmlspecialchars($rps['valorPis']) . '</ValorPis>';
        }
        if (isset($rps['valorCofins'])) {
            $xml .= '<ValorCofins>' . htmlspecialchars($rps['valorCofins']) . '</ValorCofins>';
        }
        if (isset($rps['valorInss'])) {
            $xml .= '<ValorInss>' . htmlspecialchars($rps['valorInss']) . '</ValorInss>';
        }
        if (isset($rps['valorIr'])) {
            $xml .= '<ValorIr>' . htmlspecialchars($rps['valorIr']) . '</ValorIr>';
        }
        if (isset($rps['valorCsll'])) {
            $xml .= '<ValorCsll>' . htmlspecialchars($rps['valorCsll']) . '</ValorCsll>';
        }
        if (isset($rps['outrasRetencoes'])) {
            $xml .= '<OutrasRetencoes>' . htmlspecialchars($rps['outrasRetencoes']) . '</OutrasRetencoes>';
        }
        if (isset($rps['valTotTributos'])) {
            $xml .= '<ValTotTributos>' . htmlspecialchars($rps['valTotTributos']) . '</ValTotTributos>';
        }
        if (isset($rps['valorDeducoes'])) {
            $xml .= '<ValorDeducoes>' . htmlspecialchars($rps['valorDeducoes']) . '</ValorDeducoes>';
        }
        if (isset($rps['descontoIncondicionado'])) {
            $xml .= '<DescontoIncondicionado>' . htmlspecialchars($rps['descontoIncondicionado']) . '</DescontoIncondicionado>';
        }
        if (isset($rps['descontoCondicionado'])) {
            $xml .= '<DescontoCondicionado>' . htmlspecialchars($rps['descontoCondicionado']) . '</DescontoCondicionado>';
        }
        
        $xml .= '</Valores>';
        
        $xml .= '<IssRetido>' . htmlspecialchars($rps['issRetido'] ?? '') . '</IssRetido>';
        if (isset($rps['responsavelRetencao'])) {
            $xml .= '<ResponsavelRetencao>' . htmlspecialchars($rps['responsavelRetencao']) . '</ResponsavelRetencao>';
        }
        $xml .= '<ItemListaServico>' . htmlspecialchars($rps['itemListaServico'] ?? '') . '</ItemListaServico>';
        if (isset($rps['codigoCnae'])) {
            $xml .= '<CodigoCnae>' . htmlspecialchars($rps['codigoCnae']) . '</CodigoCnae>';
        }
        $xml .= '<Discriminacao>' . htmlspecialchars($rps['discriminacao'] ?? '') . '</Discriminacao>';
        $xml .= '<CodigoMunicipio>' . htmlspecialchars($rps['codigoMunicipio'] ?? '') . '</CodigoMunicipio>';
        if (isset($rps['codigoPais'])) {
            $xml .= '<CodigoPais>' . htmlspecialchars($rps['codigoPais']) . '</CodigoPais>';
        }
        if (isset($rps['exigibilidadeISS'])) {
            $xml .= '<ExigibilidadeISS>' . htmlspecialchars($rps['exigibilidadeISS']) . '</ExigibilidadeISS>';
        }
        if (isset($rps['municipioIncidencia'])) {
            $xml .= '<MunicipioIncidencia>' . htmlspecialchars($rps['municipioIncidencia']) . '</MunicipioIncidencia>';
        }
        if (isset($rps['cNBS'])) {
            $xml .= '<cNBS>' . htmlspecialchars($rps['cNBS']) . '</cNBS>';
        }
        
        $xml .= '</Servico>';
        return $xml;
    }

    /**
     * Constrói a estrutura de Prestador para NFSe
     */
    private function buildPrestadorServico(array $p): string
    {
        $xml  = '<PrestadorServico>';
        $xml .= '<IdentificacaoPrestador>';
        $xml .= '<Cnpj>' . htmlspecialchars($p['cnpj'] ?? '') . '</Cnpj>';
        $xml .= '<InscricaoMunicipal>' . htmlspecialchars($p['inscricaoMunicipal'] ?? '') . '</InscricaoMunicipal>';
        $xml .= '</IdentificacaoPrestador>';
        $xml .= '<RazaoSocial>' . htmlspecialchars($p['razaoSocial'] ?? '') . '</RazaoSocial>';
        if (isset($p['nomeFantasia'])) {
            $xml .= '<NomeFantasia>' . htmlspecialchars($p['nomeFantasia']) . '</NomeFantasia>';
        }
        $xml .= $this->buildEndereco($p['endereco'] ?? []);
        $xml .= $this->buildContato($p['contato'] ?? []);
        $xml .= '</PrestadorServico>';
        return $xml;
    }

    /**
     * Constrói a estrutura de Prestador para RPS
     */
    private function buildPrestadorRps(array $prestador): string
    {
        $xml  = '<Prestador>';
        $xml .= '<CpfCnpj>';
        $xml .= '<Cnpj>' . htmlspecialchars($prestador['cnpj'] ?? '') . '</Cnpj>';
        $xml .= '</CpfCnpj>';
        $xml .= '<InscricaoMunicipal>' . htmlspecialchars($prestador['inscricaoMunicipal'] ?? '') . '</InscricaoMunicipal>';
        $xml .= '</Prestador>';
        return $xml;
    }

    /**
     * Constrói a estrutura de Tomador para NFSe
     */
    private function buildTomadorServico(array $t): string
    {
        $xml  = '<TomadorServico>';
        $xml .= '<IdentificacaoTomador>';
        $xml .= '<CpfCnpj>';
        if (!empty($t['cnpj'])) {
            $xml .= '<Cnpj>' . htmlspecialchars($t['cnpj']) . '</Cnpj>';
        } elseif (!empty($t['cpf'])) {
            $xml .= '<Cpf>' . htmlspecialchars($t['cpf']) . '</Cpf>';
        }
        $xml .= '</CpfCnpj>';
        if (!empty($t['inscricaoMunicipal'])) {
            $xml .= '<InscricaoMunicipal>' . htmlspecialchars($t['inscricaoMunicipal']) . '</InscricaoMunicipal>';
        }
        $xml .= '</IdentificacaoTomador>';
        $xml .= '<RazaoSocial>' . htmlspecialchars($t['razaoSocial'] ?? '') . '</RazaoSocial>';
        $xml .= $this->buildEndereco($t['endereco'] ?? []);
        $xml .= $this->buildContato($t['contato'] ?? []);
        $xml .= '</TomadorServico>';
        return $xml;
    }

    /**
     * Constrói a estrutura de Tomador para RPS
     */
    private function buildTomadorRps(array $tomador): string
    {
        if (empty($tomador)) return '';
        
        $xml  = '<TomadorServico>';
        $xml .= '<IdentificacaoTomador>';
        $xml .= '<CpfCnpj>';
        if (!empty($tomador['cnpj'])) {
            $xml .= '<Cnpj>' . htmlspecialchars($tomador['cnpj']) . '</Cnpj>';
        } elseif (!empty($tomador['cpf'])) {
            $xml .= '<Cpf>' . htmlspecialchars($tomador['cpf']) . '</Cpf>';
        }
        $xml .= '</CpfCnpj>';
        if (!empty($tomador['inscricaoMunicipal'])) {
            $xml .= '<InscricaoMunicipal>' . htmlspecialchars($tomador['inscricaoMunicipal']) . '</InscricaoMunicipal>';
        }
        $xml .= '</IdentificacaoTomador>';
        $xml .= '<RazaoSocial>' . htmlspecialchars($tomador['razaoSocial'] ?? '') . '</RazaoSocial>';
        $xml .= $this->buildEnderecoRps($tomador['endereco'] ?? []);
        $xml .= $this->buildContatoRps($tomador['contato'] ?? []);
        $xml .= '</TomadorServico>';
        return $xml;
    }

    /**
     * Constrói a estrutura de Endereço para NFSe
     */
    private function buildEndereco(array $e): string
    {
        $xml  = '<Endereco>';
        $xml .= '<Endereco>' . htmlspecialchars($e['endereco'] ?? '') . '</Endereco>';
        $xml .= '<Numero>' . htmlspecialchars($e['numero'] ?? '') . '</Numero>';
        if (isset($e['complemento'])) {
            $xml .= '<Complemento>' . htmlspecialchars($e['complemento']) . '</Complemento>';
        }
        $xml .= '<Bairro>' . htmlspecialchars($e['bairro'] ?? '') . '</Bairro>';
        $xml .= '<CodigoMunicipio>' . htmlspecialchars($e['codigoMunicipio'] ?? '') . '</CodigoMunicipio>';
        $xml .= '<Uf>' . htmlspecialchars($e['uf'] ?? '') . '</Uf>';
        $xml .= '<Cep>' . htmlspecialchars($e['cep'] ?? '') . '</Cep>';
        $xml .= '</Endereco>';
        return $xml;
    }

    /**
     * Constrói a estrutura de Endereço para RPS
     */
    private function buildEnderecoRps(array $endereco): string
    {
        if (empty($endereco)) return '';
        
        $xml  = '<Endereco>';
        $xml .= '<Endereco>' . htmlspecialchars($endereco['logradouro'] ?? $endereco['endereco'] ?? '') . '</Endereco>';
        $xml .= '<Numero>' . htmlspecialchars($endereco['numero'] ?? '') . '</Numero>';
        if (isset($endereco['complemento'])) {
            $xml .= '<Complemento>' . htmlspecialchars($endereco['complemento']) . '</Complemento>';
        }
        $xml .= '<Bairro>' . htmlspecialchars($endereco['bairro'] ?? '') . '</Bairro>';
        $xml .= '<CodigoMunicipio>' . htmlspecialchars($endereco['codigoMunicipio'] ?? '') . '</CodigoMunicipio>';
        $xml .= '<Uf>' . htmlspecialchars($endereco['uf'] ?? '') . '</Uf>';
        $xml .= '<Cep>' . htmlspecialchars($endereco['cep'] ?? '') . '</Cep>';
        $xml .= '</Endereco>';
        return $xml;
    }

    /**
     * Constrói a estrutura de Contato para NFSe
     */
    private function buildContato(array $c): string
    {
        if (empty($c['telefone']) && empty($c['email'])) return '';
        
        $xml  = '<Contato>';
        if (!empty($c['telefone'])) {
            $xml .= '<Telefone>' . htmlspecialchars($c['telefone']) . '</Telefone>';
        }
        if (!empty($c['email'])) {
            $xml .= '<Email>' . htmlspecialchars($c['email']) . '</Email>';
        }
        $xml .= '</Contato>';
        return $xml;
    }

    /**
     * Constrói a estrutura de Contato para RPS
     */
    private function buildContatoRps(array $contato): string
    {
        if (empty($contato['telefone']) && empty($contato['email'])) return '';
        
        $xml  = '<Contato>';
        if (!empty($contato['telefone'])) {
            $xml .= '<Telefone>' . htmlspecialchars($contato['telefone']) . '</Telefone>';
        }
        if (!empty($contato['email'])) {
            $xml .= '<Email>' . htmlspecialchars($contato['email']) . '</Email>';
        }
        $xml .= '</Contato>';
        return $xml;
    }

    /**
     * Constrói a estrutura de Órgão Gerador
     */
    private function buildOrgaoGerador(array $o): string
    {
        $xml  = '<OrgaoGerador>';
        $xml .= '<CodigoMunicipio>' . htmlspecialchars($o['codigoMunicipio'] ?? '') . '</CodigoMunicipio>';
        $xml .= '<Uf>' . htmlspecialchars($o['uf'] ?? '') . '</Uf>';
        $xml .= '</OrgaoGerador>';
        return $xml;
    }

    /**
     * Constrói a estrutura de Construção Civil (opcional)
     */
    private function buildConstrucaoCivil(array $cc): string
    {
        if (empty($cc['codigoObra']) && empty($cc['art'])) return '';
        
        $xml  = '<ConstrucaoCivil>';
        $xml .= '<CodigoObra>' . htmlspecialchars($cc['codigoObra'] ?? '') . '</CodigoObra>';
        $xml .= '<Art>' . htmlspecialchars($cc['art'] ?? '') . '</Art>';
        $xml .= '</ConstrucaoCivil>';
        return $xml;
    }
}