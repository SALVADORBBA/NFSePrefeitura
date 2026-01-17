<?php
namespace NFSePrefeitura\NFSe;

use SoapClient;
use SoapFault;
use Exception;

/**
 * PortoSeguro - ABRASF v2.02 (GestaoISS) - Porto Seguro/BA
 *
 * - Gera XML do Lote RPS (EnviarLoteRpsEnvio) sem assinatura (pronto para assinar externamente).
 * - Envia via SOAP usando os métodos do WSDL:
 *   - RecepcionarLoteRps
 *   - RecepcionarLoteRpsSincrono
 *
 * Pontos críticos tratados:
 * - NÃO gera tags vazias (para evitar erro de schema).
 * - Valida e monta CPF/CNPJ do tomador corretamente.
 * - DataEmissao e Competencia normalizadas para dateTime / date conforme exigência (com fallback seguro).
 * - Valores numéricos formatados corretamente.
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
        // O provedor normalmente pede isso exatamente como string XML
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
        $loteId             = $this->required($dados, 'lote_id');
        $numeroLote         = $this->required($dados, 'numeroLote');
        $cnpjPrestador      = $this->onlyDigits($this->required($dados, 'cnpjPrestador'));
        $inscricaoMunicipal = $this->required($dados, 'inscricaoMunicipal');
        $rpsList            = $this->required($dados, 'rps');

        if (strlen($cnpjPrestador) !== 14) {
            throw new Exception("cnpjPrestador inválido (precisa 14 dígitos). Recebido: {$cnpjPrestador}");
        }

        if (!is_array($rpsList) || count($rpsList) < 1) {
            throw new Exception("Lista de RPS vazia/inválida em dados['rps'].");
        }

        $quantidadeRps = (string)(isset($dados['quantidadeRps']) ? (int)$dados['quantidadeRps'] : count($rpsList));
        if ((int)$quantidadeRps !== count($rpsList)) {
            // não impede, mas evita divergência
            $quantidadeRps = (string)count($rpsList);
        }

        $x  = '<?xml version="1.0" encoding="utf-8"?>';
        $x .= '<EnviarLoteRpsEnvio xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
           .  'xmlns:xsd="http://www.w3.org/2001/XMLSchema" '
           .  'xmlns="http://www.abrasf.org.br/nfse.xsd">';
        $x .= '<LoteRps Id="' . $this->xmlEscape($loteId) . '" versao="' . $this->xmlEscape($versao) . '">';
        $x .= '<NumeroLote>' . $this->xmlEscape($numeroLote) . '</NumeroLote>';
        $x .= '<CpfCnpj><Cnpj>' . $this->xmlEscape($cnpjPrestador) . '</Cnpj></CpfCnpj>';
        $x .= '<InscricaoMunicipal>' . $this->xmlEscape($inscricaoMunicipal) . '</InscricaoMunicipal>';
        $x .= '<QuantidadeRps>' . $this->xmlEscape($quantidadeRps) . '</QuantidadeRps>';
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
        // ------- mínimos do RPS -------
        $infId  = $this->required($rps, 'inf_id');
        $infRps = $this->required($rps, 'infRps');

        if (!is_array($infRps)) {
            throw new Exception("infRps inválido no RPS {$infId}.");
        }

        $numero = $this->required($infRps, 'numero');
        $serie  = $this->required($infRps, 'serie');
        $tipo   = $this->required($infRps, 'tipo');

        // DataEmissao: muitos provedores exigem DATETIME (não só date)
        $dataEmissaoRaw = (string)($rps['infRps']['dataEmissao'] ?? '');
        $dataEmissao = '';
        if ($dataEmissaoRaw !== '') {
            $dt = new \DateTime($dataEmissaoRaw);
            $dataEmissao = $dt->format('Y-m-d\TH:i:s'); // Formato completo conforme ABRASF
        }









        
        // Competencia normalmente é date (YYYY-MM-DD), mas aceitam datetime em alguns. Vamos normalizar pra date.
        $competenciaRaw = (string)($rps['competencia'] ?? '');
        $competencia    = $this->normalizeDate($competenciaRaw, true);

        // Serviço (obrigatórios mais comuns)
        $valorServicos      = $this->money($rps['valorServicos'] ?? null, true, "valorServicos (RPS {$infId})");
        $valorIss           = $this->money($rps['valorIss'] ?? 0);
        $aliquota           = $this->percent($rps['aliquota'] ?? 0, 4);

        $issRetido          = $this->required($rps, 'issRetido'); // 1/2
        $itemListaServico   = $this->required($rps, 'itemListaServico');
        $discriminacao      = (string)($rps['discriminacao'] ?? '');
        $codigoMunicipio    = $this->required($rps, 'codigoMunicipio');
        $exigibilidadeISS   = $this->required($rps, 'exigibilidadeISS');

        // Tomador
        $tomador = $this->required($rps, 'tomador');
        if (!is_array($tomador)) {
            throw new Exception("tomador inválido no RPS {$infId}.");
        }

        $docTomadorRaw = (string)($tomador['cpfCnpj'] ?? '');
        $docTomadorXml = $this->xmlCpfCnpj($docTomadorRaw, "tomador.cpfCnpj (RPS {$infId})");

        $razaoSocial = (string)($tomador['razaoSocial'] ?? '');
        if (trim($razaoSocial) === '') {
            throw new Exception("tomador.razaoSocial obrigatório (RPS {$infId}).");
        }

        $end = $tomador['endereco'] ?? null;
        if (!is_array($end)) {
            throw new Exception("tomador.endereco obrigatório/ inválido (RPS {$infId}).");
        }

        $logradouro      = $this->required($end, 'logradouro');
        $numeroEnd       = $this->required($end, 'numero');
        $bairro          = $this->required($end, 'bairro');
        $codMunTomador   = $this->required($end, 'codigoMunicipio');
        $uf              = $this->required($end, 'uf');
        $cep             = $this->onlyDigits($this->required($end, 'cep'));
        if (strlen($cep) !== 8) {
            throw new Exception("tomador.endereco.cep inválido (precisa 8 dígitos). Recebido: {$cep} (RPS {$infId})");
        }

        // Contato (opcional na maioria, mas se vier, não pode vir vazio)
        $telefone = $this->onlyDigits((string)($tomador['telefone'] ?? ''));
        $email    = (string)($tomador['email'] ?? '');

        // Regimes finais
        $regimeEspecialTributacao = (string)($rps['regimeEspecialTributacao'] ?? '0');
        $optanteSimplesNacional   = (string)($rps['optanteSimplesNacional'] ?? '2'); // 1=Sim 2=Não (comum)
        $incentivoFiscal          = (string)($rps['incentivoFiscal'] ?? '2');        // 1=Sim 2=Não (comum)

        // Prestador do lote
        $cnpjPrestador      = $this->onlyDigits((string)($loteDados['cnpjPrestador'] ?? ''));
// ALTERAÇÃO: garantir que o CNPJ seja sempre string e apenas dígitos
// Se quiser alterar o valor, faça aqui:
// Exemplo: $cnpjPrestador = '12345678000199';
        $inscricaoMunicipal = (string)($loteDados['inscricaoMunicipal'] ?? '');
       $dataEmissao = $this->forceTimezone((string)($rps['infRps']['dataEmissao'] ?? ''));

        $x  = '<Rps>';
        $x .= '<InfDeclaracaoPrestacaoServico Id="' . $this->xmlEscape($infId) . '">';

        $x .= '<Rps>';
        $x .= '<IdentificacaoRps>';
        $x .= '<Numero>' . $this->xmlEscape($numero) . '</Numero>';
        $x .= '<Serie>' . $this->xmlEscape($serie) . '</Serie>';
        $x .= '<Tipo>' . $this->xmlEscape($tipo) . '</Tipo>';
        $x .= '</IdentificacaoRps>';
        $x .= '<DataEmissao>' . $dataEmissao . '</DataEmissao>';        
        $x .= '<Status>1</Status>';
        $x .= '</Rps>';

        $x .= '<Competencia>' . $this->xmlEscape($competencia) . '</Competencia>';

        $x .= '<Servico>';
        $x .= '<Valores>';
        $x .= '<ValorServicos>' . $this->xmlEscape($valorServicos) . '</ValorServicos>';
        $x .= $this->tag('ValorIss', $valorIss);   // pode ser 0, mas permitido
        $x .= $this->tag('Aliquota', $aliquota);   // pode ser 0
        $x .= '</Valores>';

        // obrigatórios e sem vazio
        $x .= '<IssRetido>' . $this->xmlEscape($issRetido) . '</IssRetido>';
        $x .= '<ItemListaServico>' . $this->xmlEscape($itemListaServico) . '</ItemListaServico>';

        // Discriminacao pode ser obrigatória dependendo do provedor; aqui exigimos pelo menos algo
        if (trim($discriminacao) === '') {
            throw new Exception("discriminacao obrigatória (RPS {$infId}).");
        }
        $x .= '<Discriminacao>' . $this->xmlEscape($discriminacao) . '</Discriminacao>';

        $x .= '<CodigoMunicipio>' . $this->xmlEscape($codigoMunicipio) . '</CodigoMunicipio>';
        $x .= '<ExigibilidadeISS>' . $this->xmlEscape($exigibilidadeISS) . '</ExigibilidadeISS>';
        $x .= '</Servico>';

        $x .= '<Prestador>';
        $x .= '<CpfCnpj><Cnpj>' . $this->xmlEscape($cnpjPrestador) . '</Cnpj></CpfCnpj>';
        $x .= '<InscricaoMunicipal>' . $this->xmlEscape($inscricaoMunicipal) . '</InscricaoMunicipal>';
        $x .= '</Prestador>';

        $x .= '<Tomador>';
        $x .= '<IdentificacaoTomador>';
        $x .= $docTomadorXml;

        // Inscrição municipal do tomador é opcional: só gera se tiver algo
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

        // Contato só se tiver algo
        $contato = '';
        $contato .= $this->tagIf('Telefone', $telefone);
        $contato .= $this->tagIf('Email', $email);
        if ($contato !== '') {
            $x .= '<Contato>' . $contato . '</Contato>';
        }

        $x .= '</Tomador>';

        // Regimes - não mandar vazio
        $x .= $this->tagIf('RegimeEspecialTributacao', $regimeEspecialTributacao);
        $x .= $this->tagIf('OptanteSimplesNacional', $optanteSimplesNacional);
        $x .= $this->tagIf('IncentivoFiscal', $incentivoFiscal);

        $x .= '</InfDeclaracaoPrestacaoServico>';
        $x .= '</Rps>';

        return $x;
    }

    /* =========================================================
     * HELPERS XML / VALIDAÇÕES
     * ========================================================= */

    private function required(array $arr, string $key)
    {
        if (!array_key_exists($key, $arr)) {
            throw new Exception("Campo obrigatório ausente: {$key}");
        }
        $v = $arr[$key];
        if (is_string($v) && trim($v) === '') {
            throw new Exception("Campo obrigatório vazio: {$key}");
        }
        if ($v === null) {
            throw new Exception("Campo obrigatório nulo: {$key}");
        }
        return $v;
    }

    private function onlyDigits(string $v): string
    {
        return preg_replace('/\D+/', '', $v) ?? '';
    }

    private function xmlEscape(string $v): string
    {
        // ENT_XML1: correto para XML; evita quebrar tags
        return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function tag(string $tag, string $value): string
    {
        // sempre gera (mesmo que 0.0000)
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

        // aceita "1.234,56" ou "1234.56"
        $s = (string)$v;
        $s = str_replace(['.', ' '], ['', ''], $s); // remove separador milhar comum
        $s = str_replace(',', '.', $s);

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
        $s = str_replace(['.', ' '], ['', ''], $s);
        $s = str_replace(',', '.', $s);

        if (!is_numeric($s)) {
            throw new Exception("Percentual inválido: {$v}");
        }

        return number_format((float)$s, $decimals, '.', '');
    }

    /**
     * Normaliza para date (YYYY-MM-DD).
     * Se required=true e vazio -> exception.
     */
    private function normalizeDate(string $raw, bool $required = false): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            if ($required) {
                throw new Exception("Data obrigatória não informada.");
            }
            return '';
        }

        // Se vier datetime, pega só date
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw)) {
            return substr($raw, 0, 10);
        }

        // fallback simples: tenta strtotime
        $ts = strtotime($raw);
        if ($ts === false) {
            throw new Exception("Data inválida: {$raw}");
        }

        return date('Y-m-d', $ts);
    }

    /**
     * Normaliza para dateTime (YYYY-MM-DDTHH:MM:SS).
     * Alguns provedores aceitam timezone, mas aqui mantemos formato básico.
     */
    private function normalizeDateTime(string $raw, bool $required = false): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            if ($required) {
                throw new Exception("Data/hora obrigatória não informada.");
            }
            return '';
        }

        // já é datetime ISO (com ou sem timezone)
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $raw)) {
            return substr($raw, 0, 19);
        }

        // se vier só date, adiciona 00:00:00
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw . 'T00:00:00';
        }

        // fallback: tenta strtotime
        $ts = strtotime($raw);
        if ($ts === false) {
            throw new Exception("Data/hora inválida: {$raw}");
        }

        return date('Y-m-d\TH:i:s', $ts);
    }

    private function forceTimezone(string $dt): string
{
    $dt = trim($dt);

    if ($dt === '') {
        throw new Exception('DataEmissao obrigatória.');
    }

    // já tem timezone (Z ou -03:00)
    if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|[+\-]\d{2}:\d{2})$/', $dt)) {
        return $dt;
    }

    // sem timezone: adiciona -03:00
    if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $dt)) {
        return $dt . '-03:00';
    }

    // veio só date: completa
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dt)) {
        return $dt . 'T00:00:00-03:00';
    }

    // fallback: tenta parsear
    $d = new DateTime($dt, new DateTimeZone('America/Sao_Paulo'));
    return $d->format('Y-m-d\TH:i:sP');
}

}