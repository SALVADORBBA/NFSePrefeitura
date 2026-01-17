<?php
namespace NFSePrefeitura\NFSe;

use SoapClient;
use SoapFault;
use Exception;
use DateTime;
use DateTimeZone;

/**
 * PortoSeguro - ABRASF v2.02 (GestaoISS) - Porto Seguro/BA
 *
 * Objetivo:
 * - Gerar XML do Lote RPS (EnviarLoteRpsEnvio) SEM assinatura (assinar externamente).
 * - Enviar via SOAP usando:
 *   - RecepcionarLoteRps
 *   - RecepcionarLoteRpsSincrono
 *
 * Ajustes importantes:
 * - Evita tags vazias (schema costuma rejeitar).
 * - CPF/CNPJ do tomador correto.
 * - DataEmissao e Competencia geradas no formato YYYYMMDD (8 dígitos) para passar no schema do provedor.
 * - Formatação de valores (2 casas) e alíquota (4 casas).
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

    /* =========================================================
     * CABEÇALHO ABRASF (nfseCabecMsg)
     * ========================================================= */
    private function cabecMsg(string $versao = '2.02'): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<cabecalho xmlns="http://www.abrasf.org.br/nfse.xsd" versao="' . $this->xmlEscape($versao) . '">'
            . '<versaoDados>' . $this->xmlEscape($versao) . '</versaoDados>'
            . '</cabecalho>';
    }

    /* =========================================================
     * ENVIO - RECEPCIONAR LOTE (ASSÍNCRONO)
     * ========================================================= */
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
            throw $this->wrapSoapFault($e, $client);
        }
    }

    /* =========================================================
     * ENVIO - RECEPCIONAR LOTE (SÍNCRONO)
     * ========================================================= */
    public function recepcionarLoteRpsSincrono(array $dados, string $versao = '2.02'): object
    {
        $client   = $this->client();
        $xmlDados = $this->gerarXmlLoteRps($dados, $versao);
        $cabec    = $this->cabecMsg($versao);

        try {
            return $client->__soapCall('RecepcionarLoteRpsSincrono', [[
                'nfseCabecMsg' => $cabec,
                'nfseDadosMsg' => $xmlDados,
            ]]);
        } catch (SoapFault $e) {
            throw $this->wrapSoapFault($e, $client);
        }
    }

    private function wrapSoapFault(SoapFault $e, SoapClient $client): Exception
    {
        return new Exception(
            "ERRO SOAP:\n{$e->getMessage()}\n\nREQUEST:\n{$client->__getLastRequest()}\n\nRESPONSE:\n{$client->__getLastResponse()}",
            0,
            $e
        );
    }

    /* =========================================================
     * XML - ENVIAR LOTE RPS (ABRASF 2.02)
     * ========================================================= */
    public function gerarXmlLoteRps(array $dados, string $versao = '2.02'): string
    {
        // ------- validações mínimas do lote -------
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
        $x .= '<LoteRps Id="' . $this->xmlEscape($loteId) . '" versao="' . $this->xmlEscape($versao) . '">';
        $x .= '<NumeroLote>' . $this->xmlEscape($numeroLote) . '</NumeroLote>';
        $x .= '<CpfCnpj><Cnpj>' . $this->xmlEscape($cnpjPrestador) . '</Cnpj></CpfCnpj>';
        $x .= '<InscricaoMunicipal>' . $this->xmlEscape($inscricaoMunicipal) . '</InscricaoMunicipal>';
        $x .= '<QuantidadeRps>' . $this->xmlEscape((string)$quantidadeRps) . '</QuantidadeRps>';
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

        // ✅ FORÇA PADRÃO YYYYMMDD (8 dígitos) para passar no schema do provedor
        $dataEmissao = $this->normalizeDateYmd((string)($infRps['dataEmissao'] ?? ''), true);
        $competencia = $this->normalizeDateYmd((string)($rps['competencia'] ?? ''), true);

        // Serviço
        $valorServicos    = $this->money($rps['valorServicos'] ?? null, true, "valorServicos (RPS {$infId})");
        $valorIss         = $this->money($rps['valorIss'] ?? 0);
        $aliquota         = $this->percent($rps['aliquota'] ?? 0, 4);

        $issRetido        = (string)$this->required($rps, 'issRetido'); // 1/2
        $itemListaServico = (string)$this->required($rps, 'itemListaServico');

        $discriminacao = (string)($rps['discriminacao'] ?? '');
        $discriminacao = $this->normalizeDiscriminacao($discriminacao, $infId);

        $codigoMunicipio  = (string)$this->required($rps, 'codigoMunicipio');
        $exigibilidadeISS = (string)$this->required($rps, 'exigibilidadeISS');

        // Tomador
        $tomador = $this->required($rps, 'tomador');
        if (!is_array($tomador)) {
            throw new Exception("tomador inválido no RPS {$infId}.");
        }

        $docTomadorRaw = (string)($tomador['cpfCnpj'] ?? '');
        $docTomadorXml = $this->xmlCpfCnpj($docTomadorRaw, "tomador.cpfCnpj (RPS {$infId})");

        $razaoSocial = trim((string)($tomador['razaoSocial'] ?? ''));
        if ($razaoSocial === '') {
            throw new Exception("tomador.razaoSocial obrigatório (RPS {$infId}).");
        }

        $end = $tomador['endereco'] ?? null;
        if (!is_array($end)) {
            throw new Exception("tomador.endereco obrigatório/ inválido (RPS {$infId}).");
        }

        $logradouro    = (string)$this->required($end, 'logradouro');
        $numeroEnd     = (string)$this->required($end, 'numero');
        $bairro        = (string)$this->required($end, 'bairro');
        $codMunTomador = (string)$this->required($end, 'codigoMunicipio');
        $uf            = (string)$this->required($end, 'uf');
        $cep           = $this->onlyDigits((string)$this->required($end, 'cep'));
        if (strlen($cep) !== 8) {
            throw new Exception("tomador.endereco.cep inválido (precisa 8 dígitos). Recebido: {$cep} (RPS {$infId})");
        }

        $telefone = $this->onlyDigits((string)($tomador['telefone'] ?? ''));
        $email    = trim((string)($tomador['email'] ?? ''));

        // Regimes (não mandar vazio)
        $regimeEspecialTributacao = trim((string)($rps['regimeEspecialTributacao'] ?? '0'));
        $optanteSimplesNacional   = trim((string)($rps['optanteSimplesNacional'] ?? '2'));
        $incentivoFiscal          = trim((string)($rps['incentivoFiscal'] ?? '2'));

        // Prestador do lote
        $cnpjPrestadorLote = $this->onlyDigits((string)($loteDados['cnpjPrestador'] ?? ''));
        $inscMunLote       = (string)($loteDados['inscricaoMunicipal'] ?? '');

        $x  = '<Rps>';
        $x .= '<InfDeclaracaoPrestacaoServico Id="' . $this->xmlEscape($infId) . '">';

        $x .= '<Rps>';
        $x .= '<IdentificacaoRps>';
        $x .= '<Numero>' . $this->xmlEscape($numero) . '</Numero>';
        $x .= '<Serie>' . $this->xmlEscape($serie) . '</Serie>';
        $x .= '<Tipo>' . $this->xmlEscape($tipo) . '</Tipo>';
        $x .= '</IdentificacaoRps>';
        $x .= '<DataEmissao>' . $this->xmlEscape($dataEmissao) . '</DataEmissao>';
        $x .= '<Status>1</Status>';
        $x .= '</Rps>';

        $x .= '<Competencia>' . $this->xmlEscape($competencia) . '</Competencia>';

        $x .= '<Servico>';
        $x .= '<Valores>';
        $x .= '<ValorServicos>' . $this->xmlEscape($valorServicos) . '</ValorServicos>';
        $x .= $this->tag('ValorIss', $valorIss);
        $x .= $this->tag('Aliquota', $aliquota);
        $x .= '</Valores>';

        $x .= '<IssRetido>' . $this->xmlEscape($issRetido) . '</IssRetido>';
        $x .= '<ItemListaServico>' . $this->xmlEscape($itemListaServico) . '</ItemListaServico>';
        $x .= '<Discriminacao>' . $this->xmlEscape($discriminacao) . '</Discriminacao>';
        $x .= '<CodigoMunicipio>' . $this->xmlEscape($codigoMunicipio) . '</CodigoMunicipio>';
        $x .= '<ExigibilidadeISS>' . $this->xmlEscape($exigibilidadeISS) . '</ExigibilidadeISS>';
        $x .= '</Servico>';

        $x .= '<Prestador>';
        $x .= '<CpfCnpj><Cnpj>' . $this->xmlEscape($cnpjPrestadorLote) . '</Cnpj></CpfCnpj>';
        $x .= '<InscricaoMunicipal>' . $this->xmlEscape($inscMunLote) . '</InscricaoMunicipal>';
        $x .= '</Prestador>';

        $x .= '<Tomador>';
        $x .= '<IdentificacaoTomador>';
        $x .= $docTomadorXml;
        $x .= $this->tagIf('InscricaoMunicipal', (string)($tomador['inscricaoMunicipal'] ?? ''));
        $x .= '</IdentificacaoTomador>';

        $x .= '<RazaoSocial>' . $this->xmlEscape($razaoSocial) . '</RazaoSocial>';

        $x .= '<Endereco>';
        $x .= '<Endereco>' . $this->xmlEscape($logradouro) . '</Endereco>';
        $x .= '<Numero>' . $this->xmlEscape($numeroEnd) . '</Numero>';
        $x .= '<Bairro>' . $this->xmlEscape($bairro) . '</Bairro>';
        $x .= '<CodigoMunicipio>' . $this->xmlEscape($codMunTomador) . '</CodigoMunicipio>';
        $x .= '<Uf>' . $this->xmlEscape($uf) . '</Uf>';
        $x .= '<Cep>' . $this->xmlEscape($cep) . '</Cep>';
        $x .= '</Endereco>';

        $contato = '';
        $contato .= $this->tagIf('Telefone', $telefone);
        $contato .= $this->tagIf('Email', $email);
        if ($contato !== '') {
            $x .= '<Contato>' . $contato . '</Contato>';
        }

        $x .= '</Tomador>';

        $x .= $this->tagIf('RegimeEspecialTributacao', $regimeEspecialTributacao);
        $x .= $this->tagIf('OptanteSimplesNacional', $optanteSimplesNacional);
        $x .= $this->tagIf('IncentivoFiscal', $incentivoFiscal);

        $x .= '</InfDeclaracaoPrestacaoServico>';
        $x .= '</Rps>';

        return $x;
    }

    /* =========================================================
     * HELPERS / VALIDAÇÕES
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

    private function tag(string $tag, string $value): string
    {
        return "<{$tag}>{$this->xmlEscape($value)}</{$tag}>";
    }

    private function tagIf(string $tag, string $value): string
    {
        $vv = trim((string)$value);
        if ($vv === '') {
            return '';
        }
        return "<{$tag}>{$this->xmlEscape($vv)}</{$tag}>";
    }

    private function xmlCpfCnpj(string $docRaw, string $labelForError): string
    {
        $doc = $this->onlyDigits($docRaw);

        if (strlen($doc) === 11) {
            return "<CpfCnpj><Cpf>{$this->xmlEscape($doc)}</Cpf></CpfCnpj>";
        }
        if (strlen($doc) === 14) {
            return "<CpfCnpj><Cnpj>{$this->xmlEscape($doc)}</Cnpj></CpfCnpj>";
        }

        throw new Exception("Documento inválido em {$labelForError}. Informe CPF(11) ou CNPJ(14). Recebido: {$doc}");
    }

    private function money($v, bool $required = false, string $label = 'valor'): string
    {
        if ($v === null || $v === '') {
            if ($required) {
                throw new Exception("Campo {$label} obrigatório.");
            }
            return number_format(0, 2, '.', '');
        }

        $s = (string)$v;
        // remove espaços
        $s = str_replace(' ', '', $s);

        // tenta normalizar BR: 1.234,56
        if (strpos($s, ',') !== false) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        }

        if (!is_numeric($s)) {
            throw new Exception("Valor monetário inválido em {$label}: {$v}");
        }

        return number_format((float)$s, 2, '.', '');
    }

    private function percent($v, int $decimals = 4): string
    {
        if ($v === null || $v === '') {
            $v = 0;
        }

        $s = (string)$v;
        $s = str_replace(' ', '', $s);

        if (strpos($s, ',') !== false) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        }

        if (!is_numeric($s)) {
            throw new Exception("Percentual inválido: {$v}");
        }

        return number_format((float)$s, $decimals, '.', '');
    }

    /**
     * ✅ Normaliza data para YYYYMMDD (8 dígitos).
     * Aceita:
     * - 2026-01-17
     * - 2026-01-17T14:27:03
     * - 2026-01-17T14:27:03-03:00
     * - 20260117
     * - 20260117142703 (corta pra 8)
     */
    private function normalizeDateYmd(string $raw, bool $required = false): string
    {
        $raw = trim($raw);

        if ($raw === '') {
            if ($required) {
                throw new Exception("Data obrigatória não informada.");
            }
            return '';
        }

        // 2026-01-17...
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw)) {
            $d = substr($raw, 0, 10);
            return str_replace('-', '', $d);
        }

        // extrai dígitos
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (strlen($digits) >= 8) {
            return substr($digits, 0, 8);
        }

        // fallback robusto
        $dt = new DateTime($raw, new DateTimeZone('America/Sao_Paulo'));
        return $dt->format('Ymd');
    }

    /**
     * Normaliza discriminacao:
     * - remove excesso de espaços/linhas
     * - remove duplicação de prefixo "1- 1-"
     */
    private function normalizeDiscriminacao(string $disc, string $infId): string
    {
        $disc = trim($disc);
        $disc = preg_replace('/[\r\n]+/', ' ', $disc);
        $disc = preg_replace('/\s+/', ' ', $disc);

        // remove duplicação comum: "1- 1- ..."
        $disc = preg_replace('/(^|\s)1-\s+1-\s*/', '$1', $disc);

        if ($disc === '') {
            throw new Exception("discriminacao obrigatória (RPS {$infId}).");
        }

        return $disc;
    }
}
