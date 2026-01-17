<?php
namespace NFSePrefeitura\NFSe;

use SoapClient;
use SoapFault;
use Exception;

/**
 * PortoSeguro - ABRASF v2.02 (GestaoISS) - Porto Seguro/BA
 *
 * Gera XML do EnviarLoteRpsEnvio (sem assinatura).
 * Foco: passar no XSD do provedor (datas, padrões, enums e ordem).
 */
class PortoSeguro
{
    private const WSDL = 'https://portoseguroba.gestaoiss.com.br/ws/nfse.asmx?WSDL';

    private string $certPath;
    private string $certPassword;

    public function __construct(string $certPath, string $certPassword)
    {
        $this->certPath     = $certPath;
        $this->certPassword = $certPassword;
    }

    /* =========================================================
     * SOAP CLIENT
     * ========================================================= */
    private function client(): SoapClient
    {
        return new SoapClient(self::WSDL, [
            'soap_version' => SOAP_1_1,
            'trace'        => true,
            'exceptions'   => true,
            'cache_wsdl'   => WSDL_CACHE_NONE,
            'encoding'     => 'UTF-8',
            'stream_context' => stream_context_create([
                'ssl' => [
                    'local_cert'        => $this->certPath,
                    'passphrase'        => $this->certPassword,
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ]
            ]),
        ]);
    }

    private function cabecMsg(string $versao = '2.02'): string
    {
        return '<cabecalho xmlns="http://www.abrasf.org.br/nfse.xsd" versao="'.$this->xmlEscape($versao).'">'
             . '<versaoDados>'.$this->xmlEscape($versao).'</versaoDados>'
             . '</cabecalho>';
    }

    public function recepcionarLoteRps(array $dados, string $versao = '2.02'): object
    {
        $client   = $this->client();
        $xmlDados = $this->gerarXmlLoteRps($dados, $versao);
        $cabec    = $this->cabecMsg($versao);

        try {
            return $client->__soapCall('RecepcionarLoteRps', [[
                'nfseCabecMsg' => $cabec,
                'nfseDadosMsg' => $xmlDados,
            ]]);
        } catch (SoapFault $e) {
            throw new Exception(
                "ERRO SOAP:\n{$e->getMessage()}\n\nREQUEST:\n{$client->__getLastRequest()}\n\nRESPONSE:\n{$client->__getLastResponse()}",
                0,
                $e
            );
        }
    }

    /* =========================================================
     * XML - ENVIAR LOTE RPS (ABRASF 2.02)
     * ========================================================= */
    public function gerarXmlLoteRps(array $dados, string $versao = '2.02'): string
    {
        $loteId             = (string)$this->required($dados, 'lote_id');
        $numeroLote         = (string)$this->required($dados, 'numeroLote');
        $cnpjPrestador      = $this->onlyDigits((string)$this->required($dados, 'cnpjPrestador'));
        $inscricaoMunicipal = (string)$this->required($dados, 'inscricaoMunicipal');
        $rpsList            = $this->required($dados, 'rps');

        if (strlen($cnpjPrestador) !== 14) {
            throw new Exception("cnpjPrestador inválido (precisa 14 dígitos). Recebido: {$cnpjPrestador}");
        }
        if (!is_array($rpsList) || count($rpsList) < 1) {
            throw new Exception("Lista de RPS vazia/inválida em dados['rps'].");
        }

        $quantidadeRps = (int)($dados['quantidadeRps'] ?? count($rpsList));
        if ($quantidadeRps !== count($rpsList)) {
            $quantidadeRps = count($rpsList);
        }

        $x  = '<?xml version="1.0" encoding="utf-8"?>';
        $x .= '<EnviarLoteRpsEnvio xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
            .  'xmlns:xsd="http://www.w3.org/2001/XMLSchema" '
            .  'xmlns="http://www.abrasf.org.br/nfse.xsd">';
        $x .= '<LoteRps Id="'.$this->xmlEscape($loteId).'" versao="'.$this->xmlEscape($versao).'">';
        $x .= '<NumeroLote>'.$this->xmlEscape($numeroLote).'</NumeroLote>';
        $x .= '<CpfCnpj><Cnpj>'.$this->xmlEscape($cnpjPrestador).'</Cnpj></CpfCnpj>';
        $x .= '<InscricaoMunicipal>'.$this->xmlEscape($inscricaoMunicipal).'</InscricaoMunicipal>';
        $x .= '<QuantidadeRps>'.$this->xmlEscape((string)$quantidadeRps).'</QuantidadeRps>';
        $x .= '<ListaRps>';

        foreach ($rpsList as $idx => $rps) {
            if (!is_array($rps)) {
                throw new Exception("RPS na posição {$idx} não é array.");
            }
            $x .= $this->xmlRps($rps, $dados);
        }

        $x .= '</ListaRps>';
        $x .= '</LoteRps>';
        $x .= '</EnviarLoteRpsEnvio>';

        return $x;
    }

