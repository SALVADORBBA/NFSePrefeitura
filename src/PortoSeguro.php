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
 * FIX principal:
 * - DataEmissao e Competencia no RPS devem seguir o XSD do provedor.
 * - No XSD ABRASF II (comum no GestãoISS) DataEmissao é xsd:date => YYYY-MM-DD. :contentReference[oaicite:2]{index=2}
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
        return '<cabecalho xmlns="http://www.abrasf.org.br/nfse.xsd" versao="' . $this->xmlEscape($versao) . '">'
            . '<versaoDados>' . $this->xmlEscape($versao) . '</versaoDados>'
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
            throw $this->wrapSoapFault($e, $client);
        }
    }

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

    public function gerarXmlLoteRps(array $dados, string $versao = '2.02'): string
    {
        $loteId             = (string)$this->required($dados, 'lote_id');
        $numeroLote         = (string)$this->required($dados, 'numeroLote');
        $cnpjPrestador      = $this->onlyDigits((string)$this->required($dados, 'cnpjPrestador'));
        $inscricaoMunicipal = (string)$this->required($dados, 'inscricaoMunicipal');
        $rpsList            = $this->required($dados, 'rps');

        if (strlen($cnpjPrestador) !== 14) {
            throw new Exception("cnpjPrestador inválido (14 dígitos). Recebido: {$cnpjPrestador}");
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

        // ✅ XSD do provedor: xsd:date => YYYY-MM-DD :contentReference[oaicite:3]{index=3}
        $dataEmissao = $this->normalizeDateISO((string)($infRps['dataEmissao'] ?? ''), true, "DataEmissao (RPS {$infId})");
        $competencia = $this->normalizeDateISO((string)($rps['competencia'] ?? ''), true, "Competencia (RPS {$infId})");

        $valorServicos    = $this->money($rps['valorServicos'] ?? null, true, "valorServicos (RPS {$infId})");
        $valorIss         = $this->money($rps['valorIss'] ?? 0);
        $aliquota         = $this->percent($rps['aliquota'] ?? 0, 4);

        $issRetido        = (string)$this->required($rps, 'issRetido');
        $itemListaServico = (string)$this->required($rps, 'itemListaServico');
        $discriminacao    = (string)$this->required($rps, 'discriminacao');
        $codigoMunicipio  = (string)$this->required($rps, 'codigoMunicipio');
        $exigibilidadeISS = (string)$this->required($rps, 'exigibilidadeISS');

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
        $codMunTomador = (string)$this->required($end, 'codigoMunicipio');
        $uf            = (string)$this->required($end, 'uf');
        $cep           = $this->onlyDigits((string)$this->required($end, 'cep'));
        if (strlen($cep) !== 8) {
            throw new Exception("tomador.endereco.cep inválido (8 dígitos). Recebido: {$cep} (RPS {$infId})");
        }

        $telefone = $this->onlyDigits((string)($tomador['telefone'] ?? ''));
        $email    = (string)($tomador['email'] ?? '');

        $regimeEspecialTributacao = (string)($rps['regimeEspecialTributacao'] ?? '');
        $optanteSimplesNacional   = (string)($rps['optanteSimplesNacional'] ?? '');
        $incentivoFiscal          = (string)($rps['incentivoFiscal'] ?? '');

        $cnpjPrestadorLote = $this->onlyDigits((string)($loteDados['cnpjPrestador'] ?? ''));
        $inscMunPrestador  = (string)($loteDados['inscricaoMunicipal'] ?? '');
        $reg = $this->normalizeRegimeEspecialTributacao($regimeEspecialTributacao);

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
        $x .= '<ValorIss>' . $this->xmlEscape($valorIss) . '</ValorIss>';
        $x .= '<Aliquota>' . $this->xmlEscape($aliquota) . '</Aliquota>';
        $x .= '</Valores>';

        $x .= '<IssRetido>' . $this->xmlEscape($issRetido) . '</IssRetido>';
        $x .= '<ItemListaServico>' . $this->xmlEscape($itemListaServico) . '</ItemListaServico>';

        // Se você tiver CNAE no banco, mande (muito comum ser aceito/esperado)
        if (!empty($rps['codigoCnae'])) {
            $x .= '<CodigoCnae>' . $this->xmlEscape($this->onlyDigits((string)$rps['codigoCnae'])) . '</CodigoCnae>';
        }

        // GestãoISS frequentemente valida isso (se você tiver no cadastro)
        if (!empty($rps['codigoTributacaoMunicipio'])) {
            $x .= '<CodigoTributacaoMunicipio>' . $this->xmlEscape((string)$rps['codigoTributacaoMunicipio']) . '</CodigoTributacaoMunicipio>';
        }

        // Discriminacao obrigatória
        $x .= '<Discriminacao>' . $this->xmlEscape($discriminacao) . '</Discriminacao>';

        // ATENÇÃO: em alguns provedores, CodigoMunicipio é do PRESTADOR,
        // e MunicipioIncidencia é a incidência do ISS.
        // Se você só tem um, replique no outro quando exigido.
        $x .= '<CodigoMunicipio>' . $this->xmlEscape($codigoMunicipio) . '</CodigoMunicipio>';

        if (!empty($rps['municipioIncidencia'])) {
            $x .= '<MunicipioIncidencia>' . $this->xmlEscape((string)$rps['municipioIncidencia']) . '</MunicipioIncidencia>';
        } else {
            // fallback seguro quando o provedor exige MunicipioIncidencia
            $x .= '<MunicipioIncidencia>' . $this->xmlEscape($codigoMunicipio) . '</MunicipioIncidencia>';
        }

        $x .= '<ExigibilidadeISS>' . $this->xmlEscape($exigibilidadeISS) . '</ExigibilidadeISS>';

        $x .= '</Servico>';


        $x .= '<Prestador>';
        $x .= '<CpfCnpj><Cnpj>' . $this->xmlEscape($cnpjPrestadorLote) . '</Cnpj></CpfCnpj>';
        $x .= '<InscricaoMunicipal>' . $this->xmlEscape($inscMunPrestador) . '</InscricaoMunicipal>';
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

        if ($reg !== '') {
            $x .= '<RegimeEspecialTributacao>' . $this->xmlEscape($reg) . '</RegimeEspecialTributacao>';
        }

        $x .= $this->tagIf('OptanteSimplesNacional', $optanteSimplesNacional);

        $inc = $this->normalizeIncentivoFiscal($incentivoFiscal);
        if ($inc !== '') {
            $x .= '<IncentivoFiscal>' . $this->xmlEscape($inc) . '</IncentivoFiscal>';
        }

        $inc = $this->normalizeIncentivoFiscal($rps['incentivoFiscal'] ?? null);
        if ($inc !== '') {
            $x .= '<IncentivoFiscal>' . $this->xmlEscape($inc) . '</IncentivoFiscal>';
        }
        $x .= '</InfDeclaracaoPrestacaoServico>';
        $x .= '</Rps>';

        return $x;
    }

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

    private function tagIf(string $tag, string $value): string
    {
        $vv = trim((string)$value);
        if ($vv === '') return '';
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

        throw new Exception("Documento inválido em {$labelForError}. CPF(11) ou CNPJ(14). Recebido: {$doc}");
    }

    private function money($v, bool $required = false, string $label = 'valor'): string
    {
        if ($v === null || $v === '') {
            if ($required) throw new Exception("Campo {$label} obrigatório.");
            return number_format(0, 2, '.', '');
        }

        $s = (string)$v;
        $s = str_replace([' ', "\t"], '', $s);

        if (preg_match('/,\d{1,2}$/', $s)) {
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
        if ($v === null || $v === '') $v = 0;

        $s = (string)$v;
        $s = str_replace([' ', "\t"], '', $s);

        if (preg_match('/,\d+$/', $s)) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        }

        if (!is_numeric($s)) {
            throw new Exception("Percentual inválido: {$v}");
        }

        return number_format((float)$s, $decimals, '.', '');
    }

    /**
     * ✅ Normaliza para xsd:date => YYYY-MM-DD (sem hora).
     * Aceita:
     * - 2026-01-17
     * - 2026-01-17T14:27:03
     * - 2026-01-17T14:27:03-03:00
     * - 20260117
     */
    private function normalizeDateISO(string $raw, bool $required = false, string $label = 'data'): string
    {
        $raw = trim($raw);

        if ($raw === '') {
            if ($required) throw new Exception("{$label} obrigatória não informada.");
            return '';
        }

        // Já ISO date/datetime
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw)) {
            return substr($raw, 0, 10);
        }

        // Veio AAAAMMDD
        if (preg_match('/^\d{8}$/', $raw)) {
            return substr($raw, 0, 4) . '-' . substr($raw, 4, 2) . '-' . substr($raw, 6, 2);
        }

        // Fallback
        $tz = new DateTimeZone('America/Sao_Paulo');
        $dt = new DateTime($raw, $tz);
        return $dt->format('Y-m-d');
    }

    private function normalizeRegimeEspecialTributacao($v): string
    {
        $vv = trim((string)$v);

        // Se vier vazio ou "0", trate como "não informar"
        if ($vv === '' || $vv === '0') {
            return '';
        }

        // Normaliza só dígitos
        $vv = preg_replace('/\D+/', '', $vv);

        // Conjunto mais comum aceito (ABRASF/GestãoISS):
        // 1..6 (algumas cidades têm 7). Vamos aceitar 1..7 para não travar.
        if ($vv !== '' && (int)$vv >= 1 && (int)$vv <= 7) {
            return $vv;
        }

        // Se estiver fora do range, melhor não mandar (evita erro de schema)
        return '';
    }
    private function normalizeIncentivoFiscal($v): string
    {
        $vv = trim((string)$v);

        // 0 ou vazio => NÃO INFORMAR (ou você pode preferir retornar '2')
        if ($vv === '' || $vv === '0') {
            return '2'; // padrão: Não
            // ou: return ''; // se preferir omitir a tag
        }

        $vv = preg_replace('/\D+/', '', $vv);

        // Só aceita 1 ou 2
        if ($vv === '1' || $vv === '2') {
            return $vv;
        }

        // fallback: melhor padronizar para "Não"
        return '2';
    }
}
