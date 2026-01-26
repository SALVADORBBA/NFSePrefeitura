<?php

namespace NFSePrefeitura\NFSe\Motor;

/**
 * Classe genérica para geração de XML baseado em modelo
 * PSR-12, helpers privados, padrão flexível
 */
class MotorMake
{
    private $dados;
    /**
     * Gera XML conforme modelo e dados
     * @param array $dados
     * @param array $modelo Estrutura do modelo XML (array aninhado)
     * @return string
     */
    public function gerarXml(array $dados, array $modelo): string
    {

    $this->dados=$dados;
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= $this->montarXml($modelo, $dados);
        return $xml;
    }

    /**
     * Monta XML recursivamente
     * @param array $modelo
     * @param array $dados
     * @return string
     */
    private function montarXml(array $modelo, array $dados): string
    {
        $xml = '';
        foreach ($modelo as $tag => $info) {
            $attrs = $info['attrs'] ?? [];
            $valor = $info['valor'] ?? null;
            $children = $info['children'] ?? [];
            $isList = $info['list'] ?? false;
            $dataKey = $info['key'] ?? $tag;

            if ($isList && isset($dados[$dataKey]) && is_array($dados[$dataKey])) {
                foreach ($dados[$dataKey] as $item) {
                    $xml .= $this->tag($tag, $this->montarXml($children, $item), $attrs);
                }
            } elseif (!empty($children)) {
                $xml .= $this->tag($tag, $this->montarXml($children, $dados[$dataKey] ?? []), $attrs);
            } else {
                $xml .= $this->tag($tag, $this->xmlValue($dados[$dataKey] ?? $valor), $attrs);
            }
        }
        return $xml;
    }