    private function xmlRps(array $rps, array $loteDados): string
    {
        $infId  = (string)$this->required($rps, 'inf_id');

        $infRps = $this->required($rps, 'infRps');
        if (!is_array($infRps)) {
            throw new Exception("infRps inválido no RPS {$infId}.");
        }

        $numero = (string)$this->required($infRps, 'numero');
        $serie  = (string)$this->required($infRps, 'serie');
        $tipo   = (string)$this->required($infRps, 'tipo');

        // XSD (muito comum em GestaoISS): DataEmissao/Competencia como xsd:date => YYYY-MM-DD
        $dataEmissao = $this->normalizeDateYmd((string)($infRps['dataEmissao'] ?? ''), true, "DataEmissao (RPS {$infId})");
        $competencia = $this->normalizeDateYmd((string)($rps['competencia'] ?? ''), true, "Competencia (RPS {$infId})");

        // Serviço
        $valorServicos    = $this->money($rps['valorServicos'] ?? null, true, "valorServicos (RPS {$infId})");
        $valorIss         = $this->money($rps['valorIss'] ?? 0);
        $aliquota         = $this->aliquotaToDecimal4($rps['aliquota'] ?? 0); // 2 => 0.0200

        $issRetido        = $this->enum12($rps['issRetido'] ?? null, "IssRetido (RPS {$infId})");
        $itemListaServico = $this->normalizeItemListaServico((string)($rps['itemListaServico'] ?? ''), "ItemListaServico (RPS {$infId})");

        $discriminacao    = (string)$this->required($rps, 'discriminacao');
        $codigoMunicipio  = $this->normalizeIbge7((string)($rps['codigoMunicipio'] ?? ''), "CodigoMunicipio (RPS {$infId})");
        $exigibilidadeISS = $this->digitsOnlyReq($rps['exigibilidadeISS'] ?? null, "ExigibilidadeISS (RPS {$infId})");

        // opcionais (só se vierem)
        $codigoCnae              = $this->tagIfDigits('CodigoCnae', $rps['codigoCnae'] ?? null);
        $codigoTributacaoMun     = $this->tagIfText('CodigoTributacaoMunicipio', $rps['codigoTributacaoMunicipio'] ?? null);

        // se o provedor NÃO tiver essa tag no schema, REMOVA (deixe vazio sempre).
        $municipioIncidencia = '';
        if (!empty($rps['municipioIncidencia'])) {
            $municipioIncidencia = '<MunicipioIncidencia>'
                . $this->xmlEscape($this->normalizeIbge7((string)$rps['municipioIncidencia'], "MunicipioIncidencia (RPS {$infId})"))
                . '</MunicipioIncidencia>';
        }

        // Tomador
        $tomador = $this->required($rps, 'tomador');
        if (!is_array($tomador)) {
            throw new Exception("tomador inválido no RPS {$infId}.");
        }

        $docTomadorXml = $this->xmlCpfCnpj((string)($tomador['cpfCnpj'] ?? ''), "tomador.cpfCnpj (RPS {$infId})");
        $razaoSocial   = (string)$this->required($tomador, 'razaoSocial');

        $end = $this->required($tomador, 'endereco');
        if (!is_array($end)) {
            throw new Exception("tomador.endereco obrigatório/ inválido (RPS {$infId}).");
        }

        $logradouro    = (string)$this->required($end, 'logradouro');
        $numeroEnd     = (string)$this->required($end, 'numero');
        $bairro        = (string)$this->required($end, 'bairro');
        $codMunTomador = $this->normalizeIbge7((string)($end['codigoMunicipio'] ?? ''), "tomador.endereco.codigoMunicipio (RPS {$infId})");
        $uf            = (string)$this->required($end, 'uf');
        $cep           = $this->onlyDigits((string)$this->required($end, 'cep'));
        if (strlen($cep) !== 8) {
            throw new Exception("tomador.endereco.cep inválido (8 dígitos). Recebido: {$cep} (RPS {$infId})");
        }

        $telefone = $this->onlyDigits((string)($tomador['telefone'] ?? ''));
        $email    = (string)($tomador['email'] ?? '');

        // Regimes (normalize!)
        $regimeEspecial = $this->normalizeRegimeEspecialTributacao($rps['regimeEspecialTributacao'] ?? null); // '' ou 1..7
        $optSimples     = $this->normalizeSimNao12($rps['optanteSimplesNacional'] ?? null); // 1/2
        $incentivo      = $this->normalizeSimNao12($rps['incentivoFiscal'] ?? null); // 1/2

        $cnpjPrestadorLote = $this->onlyDigits((string)($loteDados['cnpjPrestador'] ?? ''));
        $inscMunPrestador  = (string)($loteDados['inscricaoMunicipal'] ?? '');

        $x  = '<Rps>';
        $x .= '<InfDeclaracaoPrestacaoServico Id="'.$this->xmlEscape($infId).'">';

        $x .= '<Rps>';
        $x .= '<IdentificacaoRps>';
        $x .= '<Numero>'.$this->xmlEscape($numero).'</Numero>';
        $x .= '<Serie>'.$this->xmlEscape($serie).'</Serie>';
        $x .= '<Tipo>'.$this->xmlEscape($tipo).'</Tipo>';
        $x .= '</IdentificacaoRps>';
        $x .= '<DataEmissao>'.$this->xmlEscape($dataEmissao).'</DataEmissao>';
        $x .= '<Status>1</Status>';
        $x .= '</Rps>';

        $x .= '<Competencia>'.$this->xmlEscape($competencia).'</Competencia>';

        // ========= SERVICO (ordem importa no XSD) =========
        $x .= '<Servico>';
        $x .= '<Valores>';
        $x .= '<ValorServicos>'.$this->xmlEscape($valorServicos).'</ValorServicos>';
        $x .= '<ValorIss>'.$this->xmlEscape($valorIss).'</ValorIss>';
        $x .= '<Aliquota>'.$this->xmlEscape($aliquota).'</Aliquota>';
        $x .= '</Valores>';

        $x .= '<IssRetido>'.$this->xmlEscape($issRetido).'</IssRetido>';
        $x .= '<ItemListaServico>'.$this->xmlEscape($itemListaServico).'</ItemListaServico>';
        $x .= $codigoCnae;
        $x .= $codigoTributacaoMun;

        $x .= '<Discriminacao>'.$this->xmlEscape($discriminacao).'</Discriminacao>';

        $x .= '<CodigoMunicipio>'.$this->xmlEscape($codigoMunicipio).'</CodigoMunicipio>';
        $x .= '<ExigibilidadeISS>'.$this->xmlEscape($exigibilidadeISS).'</ExigibilidadeISS>';

        // se o schema do seu provedor não tiver isso, mantenha SEMPRE vazio
        $x .= $municipioIncidencia;

        $x .= '</Servico>';

        // ========= PRESTADOR =========
        $x .= '<Prestador>';
        $x .= '<CpfCnpj><Cnpj>'.$this->xmlEscape($cnpjPrestadorLote).'</Cnpj></CpfCnpj>';
        $x .= '<InscricaoMunicipal>'.$this->xmlEscape($inscMunPrestador).'</InscricaoMunicipal>';
        $x .= '</Prestador>';

        // ========= TOMADOR =========
        $x .= '<Tomador>';
        $x .= '<IdentificacaoTomador>';
        $x .= $docTomadorXml;
        $x .= $this->tagIfText('InscricaoMunicipal', $tomador['inscricaoMunicipal'] ?? null);
        $x .= '</IdentificacaoTomador>';

        $x .= '<RazaoSocial>'.$this->xmlEscape($razaoSocial).'</RazaoSocial>';

        $x .= '<Endereco>';
        $x .= '<Endereco>'.$this->xmlEscape($logradouro).'</Endereco>';
        $x .= '<Numero>'.$this->xmlEscape($numeroEnd).'</Numero>';
        $x .= '<Bairro>'.$this->xmlEscape($bairro).'</Bairro>';
        $x .= '<CodigoMunicipio>'.$this->xmlEscape($codMunTomador).'</CodigoMunicipio>';
        $x .= '<Uf>'.$this->xmlEscape($uf).'</Uf>';
        $x .= '<Cep>'.$this->xmlEscape($cep).'</Cep>';
        $x .= '</Endereco>';

        $contato = '';
        $contato .= $this->tagIfText('Telefone', $telefone);
        $contato .= $this->tagIfText('Email', $email);
        if ($contato !== '') {
            $x .= '<Contato>'.$contato.'</Contato>';
        }

        $x .= '</Tomador>';

        // ========= REGIMES (muitos provedores rejeitam 0) =========
        if ($regimeEspecial !== '') {
            $x .= '<RegimeEspecialTributacao>'.$this->xmlEscape($regimeEspecial).'</RegimeEspecialTributacao>';
        }
        $x .= '<OptanteSimplesNacional>'.$this->xmlEscape($optSimples).'</OptanteSimplesNacional>';
        $x .= '<IncentivoFiscal>'.$this->xmlEscape($incentivo).'</IncentivoFiscal>';

        $x .= '</InfDeclaracaoPrestacaoServico>';
        $x .= '</Rps>';

        return $x;
    }

