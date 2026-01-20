<?php
namespace NFSePrefeitura\NFSe\PFNatal;

use InvalidArgumentException;

class Natal
{
    public function gerarXmlLoteRps(array $dados): string
    {
        $this->validarLote($dados);

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<EnviarLoteRpsEnvio xmlns="http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd">';
        $xml .= '<LoteRps Id="lote_' . $this->xmlSafeId((string)$dados['lote_id']) . '">';
        $xml .= '<NumeroLote>' . $this->onlyDigits((string)$dados['numeroLote']) . '</NumeroLote>';
        $xml .= '<Cnpj>' . $this->onlyDigits((string)$dados['cnpjPrestador']) . '</Cnpj>';
        $xml .= '<InscricaoMunicipal>' . $this->onlyDigits((string)$dados['inscricaoMunicipal']) . '</InscricaoMunicipal>';
        $xml .= '<QuantidadeRps>' . (int)$dados['quantidadeRps'] . '</QuantidadeRps>';
        $xml .= '<ListaRps>';

        foreach ($dados['rps'] as $rps) {
            $this->validarRps($rps);

            $infId = (string)$rps['inf_id'];

            $xml .= '<Rps>';
            $xml .= '<InfRps Id="rps:' . $this->xmlSafeId($infId) . '">';

            $xml .= '<IdentificacaoRps>';
            $xml .= '<Numero>' . $this->onlyDigits((string)$rps['infRps']['numero']) . '</Numero>';
            $xml .= '<Serie>' . $this->xmlSafeText((string)$rps['infRps']['serie']) . '</Serie>';
            $xml .= '<Tipo>' . (int)$rps['infRps']['tipo'] . '</Tipo>';
            $xml .= '</IdentificacaoRps>';

            $xml .= '<DataEmissao>' . $this->xmlSafeText((string)$rps['infRps']['dataEmissao']) . '</DataEmissao>';
            $xml .= '<NaturezaOperacao>' . $this->onlyDigits((string)$rps['naturezaOperacao']) . '</NaturezaOperacao>';
            $xml .= '<OptanteSimplesNacional>' . (int)$rps['optanteSimplesNacional'] . '</OptanteSimplesNacional>';
            $xml .= '<IncentivadorCultural>' . (int)$rps['incentivadorCultural'] . '</IncentivadorCultural>';
            $xml .= '<Status>' . (int)$rps['status'] . '</Status>';

            // -------- Serviço
            $xml .= '<Servico>';
            $xml .= '<Valores>';
            $xml .= '<ValorServicos>' . $this->fmtMoney($rps['valorServicos']) . '</ValorServicos>';
            $xml .= '<ValorDeducoes>' . $this->fmtMoney($rps['valorDeducoes'] ?? 0) . '</ValorDeducoes>';
            $xml .= '<ValorPis>' . $this->fmtMoney($rps['valorPis'] ?? 0) . '</ValorPis>';
            $xml .= '<ValorCofins>' . $this->fmtMoney($rps['valorCofins'] ?? 0) . '</ValorCofins>';
            $xml .= '<ValorCsll>' . $this->fmtMoney($rps['valorCsll'] ?? 0) . '</ValorCsll>';
            $xml .= '<ValorIr>' . $this->fmtMoney($rps['valorIr'] ?? 0) . '</ValorIr>';

            $xml .= '<IssRetido>' . (int)($rps['issRetido'] ?? 2) . '</IssRetido>';
            $xml .= '<ValorIss>' . $this->fmtMoney($rps['valorIss'] ?? 0) . '</ValorIss>';
            $xml .= '<ValorIssRetido>' . $this->fmtMoney($rps['valorIssRetido'] ?? 0) . '</ValorIssRetido>';

            $xml .= '<OutrasRetencoes>' . $this->fmtMoney($rps['outrasRetencoes'] ?? 0) . '</OutrasRetencoes>';
            $xml .= '<BaseCalculo>' . $this->fmtMoney($rps['baseCalculo'] ?? $rps['valorServicos']) . '</BaseCalculo>';
            $xml .= '<Aliquota>' . $this->fmtAliquota($rps['aliquota'] ?? 0) . '</Aliquota>';

            $xml .= '<ValorLiquidoNfse>' . $this->fmtMoney($rps['valorLiquidoNfse'] ?? $rps['valorServicos']) . '</ValorLiquidoNfse>';
            $xml .= '<DescontoIncondicionado>' . $this->fmtMoney($rps['descontoIncondicionado'] ?? 0) . '</DescontoIncondicionado>';
            $xml .= '<DescontoCondicionado>' . $this->fmtMoney($rps['descontoCondicionado'] ?? 0) . '</DescontoCondicionado>';
            $xml .= '</Valores>';

            $xml .= '<ItemListaServico>' . $this->xmlSafeText((string)$rps['itemListaServico']) . '</ItemListaServico>';
            $xml .= '<CodigoTributacaoMunicipio>' . $this->xmlSafeText((string)$rps['codigoTributacaoMunicipio']) . '</CodigoTributacaoMunicipio>';
            $xml .= '<Discriminacao>' . $this->xmlSafeText((string)$rps['discriminacao']) . '</Discriminacao>';
            $xml .= '<CodigoMunicipio>' . $this->onlyDigits((string)$rps['codigoMunicipio']) . '</CodigoMunicipio>';
            $xml .= '</Servico>';

            // -------- Prestador
            $xml .= '<Prestador>';
            $xml .= '<Cnpj>' . $this->onlyDigits((string)$dados['cnpjPrestador']) . '</Cnpj>';
            $xml .= '<InscricaoMunicipal>' . $this->onlyDigits((string)$dados['inscricaoMunicipal']) . '</InscricaoMunicipal>';
            $xml .= '</Prestador>';

            // -------- Tomador
            $xml .= '<Tomador>';
            $xml .= '<IdentificacaoTomador><CpfCnpj>';

            $doc = $this->onlyDigits((string)$rps['tomador']['cpfCnpj']);
            if (strlen($doc) === 11) {
                $xml .= '<Cpf>' . $doc . '</Cpf>';
            } else {
                $xml .= '<Cnpj>' . $doc . '</Cnpj>';
            }

            $xml .= '</CpfCnpj></IdentificacaoTomador>';
            $xml .= '<RazaoSocial>' . $this->xmlSafeText((string)$rps['tomador']['razaoSocial']) . '</RazaoSocial>';

            if (!empty($rps['tomador']['endereco']) && is_array($rps['tomador']['endereco'])) {
                $e = $rps['tomador']['endereco'];
                $xml .= '<Endereco>';
                $xml .= '<Endereco>' . $this->xmlSafeText((string)($e['logradouro'] ?? '')) . '</Endereco>';
                $xml .= '<Numero>' . $this->xmlSafeText((string)($e['numero'] ?? '')) . '</Numero>';
                $xml .= '<Bairro>' . $this->xmlSafeText((string)($e['bairro'] ?? '')) . '</Bairro>';
                $xml .= '<CodigoMunicipio>' . $this->onlyDigits((string)($e['codigoMunicipio'] ?? '')) . '</CodigoMunicipio>';
                $xml .= '<Uf>' . $this->xmlSafeText((string)($e['uf'] ?? '')) . '</Uf>';
                $xml .= '<Cep>' . $this->onlyDigits((string)($e['cep'] ?? '')) . '</Cep>';
                $xml .= '</Endereco>';
            }

            $xml .= '</Tomador>';

            $xml .= '</InfRps>';
            $xml .= '</Rps>';
        }

        $xml .= '</ListaRps>';
        $xml .= '</LoteRps>';
        $xml .= '</EnviarLoteRpsEnvio>';

        // valida localmente (ajuda a detectar lixo antes de enviar)
        $this->assertXmlOk($xml);

        return $xml;
    }