    /**
     * Escapa valores para XML
     */
    private function xmlValue($v): string
    {
        if (is_null($v)) {
            return '';
        }
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        if (is_array($v) || is_object($v)) {
            return htmlspecialchars(json_encode($v), ENT_XML1 | ENT_COMPAT, 'UTF-8');
        }
        return htmlspecialchars((string)$v, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    /**
     * Monta atributos para tags XML
     */
    private function attr(array $attrs): string
    {
        $str = '';
        foreach ($attrs as $k => $v) {
            if ($v !== null && $v !== '') {
                $str .= ' ' . $k . '="' . htmlspecialchars((string)$v, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '"';
            }
        }
        return $str;
    }

    /**
     * Cria uma tag XML simples
     */
    private function tag(string $name, ?string $value = null, array $attrs = []): string
    {
        $attrStr = $this->attr($attrs);
        if ($value === null || $value === '') {
            return "<{$name}{$attrStr}/>";
        }
        return "<{$name}{$attrStr}>{$value}</{$name}>";
    }

    /**
     * Gera e salva o código PHP de uma nova classe em qualquer diretório informado
     * @param string $nomeClasse Nome da classe a ser criada
     * @param string $nomeMetodo Nome do método público para gerar XML
     * @param string $modeloXml Modelo XML puro
     * @param string $diretorioDestino Diretório onde salvar a classe
     * @return string Caminho completo do arquivo salvo
     */
    public function gerarESalvarClasse(string $nomeClasse, string $nomeMetodo, string $modeloXml, string $diretorioDestino): string
    {
        $namespace = "NFSePrefeitura\\NFSe\\$nomeClasse";
        $codigoMetodo = "    /**\n     * Gera o XML do lote RPS conforme o padrão ABRASF\n     */\n    public function $nomeMetodo(array \$dados): string\n    {\n        \$xml  = '<?xml version=\"1.0\" encoding=\"UTF-8\"?>';\n        \$xml .= '<EnviarLoteRpsEnvio xmlns=\"http://www.abrasf.org.br/nfse.xsd\">';\n        \$xml .= '<LoteRps Id=\"lote' . \$dados['lote_id'] . '\" versao=\"2.04\">';\n        \$xml .= '<NumeroLote>' . \$dados['numeroLote'] . '</NumeroLote>';\n        \$xml .= '<QuantidadeRps>' . \$dados['quantidadeRps'] . '</QuantidadeRps>';\n        \$xml .= '<ListaRps>';\n        foreach (\$dados['rps'] as \$rps) {\n            \$xml .= '<Rps>';\n            \$xml .= '<InfDeclaracaoPrestacaoServico Id=\"rps' . \$rps['numero'] . '\">';\n            \$xml .= '<Rps>';\n            \$xml .= '<IdentificacaoRps>';\n            \$xml .= '<Numero>' . \$rps['numero'] . '</Numero>';\n            \$xml .= '<Serie>' . \$rps['serie'] . '</Serie>';\n            \$xml .= '<Tipo>' . \$rps['tipo'] . '</Tipo>';\n            \$xml .= '</IdentificacaoRps>';\n            \$xml .= '<DataEmissao>' . \$rps['dataEmissao'] . '</DataEmissao>';\n            \$xml .= '<Status>' . \$rps['status'] . '</Status>';\n            \$xml .= '</Rps>';\n            \$xml .= '<Competencia>' . \$rps['competencia'] . '</Competencia>';\n            \$xml .= '<Servico>';\n            \$xml .= '<Valores>';\n            \$xml .= '<ValorServicos>' . \$rps['valorServicos'] . '</ValorServicos>';\n            \$xml .= '<ValorDeducoes>' . \$rps['valorDeducoes'] . '</ValorDeducoes>';\n            \$xml .= '<ValorPis>' . \$rps['valorPis'] . '</ValorPis>';\n            \$xml .= '<ValorCofins>' . \$rps['valorCofins'] . '</ValorCofins>';\n            \$xml .= '<ValorInss>' . \$rps['valorInss'] . '</ValorInss>';\n            \$xml .= '<ValorIr>' . \$rps['valorIr'] . '</ValorIr>';\n            \$xml .= '<ValorCsll>' . \$rps['valorCsll'] . '</ValorCsll>';\n            \$xml .= '<OutrasRetencoes>' . \$rps['outrasRetencoes'] . '</OutrasRetencoes>';\n            \$xml .= '<ValTotTributos>' . \$rps['valTotTributos'] . '</ValTotTributos>';\n            \$xml .= '<ValorIss>' . \$rps['valorIss'] . '</ValorIss>';\n            \$xml .= '<Aliquota>0.00</Aliquota>';\n            \$xml .= '<DescontoIncondicionado>0.00</DescontoIncondicionado>';\n            \$xml .= '<DescontoCondicionado>0.00</DescontoCondicionado>';\n            \$xml .= '</Valores>';\n            \$xml .= '<IssRetido>' . \$rps['issRetido'] . '</IssRetido>';\n            \$xml .= '<ResponsavelRetencao>1</ResponsavelRetencao>';\n            \$xml .= '<ItemListaServico>' . \$rps['itemListaServico'] . '</ItemListaServico>';\n            \$xml .= '<CodigoCnae>' . \$rps['codigoCnae'] . '</CodigoCnae>';\n            \$xml .= '<Discriminacao>' . \$rps['discriminacao'] . '</Discriminacao>';\n            \$xml .= '<CodigoMunicipio>' . \$rps['codigoMunicipio'] . '</CodigoMunicipio>';\n            \$xml .= '<cNBS>' . \$rps['cNBS'] . '</cNBS>';\n            \$xml .= '<CodigoPais>1058</CodigoPais>';\n            \$xml .= '<ExigibilidadeISS>1</ExigibilidadeISS>';\n            \$xml .= '<MunicipioIncidencia>' . \$rps['municipioIncidencia'] . '</MunicipioIncidencia>';\n            \$xml .= '<LocalidadeIncidencia>' . \$rps['localidadeIncidencia'] . '</LocalidadeIncidencia>';\n            \$xml .= '</Servico>';\n            \$xml .= '<Prestador>';\n            \$xml .= '<CpfCnpj><Cnpj>' . \$dados['cnpjPrestador'] . '</Cnpj></CpfCnpj>';\n            \$xml .= '<InscricaoMunicipal>' . \$dados['inscricaoMunicipal'] . '</InscricaoMunicipal>';\n            \$xml .= '</Prestador>';\n            \$xml .= '<TomadorServico>';\n            \$xml .= '<IdentificacaoTomador>';\n            \$xml .= '<CpfCnpj><Cnpj>' . \$rps['tomador']['cnpj'] . '</Cnpj></CpfCnpj>';\n            \$xml .= '</IdentificacaoTomador>';\n            \$xml .= '<RazaoSocial>' . \$rps['tomador']['razaoSocial'] . '</RazaoSocial>';\n            \$xml .= '<Endereco>';\n            \$xml .= '<Endereco>' . \$rps['tomador']['endereco'] . '</Endereco>';\n            \$xml .= '<Numero>' . \$rps['tomador']['numero'] . '</Numero>';\n            \$xml .= '<Complemento>' . \$rps['tomador']['complemento'] . '</Complemento>';\n            \$xml .= '<Bairro>' . \$rps['tomador']['bairro'] . '</Bairro>';\n            \$xml .= '<CodigoMunicipio>' . \$rps['tomador']['codigoMunicipio'] . '</CodigoMunicipio>';\n            \$xml .= '<Uf>' . \$rps['tomador']['uf'] . '</Uf>';\n            \$xml .= '<Cep>' . \$rps['tomador']['cep'] . '</Cep>';\n            \$xml .= '</Endereco>';\n            \$xml .= '<Contato>';\n            \$xml .= '<Telefone>' . \$rps['tomador']['telefone'] . '</Telefone>';\n            \$xml .= '<Email>' . \$rps['tomador']['email'] . '</Email>';\n            \$xml .= '</Contato>';\n            \$xml .= '</TomadorServico>';\n            \$xml .= '<RegimeEspecialTributacao>1</RegimeEspecialTributacao>';\n            \$xml .= '<OptanteSimplesNacional>1</OptanteSimplesNacional>';\n            \$xml .= '<IncentivoFiscal>2</IncentivoFiscal>';\n            \$xml .= '<InformacoesComplementares>nada</InformacoesComplementares>';\n            \$xml .= '</InfDeclaracaoPrestacaoServico>';\n            \$xml .= '</Rps>';\n        }\n        \$xml .= '</ListaRps>';\n        \$xml .= '</LoteRps>';\n        \$xml .= '</EnviarLoteRpsEnvio>';\n        return \$xml;\n    }\n";
        $codigo = "<?php\n\nnamespace $namespace;\n\nclass $nomeClasse\n{\n$codigoMetodo}\n";
        if (!is_dir($diretorioDestino)) {
            mkdir($diretorioDestino, 0777, true);
        }
        $caminhoArquivo = rtrim($diretorioDestino, '/\\') . DIRECTORY_SEPARATOR . $nomeClasse . '.php';
        file_put_contents($caminhoArquivo, $codigo);
        return $caminhoArquivo;
    }
}