    /* =========================================================
     * HELPERS
     * ========================================================= */

    private function required(array $arr, string $key)
    {
        if (!array_key_exists($key, $arr)) {
            throw new Exception("Campo obrigatório ausente: {$key}");
        }
        $v = $arr[$key];
        if ($v === null) {
            throw new Exception("Campo obrigatório nulo: {$key}");
        }
        if (is_string($v) && trim($v) === '') {
            throw new Exception("Campo obrigatório vazio: {$key}");
        }
        return $v;
    }

    private function onlyDigits(string $v): string
    {
        return preg_replace('/\D+/', '', $v) ?? '';
    }

    private function xmlEscape(string $v): string
    {
        return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function tagIfText(string $tag, $value): string
    {
        $v = trim((string)$value);
        if ($v === '') return '';
        return "<{$tag}>{$this->xmlEscape($v)}</{$tag}>";
    }

    private function tagIfDigits(string $tag, $value): string
    {
        $v = $this->onlyDigits((string)$value);
        if ($v === '') return '';
        return "<{$tag}>{$this->xmlEscape($v)}</{$tag}>";
    }

    private function xmlCpfCnpj(string $docRaw, string $labelForError): string
    {
        $doc = $this->onlyDigits($docRaw);
        if (strlen($doc) === 11) return "<CpfCnpj><Cpf>{$this->xmlEscape($doc)}</Cpf></CpfCnpj>";
        if (strlen($doc) === 14) return "<CpfCnpj><Cnpj>{$this->xmlEscape($doc)}</Cnpj></CpfCnpj>";
        throw new Exception("Documento inválido em {$labelForError}. CPF(11) ou CNPJ(14). Recebido: {$doc}");
    }

    private function money($v, bool $required = false, string $label = 'valor'): string
    {
        if ($v === null || $v === '') {
            if ($required) throw new Exception("Campo {$label} obrigatório.");
            return number_format(0, 2, '.', '');
        }

        $s = trim((string)$v);

        // aceita 1.234,56 e 1234.56
        if (preg_match('/,\d{1,2}$/', $s)) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        }

        if (!is_numeric($s)) throw new Exception("Valor monetário inválido em {$label}: {$v}");

        return number_format((float)$s, 2, '.', '');
    }

