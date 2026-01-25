<?php

namespace NFSePrefeitura\NFSe\PFNatal;

use NFSePrefeitura\NFSe\MasterClass;
use InvalidArgumentException;

class Natal extends MasterClass
{
    public function gerarXmlLoteRps(array $dados): string
    {
        $this->validarLote($dados);
        $this->validarFormatosLote($dados);

        // ... existing code ...
        $xml .= '<EnviarLoteRpsEnvio xmlns="http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd">';
        $xml .= '<LoteRps Id="lote">';
        $xml .= '<NumeroLote>' . $this->onlyDigits((string)$dados['numeroLote']) . '</NumeroLote>';
        $xml .= '<Cnpj>' . $this->onlyDigits((string)$dados['cnpjPrestador']) . '</Cnpj>';
        $xml .= '<InscricaoMunicipal>' . $this->onlyDigits((string)$dados['inscricaoMunicipal']) . '</InscricaoMunicipal>';
        $xml .= '<QuantidadeRps>' . (int)$dados['quantidadeRps'] . '</QuantidadeRps>';
        $xml .= '<ListaRps>';

        foreach ($dados['rps'] as $rps) {
            $this->validarRps($rps);
            $this->validarFormatosRps($rps);
            $infId = (string)$rps['inf_id'];
            $xml .= '<Rps>';
            $xml .= '<InfRps Id="rps:'. $dados['rps'][0]['inf_id'] . '">';
            $xml .= '<IdentificacaoRps>';
            $xml .= '<Numero>' . $this->onlyDigits((string)$rps['infRps']['numero']) . '</Numero>';
            $xml .= '<Serie>' . $this->xmlSafeText((string)$rps['infRps']['serie']) . '</Serie>';
            $xml .= '<Tipo>' . (int)$rps['infRps']['tipo'] . '</Tipo>';
            $xml .= '</IdentificacaoRps>';
            $xml .= '<DataEmissao>' . $this->xmlSafeText((string)$rps['infRps']['dataEmissao']) . '</DataEmissao>';
            $xml .= '<NaturezaOperacao>' . $this->onlyDigits((string)$rps['naturezaOperacao']) . '</NaturezaOperacao>';
            if (isset($rps['regimeEspecialTributacao'])) {
                $xml .= '<RegimeEspecialTributacao>' . $this->onlyDigits((string)$rps['regimeEspecialTributacao']) . '</RegimeEspecialTributacao>';
            }
            $xml .= '<OptanteSimplesNacional>' . ((int)$rps['optanteSimplesNacional'] === 1 ? 1 : 2) . '</OptanteSimplesNacional>';
            $xml .= '<IncentivadorCultural>' . ((int)$rps['incentivadorCultural'] === 1 ? 1 : 2) . '</IncentivadorCultural>';
            $xml .= '<Status>' . (int)$rps['status'] . '</Status>';
            $xml .= '<Servico>';
            $xml .= '<Valores>';
            $xml .= '<ValorServicos>' . $this->fmtMoney($rps['valorServicos']) . '</ValorServicos>';
            if (isset($rps['valorPis'])) $xml .= '<ValorPis>' . $this->fmtMoney($rps['valorPis']) . '</ValorPis>';
            if (isset($rps['valorCofins'])) $xml .= '<ValorCofins>' . $this->fmtMoney($rps['valorCofins']) . '</ValorCofins>';
            if (isset($rps['valorInss'])) $xml .= '<ValorInss>' . $this->fmtMoney($rps['valorInss']) . '</ValorInss>';
            if (isset($rps['valorIr'])) $xml .= '<ValorIr>' . $this->fmtMoney($rps['valorIr']) . '</ValorIr>';
            if (isset($rps['valorCsll'])) $xml .= '<ValorCsll>' . $this->fmtMoney($rps['valorCsll']) . '</ValorCsll>';
            $xml .= '<IssRetido>' . ((int)($rps['issRetido'] ?? 2) === 1 ? 1 : 2) . '</IssRetido>';
            $xml .= '<ValorIss>' . $this->fmtMoney($rps['valorIss'] ?? 0) . '</ValorIss>';
            if (isset($rps['outrasRetencoes'])) $xml .= '<OutrasRetencoes>' . $this->fmtMoney($rps['outrasRetencoes']) . '</OutrasRetencoes>';
            $xml .= '<BaseCalculo>' . $this->fmtMoney($rps['baseCalculo'] ?? $rps['valorServicos']) . '</BaseCalculo>';
            $xml .= '<Aliquota>' . $this->fmtAliquota($rps['aliquota'] ?? 0) . '</Aliquota>';
            $xml .= '</Valores>';
            $xml .= '<ItemListaServico>' . $this->xmlSafeText($rps['itemListaServico']) . '</ItemListaServico>';
            if (isset($rps['codigoCnae'])) $xml .= '<CodigoCnae>' . $this->xmlSafeText($rps['codigoCnae']) . '</CodigoCnae>';
            $xml .= '<Discriminacao>' . $this->xmlSafeText((string)$rps['discriminacao']) . '</Discriminacao>';
            $xml .= '<CodigoMunicipio>' . $this->onlyDigits((string)$rps['codigoMunicipio']) . '</CodigoMunicipio>';
            $xml .= '</Servico>';
            // Prestador
            $xml .= '<Prestador>';
            $xml .= '<Cnpj>' . $this->onlyDigits((string)$dados['cnpjPrestador']) . '</Cnpj>';
            $xml .= '<InscricaoMunicipal>' . $this->onlyDigits((string)$dados['inscricaoMunicipal']) . '</InscricaoMunicipal>';
            $xml .= '</Prestador>';
            // Tomador
            $xml .= '<Tomador>';
            $xml .= '<IdentificacaoTomador>';
            $xml .= '<CpfCnpj>';
            $doc = $this->onlyDigits((string)$rps['tomador']['cpfCnpj']);
            if (strlen($doc) === 11) {
                $xml .= '<Cpf>' . $doc . '</Cpf>';
            } else {
                $xml .= '<Cnpj>' . $doc . '</Cnpj>';
            }
            $xml .= '</CpfCnpj>';
            if (isset($rps['tomador']['inscricaoMunicipal'])) $xml .= '<InscricaoMunicipal>' . $this->xmlSafeText((string)$rps['tomador']['inscricaoMunicipal']) . '</InscricaoMunicipal>';
            $xml .= '</IdentificacaoTomador>';
            $xml .= '<RazaoSocial>' . $this->xmlSafeText((string)$rps['tomador']['razaoSocial']) . '</RazaoSocial>';
            if (!empty($rps['tomador']['endereco']) && is_array($rps['tomador']['endereco'])) {
                $e = $rps['tomador']['endereco'];
                $xml .= '<Endereco>';
                $xml .= '<Endereco>' . $this->xmlSafeText((string)($e['logradouro'] ?? '')) . '</Endereco>';
                $xml .= '<Numero>' . $this->xmlSafeText((string)($e['numero'] ?? '')) . '</Numero>';
                if (isset($e['complemento'])) $xml .= '<Complemento>' . $this->xmlSafeText((string)$e['complemento']) . '</Complemento>';
                $xml .= '<Bairro>' . $this->xmlSafeText((string)($e['bairro'] ?? '')) . '</Bairro>';
                $xml .= '<CodigoMunicipio>' . $this->onlyDigits((string)($e['codigoMunicipio'] ?? '')) . '</CodigoMunicipio>';
                $xml .= '<Uf>' . $this->xmlSafeText((string)($e['uf'] ?? '')) . '</Uf>';
                $xml .= '<Cep>' . $this->onlyDigits((string)($e['cep'] ?? '')) . '</Cep>';
                $xml .= '</Endereco>';
            }
            if (isset($rps['tomador']['contato'])) {
                $c = $rps['tomador']['contato'];
                $xml .= '<Contato>';
                if (isset($c['telefone'])) $xml .= '<Telefone>' . $this->xmlSafeText((string)$c['telefone']) . '</Telefone>';
                if (isset($c['email'])) $xml .= '<Email>' . $this->xmlSafeText((string)$c['email']) . '</Email>';
                $xml .= '</Contato>';
            }
            $xml .= '</Tomador>';
            // ConstrucaoCivil (opcional)
            if (isset($rps['construcaoCivil'])) {
                $cc = $rps['construcaoCivil'];
                $xml .= '<ConstrucaoCivil>';
                if (isset($cc['codigoObra'])) $xml .= '<CodigoObra>' . $this->xmlSafeText((string)$cc['codigoObra']) . '</CodigoObra>';
                if (isset($cc['art'])) $xml .= '<Art>' . $this->xmlSafeText((string)$cc['art']) . '</Art>';
                $xml .= '</ConstrucaoCivil>';
            }
            // IBSCBS (opcional - reforma tributária)
            if (isset($rps['IBSCBS']) && is_array($rps['IBSCBS'])) {
                $xml .= "<IBSCBS>";
                foreach ($rps['IBSCBS'] as $key => $value) {
                    if (is_array($value)) {
                        $xml .= "<{$key}>";
                        foreach ($value as $subKey => $subValue) {
                            if (is_array($subValue)) {
                                $xml .= "<{$subKey}>";
                                foreach ($subValue as $subSubKey => $subSubValue) {
                                    $xml .= "<{$subSubKey}>" . htmlspecialchars((string)$subSubValue, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</{$subSubKey}>";
                                }
                                $xml .= "</{$subKey}>";
                            } else {
                                $xml .= "<{$subKey}>" . htmlspecialchars((string)$subValue, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</{$subKey}>";
                            }
                        }
                        $xml .= "</{$key}>";
                    } else {
                        $xml .= "<{$key}>" . htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</{$key}>";
                    }
                }
                $xml .= "</IBSCBS>";
            }
            $xml .= '</InfRps>';
            // Signature do RPS
            if (isset($rps['signature'])) {
                $xml .= $rps['signature'];
            }
            $xml .= '</Rps>';
        }
        $xml .= '</ListaRps>';
        $xml .= '</LoteRps>';
        // Signature do lote
        if (isset($dados['signatureLote'])) {
            $xml .= $dados['signatureLote'];
        }
        $xml .= '</EnviarLoteRpsEnvio>';
        $xml = preg_replace('/>\s+</', '><', $xml); // minifica: remove espaços/quebras entre tags
        $this->assertXmlOk($xml);
        return $xml;
    }

    /**
     * Valida campos obrigatórios e formato do lote.
     */
    private function validarFormatosLote(array $d): void
    {
        // CNPJ Prestador
        if (!isset($d['cnpjPrestador']) || !$this->isCnpjValido($d['cnpjPrestador'])) {
            throw new InvalidArgumentException("CNPJ do prestador inválido ou não informado.");
        }
        // Inscrição Municipal Prestador
        if (!isset($d['inscricaoMunicipal']) || !$this->isInscricaoMunicipalValida($d['inscricaoMunicipal'])) {
            throw new InvalidArgumentException("Inscrição municipal do prestador inválida ou não informada.");
        }
        // Numero Lote
        if (!isset($d['numeroLote']) || !is_numeric($d['numeroLote'])) {
            throw new InvalidArgumentException("Número do lote inválido ou não informado.");
        }
        // Quantidade RPS
        if (!isset($d['quantidadeRps']) || !is_numeric($d['quantidadeRps']) || $d['quantidadeRps'] < 1) {
            throw new InvalidArgumentException("Quantidade de RPS inválida ou não informada.");
        }
        // Valida cada RPS
        foreach ($d['rps'] as $rps) {
            $this->validarFormatosRps($rps);
        }
    }

    /**
     * Valida campos obrigatórios e formato do RPS.
     */
    private function validarFormatosRps(array $rps): void
    {
        // inf_id
        if (!isset($rps['inf_id']) || empty($rps['inf_id'])) {
            throw new InvalidArgumentException("inf_id do RPS não informado.");
        }
        // infRps
        if (!isset($rps['infRps']) || !is_array($rps['infRps'])) {
            throw new InvalidArgumentException("infRps do RPS não informado.");
        }
        $inf = $rps['infRps'];
        // Numero
        if (!isset($inf['numero']) || !is_numeric($inf['numero'])) {
            throw new InvalidArgumentException("Número do RPS inválido ou não informado.");
        }
        // Serie
        if (!isset($inf['serie']) || empty($inf['serie'])) {
            throw new InvalidArgumentException("Série do RPS não informada.");
        }
        // Tipo
        if (!isset($inf['tipo']) || !in_array($inf['tipo'], [1,2,3])) {
            throw new InvalidArgumentException("Tipo do RPS inválido ou não informado.");
        }
        // Data Emissão
        if (!isset($inf['dataEmissao']) || !$this->isDataValida($inf['dataEmissao'])) {
            throw new InvalidArgumentException("Data de emissão do RPS inválida ou não informada.");
        }
        // Valor Serviços
        if (!isset($rps['valorServicos']) || !is_numeric($rps['valorServicos'])) {
            throw new InvalidArgumentException("Valor dos serviços inválido ou não informado.");
        }
        // Tomador
        if (!isset($rps['tomador']) || !is_array($rps['tomador'])) {
            throw new InvalidArgumentException("Tomador do RPS não informado.");
        }
        $tom = $rps['tomador'];
        // CPF/CNPJ Tomador
        if (!isset($tom['cpfCnpj']) || !$this->isCpfCnpjValido($tom['cpfCnpj'])) {
            throw new InvalidArgumentException("CPF/CNPJ do tomador inválido ou não informado.");
        }
        // Razão Social Tomador
        if (!isset($tom['razaoSocial']) || empty($tom['razaoSocial'])) {
            throw new InvalidArgumentException("Razão social do tomador não informada.");
        }
        // Endereço Tomador
        if (!isset($tom['endereco']) || !is_array($tom['endereco'])) {
            throw new InvalidArgumentException("Endereço do tomador não informado.");
        }
        $end = $tom['endereco'];
        foreach ([
            'logradouro', 'numero', 'bairro', 'codigoMunicipio', 'uf', 'cep'
        ] as $k) {
            if (!isset($end[$k]) || empty($end[$k])) {
                throw new InvalidArgumentException("Endereço do tomador: campo obrigatório '{$k}' não informado.");
            }
        }
    }

    /**
     * Valida formato de CNPJ.
     */
    protected function isCnpjValido($cnpj): bool
    {
        $cnpj = preg_replace('/\D+/', '', $cnpj);
        return (strlen($cnpj) === 14);
    }
    /**
     * Valida formato de Inscrição Municipal (mínimo 1 caractere).
     */
    protected function isInscricaoMunicipalValida($im): bool
    {
        return (is_string($im) && strlen(trim($im)) > 0);
    }
    /**
     * Valida formato de data (YYYY-MM-DD ou YYYY-MM-DDTHH:MM:SS±HH:MM).
     */
    protected function isDataValida($data): bool
    {
        return (bool)preg_match('/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}:\d{2}([+-]\d{2}:\d{2})?)?$/', $data);
    }
    /**
     * Valida formato de CPF ou CNPJ.
     */
    protected function isCpfCnpjValido($doc): bool
    {
        $doc = preg_replace('/\D+/', '', $doc);
        return (strlen($doc) === 11 || strlen($doc) === 14);
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

    protected function onlyDigits(string $s): string
    {
        return preg_replace('/\D+/', '', $s) ?? '';
    }

    protected function xmlSafeText(string $s): string
    {
        // garante UTF-8 válido
        $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
        // remove caracteres inválidos no XML 1.0
        $s = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $s);
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    protected function xmlSafeId(string $s): string
    {
        $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
        $s = preg_replace('/[^\w\-\:\.]+/u', '', $s); // mantém letras, números, _, -, :, .
        return $s ?: '0';
    }

    protected function fmtMoney($v): string
    {
        return number_format((float)$v, 2, '.', '');
    }

    protected function fmtAliquota($v): string
    {
        return number_format((float)$v, 2, '.', '');
    }
}