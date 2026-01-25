<?php

namespace NFSePrefeitura\NFSe\PFNatal;

class NatalGeradorXML
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
     * Gera o XML da NFSe conforme modelo versao_2_natal_.xml
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
     * Gera o XML do lote RPS conforme modelo versao_2_natal_.xml, usando array de entrada igual ao JSON enviado
     */
    public function gerarXmlLoteRps(array $dados): string
    {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<EnviarLoteRpsEnvio xmlns="http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd">';
        $xml .= '<LoteRps Id="lote:' . htmlspecialchars($dados['lote_id'] ?? '') . '">';
        $xml .= '<NumeroLote>' . htmlspecialchars($dados['numeroLote'] ?? '') . '</NumeroLote>';
        $xml .= '<Cnpj>' . htmlspecialchars($dados['cnpjPrestador'] ?? '') . '</Cnpj>';
        $xml .= '<InscricaoMunicipal>' . htmlspecialchars($dados['inscricaoMunicipal'] ?? '') . '</InscricaoMunicipal>';
        $xml .= '<QuantidadeRps>' . htmlspecialchars($dados['quantidadeRps'] ?? '') . '</QuantidadeRps>';
        $xml .= '<ListaRps>';
        $xml .= $this->buildRps($dados['rps'][0]);
        $xml .= '</ListaRps>';
        $xml .= '</LoteRps>';
        $xml .= '</EnviarLoteRpsEnvio>';
        return $this->sanitizeXmlDeclaration($xml);
    }

    private function buildInfNfse(array $d): string
    {
        // Exemplo: campos principais, ajustar conforme $d
        $xml  = '<InfNfse id="' . htmlspecialchars($d['inf_id'] ?? '') . '">';
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

    private function buildPrestadorServico(array $p): string
    {
        $xml  = '<PrestadorServico>';
        $xml .= '<IdentificacaoPrestador>';
        $xml .= '<Cnpj>' . htmlspecialchars($p['cnpj'] ?? '') . '</Cnpj>';
        $xml .= '<InscricaoMunicipal>' . htmlspecialchars($p['inscricaoMunicipal'] ?? '') . '</InscricaoMunicipal>';
        $xml .= '</IdentificacaoPrestador>';
        $xml .= '<RazaoSocial>' . htmlspecialchars($p['razaoSocial'] ?? '') . '</RazaoSocial>';
        $xml .= '<NomeFantasia>' . htmlspecialchars($p['nomeFantasia'] ?? '') . '</NomeFantasia>';
        $xml .= $this->buildEndereco($p['endereco'] ?? []);
        $xml .= $this->buildContato($p['contato'] ?? []);
        $xml .= '</PrestadorServico>';
        return $xml;
    }

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

    private function buildOrgaoGerador(array $o): string
    {
        $xml  = '<OrgaoGerador>';
        $xml .= '<CodigoMunicipio>' . htmlspecialchars($o['codigoMunicipio'] ?? '') . '</CodigoMunicipio>';
        $xml .= '<Uf>' . htmlspecialchars($o['uf'] ?? '') . '</Uf>';
        $xml .= '</OrgaoGerador>';
        return $xml;
    }

    private function buildRps(array $rps): string
    {
        $xml  = '<Rps>';
        $xml .= '<InfRps Id="rps:' . htmlspecialchars($rps['inf_id'] ?? '') . '">';
        $xml .= '<IdentificacaoRps>';
        $xml .= '<Numero>' . htmlspecialchars($rps['infRps']['numero'] ?? '') . '</Numero>';
        $xml .= '<Serie>' . htmlspecialchars($rps['infRps']['serie'] ?? '') . '</Serie>';
        $xml .= '<Tipo>' . htmlspecialchars($rps['infRps']['tipo'] ?? '') . '</Tipo>';
        $xml .= '</IdentificacaoRps>';
        $xml .= '<DataEmissao>' . htmlspecialchars($rps['infRps']['dataEmissao'] ?? '') . '</DataEmissao>';
        $xml .= '<NaturezaOperacao>' . htmlspecialchars($rps['naturezaOperacao'] ?? '') . '</NaturezaOperacao>';
        $xml .= '<OptanteSimplesNacional>' . htmlspecialchars($rps['optanteSimplesNacional'] ?? '') . '</OptanteSimplesNacional>';
        $xml .= '<IncentivadorCultural>' . htmlspecialchars($rps['incentivadorCultural'] ?? '') . '</IncentivadorCultural>';
        $xml .= '<Status>' . htmlspecialchars($rps['status'] ?? '') . '</Status>';
        $xml .= $this->buildServicoRps($rps);
        $xml .= $this->buildTomadorRps($rps['tomador'] ?? $rps['Tomador'] ?? []);
        $xml .= $this->buildConstrucaoCivil($rps['construcaoCivil'] ?? []);
        $xml .= '</InfRps>';
        $xml .= '</Rps>';
        return $xml;
    }

    private function buildServicoRps(array $rps): string
    {
        $xml  = '<Servico>';
        $xml .= '<Valores>';
        $xml .= '<ValorServicos>' . htmlspecialchars($rps['valorServicos'] ?? '') . '</ValorServicos>';
        $xml .= '<ValorIss>' . htmlspecialchars($rps['valorIss'] ?? '') . '</ValorIss>';
        $xml .= '<BaseCalculo>' . htmlspecialchars($rps['baseCalculo'] ?? '') . '</BaseCalculo>';
        $xml .= '<Aliquota>' . htmlspecialchars($rps['aliquota'] ?? '') . '</Aliquota>';
        $xml .= '<IssRetido>' . htmlspecialchars($rps['issRetido'] ?? '') . '</IssRetido>';
        $xml .= '</Valores>';
        $xml .= '<ItemListaServico>' . htmlspecialchars($rps['itemListaServico'] ?? '') . '</ItemListaServico>';
        $xml .= '<CodigoCnae>' . htmlspecialchars($rps['codigoCnae'] ?? '') . '</CodigoCnae>';
        $xml .= '<Discriminacao>' . htmlspecialchars($rps['discriminacao'] ?? '') . '</Discriminacao>';
        $xml .= '<CodigoMunicipio>' . htmlspecialchars($rps['codigoMunicipio'] ?? '') . '</CodigoMunicipio>';
        $xml .= '</Servico>';
        return $xml;
    }

    private function buildTomadorRps(array $tomador): string
    {
        if (empty($tomador)) return '';
        $xml  = '<Tomador>';
        $xml .= '<IdentificacaoTomador>';
        $xml .= '<CpfCnpj>';
        if (!empty($tomador['cpfCnpj'])) {
            if (strlen($tomador['cpfCnpj']) == 11) {
                $xml .= '<Cpf>' . htmlspecialchars($tomador['cpfCnpj']) . '</Cpf>';
            } else {
                $xml .= '<Cnpj>' . htmlspecialchars($tomador['cpfCnpj']) . '</Cnpj>';
            }
        }
        $xml .= '</CpfCnpj>';
        $xml .= '</IdentificacaoTomador>';
        $xml .= '<RazaoSocial>' . htmlspecialchars($tomador['razaoSocial'] ?? '') . '</RazaoSocial>';
        $xml .= $this->buildEnderecoRps($tomador['endereco'] ?? []);
        $xml .= $this->buildContatoRps($tomador['contato'] ?? []);
        $xml .= '</Tomador>';
        return $xml;
    }

    private function buildEnderecoRps(array $endereco): string
    {
        if (empty($endereco)) return '';
        $xml  = '<Endereco>';
        $xml .= '<Endereco>' . htmlspecialchars($endereco['logradouro'] ?? '') . '</Endereco>';
        $xml .= '<Numero>' . htmlspecialchars($endereco['numero'] ?? '') . '</Numero>';
        $xml .= '<Bairro>' . htmlspecialchars($endereco['bairro'] ?? '') . '</Bairro>';
        $xml .= '<CodigoMunicipio>' . htmlspecialchars($endereco['codigoMunicipio'] ?? '') . '</CodigoMunicipio>';
        $xml .= '<Uf>' . htmlspecialchars($endereco['uf'] ?? '') . '</Uf>';
        $xml .= '<Cep>' . htmlspecialchars($endereco['cep'] ?? '') . '</Cep>';
        $xml .= '</Endereco>';
        return $xml;
    }

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