    // ---------------- VALIDATORS ----------------

    private function validarLote(array $d): void
    {
        foreach (['lote_id','numeroLote','cnpjPrestador','inscricaoMunicipal','quantidadeRps','rps'] as $k) {
            if (!isset($d[$k])) throw new InvalidArgumentException("Campo obrigatório do lote não informado: {$k}");
        }
        if (!is_array($d['rps']) || count($d['rps']) < 1) {
            throw new InvalidArgumentException("Campo rps deve ser um array com ao menos 1 item.");
        }
    }

    private function validarRps(array $rps): void
    {
        foreach (['inf_id','infRps','naturezaOperacao','optanteSimplesNacional','incentivadorCultural','status','valorServicos','tomador'] as $k) {
            if (!isset($rps[$k])) throw new InvalidArgumentException("Campo obrigatório do RPS não informado: {$k}");
        }
        foreach (['numero','serie','tipo','dataEmissao'] as $k) {
            if (!isset($rps['infRps'][$k])) throw new InvalidArgumentException("Campo obrigatório infRps não informado: {$k}");
        }
        if (!isset($rps['tomador']['cpfCnpj'], $rps['tomador']['razaoSocial'])) {
            throw new InvalidArgumentException("Tomador incompleto: cpfCnpj/razaoSocial obrigatórios.");
        }
    }

    private function assertXmlOk(string $xml): void
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $ok  = $dom->loadXML($xml);
        if (!$ok) {
            $errs = libxml_get_errors();
            libxml_clear_errors();
            throw new InvalidArgumentException("XML inválido localmente (antes do envio):\n" . print_r($errs, true));
        }
    }

    // ---------------- FORMAT / SANITIZE ----------------

    private function onlyDigits(string $s): string
    {
        return preg_replace('/\D+/', '', $s) ?? '';
    }

    /**
     * Sanitiza texto para XML 1.0 e ESCAPA.
     * Resolve o EX1: illegal xml character.
     */
    private function xmlSafeText(string $s): string
    {
        // garante UTF-8 válido
        $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');

        // remove caracteres inválidos no XML 1.0
        $s = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $s);

        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** Id não deve conter espaços/quotes etc. */
    private function xmlSafeId(string $s): string
    {
        $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
        $s = preg_replace('/[^\w\-\:\.]+/u', '', $s); // mantém letras, números, _, -, :, .
        return $s ?: '0';
    }

    private function fmtMoney($v): string
    {
        return number_format((float)$v, 2, '.', '');
    }

    private function fmtAliquota($v): string
    {
        return number_format((float)$v, 2, '.', '');
    }
}
