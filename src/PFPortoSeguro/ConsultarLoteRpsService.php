<?php

namespace NFSePrefeitura\NFSe;

use DOMDocument;
use DOMXPath;
use InvalidArgumentException;

class ConsultarLoteRpsService
{
    private string $endpoint;
    private string $metodo;     // ex: "ConsultarLoteRps" ou "ConsultarSituacaoLoteRps"
    private string $soapAction; // ex: "http://nfse.abrasf.org.br/ConsultarLoteRps"
    private int $timeout;

    public function __construct(
        string $endpoint,
        string $metodo = 'ConsultarLoteRps',
        ?string $soapAction = null,
        int $timeout = 30
    ) {
        $this->endpoint = $endpoint;
        $this->metodo   = $metodo;
        $this->soapAction = $soapAction ?: "http://nfse.abrasf.org.br/{$metodo}";
        $this->timeout = $timeout;

        if (!filter_var($this->endpoint, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException("Endpoint inválido: {$this->endpoint}");
        }
    }

    /**
     * Consulta lote pelo protocolo.
     * $prestador = [
     *   'cnpj' => '12345678000199',
     *   'im'   => '12345' // inscrição municipal
     * ]
     */
    public function consultarPorProtocolo(array $prestador, string $protocolo): array
    {
        $cnpj = $this->onlyDigits($prestador['cnpj'] ?? '');
        $im   = $this->onlyDigits($prestador['im'] ?? '');

        if (strlen($cnpj) !== 14) {
            throw new InvalidArgumentException('CNPJ do prestador inválido');
        }
        if ($im === '') {
            throw new InvalidArgumentException('Inscrição Municipal do prestador é obrigatória');
        }
        if ($this->onlyDigits($protocolo) === '') {
            throw new InvalidArgumentException('Protocolo inválido');
        }

        $xmlEnvio = $this->montarXmlConsultarLoteRps($cnpj, $im, $protocolo);

        $soapEnvelope = $this->montarSoapEnvelope($this->metodo, $xmlEnvio);

        $raw = $this->postSoap($soapEnvelope);

        return [
            'ok' => true,
            'metodo' => $this->metodo,
            'soap_action' => $this->soapAction,
            'endpoint' => $this->endpoint,
            'xml_envio' => $xmlEnvio,
            'soap_request' => $soapEnvelope,
            'soap_response_raw' => $raw,
            'xml_retorno' => $this->extrairXmlRetorno($raw),
        ];
    }

    private function montarXmlConsultarLoteRps(string $cnpj, string $im, string $protocolo): string
    {
        // Padrão ABRASF mais comum
        return
            '<ConsultarLoteRpsEnvio xmlns="http://www.abrasf.org.br/nfse.xsd">' .
                '<Prestador>' .
                    '<CpfCnpj><Cnpj>' . $cnpj . '</Cnpj></CpfCnpj>' .
                    '<InscricaoMunicipal>' . $im . '</InscricaoMunicipal>' .
                '</Prestador>' .
                '<Protocolo>' . $this->onlyDigits($protocolo) . '</Protocolo>' .
            '</ConsultarLoteRpsEnvio>';
    }

    private function montarSoapEnvelope(string $metodo, string $xmlInterno): string
    {
        // Muitos provedores ABRASF esperam o XML dentro de um "param0" (ou "xml") como string.
        // Aqui mando em CDATA para evitar escapar tudo.
        return
            '<?xml version="1.0" encoding="utf-8"?>' .
            '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">' .
                '<soapenv:Header/>' .
                '<soapenv:Body>' .
                    '<' . $metodo . ' xmlns="http://nfse.abrasf.org.br/">' .
                        '<param0><![CDATA[' . $xmlInterno . ']]></param0>' .
                    '</' . $metodo . '>' .
                '</soapenv:Body>' .
            '</soapenv:Envelope>';
    }

    private function postSoap(string $soapEnvelope): string
    {
        $ch = curl_init($this->endpoint);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $soapEnvelope,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: "' . $this->soapAction . '"',
            ],
        ]);

        $resp = curl_exec($ch);

        if ($resp === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("Falha CURL: {$err}");
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \RuntimeException("HTTP {$httpCode} ao consultar. Resposta: " . substr($resp, 0, 2000));
        }

        return $resp;
    }

    /**
     * Tenta extrair o XML interno do retorno SOAP.
     * Alguns provedores retornam o XML “escapado” (&lt;...&gt;), outros em CDATA.
     */
    private function extrairXmlRetorno(string $soapRaw): string
    {
        // 1) tenta pegar conteúdo entre tags de retorno do método
        // Ex: <ConsultarLoteRpsResponse> <return>...</return> </ConsultarLoteRpsResponse>
        if (preg_match('~<return[^>]*>(.*?)</return>~is', $soapRaw, $m)) {
            $inner = trim($m[1]);

            // se vier escapado
            if (str_contains($inner, '&lt;')) {
                $inner = html_entity_decode($inner, ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
            // se vier CDATA, o regex já pega o conteúdo
            return $inner;
        }

        // fallback: devolve o raw pra você inspecionar
        return $soapRaw;
    }

    private function onlyDigits(string $v): string
    {
        return preg_replace('/\D+/', '', $v) ?? '';
    }
}