    /**
     * Aliquota no XML costuma ser decimal (ex.: 2% => 0.0200).
     * Se vier 2 ou 2.0 ou 2,00 => converte para 0.0200.
     * Se vier 0.02 => mantém.
     */
    private function aliquotaToDecimal4($v): string
    {
        $s = trim((string)$v);
        if ($s === '') return number_format(0, 4, '.', '');

        if (preg_match('/,\d+$/', $s)) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        }
        if (!is_numeric($s)) throw new Exception("Aliquota inválida: {$v}");

        $f = (float)$s;

        // heurística: se > 1, veio em percentual (2 => 2%), converte.
        if ($f > 1) {
            $f = $f / 100.0;
        }

        return number_format($f, 4, '.', '');
    }

    /**
     * Normaliza para YYYY-MM-DD (xsd:date).
     * Aceita: YYYY-MM-DD, YYYY-MM-DDTHH:MM:SS, YYYYMMDD, etc.
     */
    private function normalizeDateYmd(string $raw, bool $required = false, string $label = 'data'): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            if ($required) throw new Exception("{$label} obrigatória não informada.");
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw)) {
            return substr($raw, 0, 10);
        }

        if (preg_match('/^\d{8}$/', $raw)) {
            return substr($raw, 0, 4).'-'.substr($raw, 4, 2).'-'.substr($raw, 6, 2);
        }

        $ts = strtotime($raw);
        if ($ts === false) throw new Exception("{$label} inválida: {$raw}");
        return date('Y-m-d', $ts);
    }

    private function normalizeIbge7(string $raw, string $label): string
    {
        $v = $this->onlyDigits($raw);
        if (strlen($v) !== 7) throw new Exception("{$label} inválido (IBGE 7 dígitos). Recebido: {$raw}");
        return $v;
    }

    private function digitsOnlyReq($v, string $label): string
    {
        $x = $this->onlyDigits((string)$v);
        if ($x === '') throw new Exception("{$label} obrigatório.");
        return $x;
    }

    private function enum12($v, string $label): string
    {
        $x = $this->onlyDigits((string)$v);
        if ($x !== '1' && $x !== '2') throw new Exception("{$label} inválido (use 1 ou 2). Recebido: ".(string)$v);
        return $x;
    }

    /**
     * ItemListaServico no formato XX.YY (ex.: 07.10)
     * Se vier 071001 => vira 07.10
     */
    private function normalizeItemListaServico(string $raw, string $label): string
    {
        $raw = trim($raw);
        if ($raw === '') throw new Exception("{$label} obrigatório.");

        // se já está como 07.10 ou 7.10
        if (preg_match('/^\d{1,2}\.\d{2}$/', $raw)) {
            $p = explode('.', $raw);
            return str_pad($p[0], 2, '0', STR_PAD_LEFT).'.'.$p[1];
        }

        $d = $this->onlyDigits($raw);
        if (strlen($d) >= 4) {
            $xx = substr($d, 0, 2);
            $yy = substr($d, 2, 2);
            return $xx.'.'.$yy;
        }

        throw new Exception("{$label} inválido. Esperado XX.YY (ex 07.10). Recebido: {$raw}");
    }

    /**
     * RegimeEspecialTributacao: muitos XSD aceitam 1..6 (às vezes 7).
     * 0/vazio => omite.
     */
    private function normalizeRegimeEspecialTributacao($v): string
    {
        $x = $this->onlyDigits((string)$v);
        if ($x === '' || $x === '0') return '';
        $n = (int)$x;
        if ($n >= 1 && $n <= 7) return (string)$n;
        return ''; // melhor omitir do que reprovar enum
    }

    /**
     * OptanteSimplesNacional / IncentivoFiscal: 1=Sim, 2=Não (muitos provedores rejeitam 0).
     * default: 2
     */
    private function normalizeSimNao12($v): string
    {
        $x = $this->onlyDigits((string)$v);
        if ($x === '1') return '1';
        return '2';
    }
}
