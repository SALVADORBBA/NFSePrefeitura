<?php
namespace DevelopApi;

class PortoSeguroNfse
{
    public const NS = 'http://www.abrasf.org.br/nfse.xsd';
    public const VERSAO = '2.02';

    private array $dados;
    private string $xml = '';

    public function __construct(array $dados)
    {
        $this->dados = $dados;
    }

    /* =========================================================
     * Helpers
     * ========================================================= */

    private function xmlValue($v): string
    {
        if ($v === null) {
            $v = '';
        } elseif (is_array($v)) {
            $v = implode(' ', array_map(function ($x) {
                if (is_scalar($x)) return (string)$x;
                return json_encode($x, JSON_UNESCAPED_UNICODE);
            }, $v));
        } elseif (is_object($v)) {
            $v = method_exists($v, '__toString')
                ? (string)$v
                : json_encode($v, JSON_UNESCAPED_UNICODE);
        } elseif (is_bool($v)) {
            $v = $v ? '1' : '0';
        } else {
            $v = (string)$v;
        }

        return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function money($v): string
    {
        if ($v === null || $v === '') return '0.00';

        if (is_array($v) || is_object($v)) {
            $v = $this->xmlValue($v);
        }

        $s = (string)$v;
        $s = str_replace(['.', ' '], ['', ''], $s);
        $s = str_replace(',', '.', $s);

        $n = is_numeric($s) ? (float)$s : 0.0;
        return number_format($n, 2, '.', '');
    }

    private function date($v): string
    {
        $s = is_scalar($v) ? trim((string)$v) : '';

        // aceita ISO completo
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $s)) {
            return $this->xmlValue($s);
        }

        // "YYYY-MM-DD HH:MM:SS" => "YYYY-MM-DDTHH:MM:SS"
        if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/', $s)) {
            return $this->xmlValue(str_replace(' ', 'T', $s));
        }

        // somente data
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return $this->xmlValue($s);
        }

        return $this->xmlValue($s);
    }

    private function cabecalho(): string
    {
        return '<cabecalho xmlns="' . self::NS . '" versao="' . self::VERSAO . '">'
             .   '<versaoDados>' . self::VERSAO . '</versaoDados>'
             . '</cabecalho>';
    }

    /* =========================================================
     * 1) XML LOTE: EnviarLoteRpsEnvio
     * (use com RecepcionarLoteRps / RecepcionarLoteRpsSincrono)
     * ========================================================= */

    public function montarXmlLote(): string
    {
        $rps       = $this->dados['rps']       ?? [];
        $prestador = $this->dados['prestador'] ?? [];
        $servico   = $this->dados['servico']   ?? [];
        $tomador   = $this->dados['tomador']   ?? [];

        $xml =
         
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<EnviarLoteRpsEnvio xmlns="' . self::NS . '">'
            . '  <LoteRps>'
            . '    <NumeroLote>' . $this->xmlValue($rps['numero'] ?? '') . '</NumeroLote>'
            . '    <Cnpj>' . $this->xmlValue($prestador['cnpjPrestador'] ?? '') . '</Cnpj>'
            . '    <InscricaoMunicipal>' . $this->xmlValue($prestador['inscricao_municipal'] ?? '') . '</InscricaoMunicipal>'
            . '    <QuantidadeRps>1</QuantidadeRps>'
            . '    <ListaRps>'
            . '      <Rps>'
            . '        <InfRps>'
            . '          <IdentificacaoRps>'
            . '            <Numero>' . $this->xmlValue($rps['numero'] ?? '') . '</Numero>'
            . '            <Serie>'  . $this->xmlValue($rps['serie'] ?? '')  . '</Serie>'
            . '            <Tipo>'   . $this->xmlValue($rps['tipo'] ?? '')   . '</Tipo>'
            . '          </IdentificacaoRps>'
            . '          <DataEmissao>' . $this->date($rps['data_emissao'] ?? '') . '</DataEmissao>'
            . '          <Servico>'
            . '            <Descricao>'     . $this->xmlValue($servico['descricao'] ?? '') . '</Descricao>'
            . '            <Aliquota>'      . $this->money($servico['aliquota'] ?? '0') . '</Aliquota>'
            . '            <ValorServico>'  . $this->money($servico['valor_servico'] ?? '0') . '</ValorServico>'
            . '            <CodigoServico>' . $this->xmlValue($servico['codigo_servico'] ?? '') . '</CodigoServico>'
            . '          </Servico>'
            . '          <Tomador>'
            . '            <CpfCnpj>' . $this->xmlValue($tomador['cpf_cnpj'] ?? '') . '</CpfCnpj>'
            . '            <Nome>'    . $this->xmlValue($tomador['nome'] ?? '') . '</Nome>'
            . '            <Endereco>' . $this->xmlValue($tomador['endereco'] ?? '') . '</Endereco>'
            . '            <Bairro>'  . $this->xmlValue($tomador['bairro'] ?? '') . '</Bairro>'
            . '            <Cep>'     . $this->xmlValue($tomador['cep'] ?? '') . '</Cep>'
            . '            <Cidade>'  . $this->xmlValue($tomador['cidade'] ?? '') . '</Cidade>'
            . '            <Uf>'      . $this->xmlValue($tomador['uf'] ?? '') . '</Uf>'
            . '            <Email>'   . $this->xmlValue($tomador['email'] ?? '') . '</Email>'
            . '          </Tomador>'
            . '        </InfRps>'
            . '      </Rps>'
            . '    </ListaRps>'
            . '  </LoteRps>'
            . '</EnviarLoteRpsEnvio>';

        $this->xml = $xml;
        return $this->xml;
    }

    public function paramsLote(): array
    {
        return [
            'nfseCabecMsg' => $this->cabecalho(),
            'nfseDadosMsg' => $this->montarXmlLote(),
        ];
    }

    /* =========================================================
     * 2) XML GERAR NFSE: GerarNfseEnvio
     * (use com GerarNfse)
     * ========================================================= */

    public function montarXmlGerarNfse(): string
    {
        $rps       = $this->dados['rps']       ?? [];
        $prestador = $this->dados['prestador'] ?? [];
        $servico   = $this->dados['servico']   ?? [];
        $tomador   = $this->dados['tomador']   ?? [];

        // ATENÇÃO:
        // Aqui é um "esqueleto mínimo". O padrão ABRASF GerarNfseEnvio normalmente exige
        // grupos mais completos (IdentificacaoPrestador, Tomador com IdentificacaoTomador, Endereco, etc).
        // Se o provedor exigir mais campos, você vai precisar expandir.

        $xml =
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<GerarNfseEnvio xmlns="' . self::NS . '">'
            . '  <Rps>'
            . '    <InfRps>'
            . '      <IdentificacaoRps>'
            . '        <Numero>' . $this->xmlValue($rps['numero'] ?? '') . '</Numero>'
            . '        <Serie>' . $this->xmlValue($rps['serie'] ?? '') . '</Serie>'
            . '        <Tipo>' . $this->xmlValue($rps['tipo'] ?? '') . '</Tipo>'
            . '      </IdentificacaoRps>'
            . '      <DataEmissao>' . $this->date($rps['data_emissao'] ?? '') . '</DataEmissao>'
            . '      <Prestador>'
            . '        <Cnpj>' . $this->xmlValue($prestador['cnpjPrestador'] ?? '') . '</Cnpj>'
            . '        <InscricaoMunicipal>' . $this->xmlValue($prestador['inscricao_municipal'] ?? '') . '</InscricaoMunicipal>'
            . '      </Prestador>'
            . '      <Servico>'
            . '        <Descricao>' . $this->xmlValue($servico['descricao'] ?? '') . '</Descricao>'
            . '        <Aliquota>' . $this->money($servico['aliquota'] ?? '0') . '</Aliquota>'
            . '        <ValorServico>' . $this->money($servico['valor_servico'] ?? '0') . '</ValorServico>'
            . '        <CodigoServico>' . $this->xmlValue($servico['codigo_servico'] ?? '') . '</CodigoServico>'
            . '      </Servico>'
            . '      <Tomador>'
            . '        <CpfCnpj>' . $this->xmlValue($tomador['cpf_cnpj'] ?? '') . '</CpfCnpj>'
            . '        <Nome>' . $this->xmlValue($tomador['nome'] ?? '') . '</Nome>'
            . '        <Endereco>' . $this->xmlValue($tomador['endereco'] ?? '') . '</Endereco>'
            . '        <Bairro>' . $this->xmlValue($tomador['bairro'] ?? '') . '</Bairro>'
            . '        <Cep>' . $this->xmlValue($tomador['cep'] ?? '') . '</Cep>'
            . '        <Cidade>' . $this->xmlValue($tomador['cidade'] ?? '') . '</Cidade>'
            . '        <Uf>' . $this->xmlValue($tomador['uf'] ?? '') . '</Uf>'
            . '        <Email>' . $this->xmlValue($tomador['email'] ?? '') . '</Email>'
            . '      </Tomador>'
            . '    </InfRps>'
            . '  </Rps>'
            . '</GerarNfseEnvio>';

        $this->xml = $xml;
        return $this->xml;
    }

    public function paramsGerarNfse(): array
    {
        return [
            'nfseCabecMsg' => $this->cabecalho(),
            'nfseDadosMsg' => $this->montarXmlGerarNfse(),
        ];
    }

    /* =========================================================
     * Envio genérico
     * ========================================================= */

    public function enviarSoap(string $wsdl, string $method, array $params, string $certPath, string $certPass): array
    {
        $options = [
            'local_cert'         => $certPath,
            'passphrase'         => $certPass,
            'trace'              => 1,
            'exceptions'         => true,
            'cache_wsdl'         => WSDL_CACHE_NONE,
            'soap_version'       => SOAP_1_1,
            'connection_timeout' => 120,
        ];

        $client = new \SoapClient($wsdl, $options);

        try {
            $response = $client->__soapCall($method, [$params]);
            return [
                'success'      => true,
                'response'     => $response,
                'last_request' => $client->__getLastRequest(),
                'last_response'=> $client->__getLastResponse(),
            ];
        } catch (\SoapFault $e) {
            return [
                'success'      => false,
                'error'        => $e->getMessage(),
                'faultcode'    => $e->faultcode ?? null,
                'faultstring'  => $e->faultstring ?? null,
                'last_request' => $client->__getLastRequest(),
                'last_response'=> $client->__getLastResponse(),
            ];
        }
    }

    public function getXml(): string
    {
        return $this->xml;
    }
}
