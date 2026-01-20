<?php

namespace NFSePrefeitura\NFSe\PFPortoSeguro;

use Exception;
use InvalidArgumentException;

class ConsultarLoteRpsGestaoIssService
{
    private string $endpoint;

    /** Versão ABRASF do cabeçalho/dados (Porto Seguro costuma ser 2.02) */
    private string $versao;

    /** SOAPAction padrão ABRASF */
    private string $soapAction;

    private int $connectTimeout = 15;
    private int $timeout = 30;

    public function __construct(
        string $endpoint = 'https://portoseguroba.gestaoiss.com.br/ws/nfse.asmx',
        string $versao = '2.02',
        string $soapAction = 'http://nfse.abrasf.org.br/ConsultarLoteRps'
    ) {
        if (!filter_var($endpoint, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException("Endpoint inválido: {$endpoint}");
        }
        $this->endpoint = $endpoint;
        $this->versao = $versao;
        $this->soapAction = $soapAction;
    }

    public function setTimeouts(int $connectTimeout, int $timeout): self
    {
        $this->connectTimeout = max(1, $connectTimeout);
        $this->timeout = max(1, $timeout);
        return $this;
    }

    /**
     * Consulta o lote pelo protocolo (GestãoISS/ABRASF).
     */
    public function consultarPorProtocolo(string $cnpj, string $inscricaoMunicipal, string $protocolo): array
    {
        $cnpj = $this->onlyDigits($cnpj);
        $im   = $this->onlyDigits($inscricaoMunicipal);
        $prot = $this->onlyDigits($protocolo);

        if (strlen($cnpj) !== 14) {
            throw new InvalidArgumentException('CNPJ inválido');
        }
        if ($im === '') {
            throw new InvalidArgumentException('Inscrição Municipal inválida');
        }
        if ($prot === '') {
            throw new InvalidArgumentException('Protocolo inválido');
        }

        $cabecMsg = $this->montarCabecMsg($this->versao);
        $dadosMsg = $this->montarConsultarLoteRpsEnvio($cnpj, $im, $prot);

        $soapRequest = $this->montarSoapEnvelope($cabecMsg, $dadosMsg);

        [$httpCode, $raw] = $this->postSoap($soapRequest);

        $xmlRetorno = $this->extrairXmlRetorno($raw);

        return [
            'ok' => true,
            'http_code' => $httpCode,
            'metodo' => 'ConsultarLoteRps',
            'soap_action' => $this->soapAction,
            'endpoint' => $this->endpoint,
            'versao' => $this->versao,
            'nfseCabecMsg' => $cabecMsg,
            'nfseDadosMsg' => $dadosMsg,
            'soap_request' => $soapRequest,
            'soap_response_raw' => $raw,
            'xml_retorno' => $xmlRetorno,
        ];
    }

    /**
     * Cabeçalho ABRASF (SEM <?xml ...?>), formato aceito pelo GestãoISS.
     */
    private function montarCabecMsg(string $versao): string
    {
        $v = $this->xmlEscape($versao);

        return '<cabecalho xmlns="http://www.abrasf.org.br/nfse.xsd" versao="' . $v . '">'
             . '<versaoDados>' . $v . '</versaoDados>'
             . '</cabecalho>';
    }

    /**
     * Dados da consulta (SEM <?xml ...?>), formato ABRASF.
     */
    private function montarConsultarLoteRpsEnvio(string $cnpj, string $im, string $protocolo): string
    {
        return '<ConsultarLoteRpsEnvio xmlns="http://www.abrasf.org.br/nfse.xsd">'
             .   '<Prestador>'
             .     '<CpfCnpj><Cnpj>' . $cnpj . '</Cnpj></CpfCnpj>'
             .     '<InscricaoMunicipal>' . $this->xmlEscape($im) . '</InscricaoMunicipal>'
             .   '</Prestador>'
             .   '<Protocolo>' . $protocolo . '</Protocolo>'
             . '</ConsultarLoteRpsEnvio>';
    }

    /**
     * SOAP no padrão GestãoISS:
     * <ConsultarLoteRpsRequest xmlns="http://nfse.abrasf.org.br">
     *   <nfseCabecMsg xmlns="">...escapado...</nfseCabecMsg>
     *   <nfseDadosMsg xmlns="">...escapado...</nfseDadosMsg>
     * </ConsultarLoteRpsRequest>
     */
    private function montarSoapEnvelope(string $cabecMsg, string $dadosMsg): string
    {
        $cabecEsc = htmlspecialchars($cabecMsg, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $dadosEsc = htmlspecialchars($dadosMsg, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return '<?xml version="1.0" encoding="utf-8"?>'
            . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">'
            .   '<soapenv:Header/>'
            .   '<soapenv:Body>'
            .     '<ConsultarLoteRpsRequest xmlns="http://nfse.abrasf.org.br">'
            .       '<nfseCabecMsg xmlns="">' . $cabecEsc . '</nfseCabecMsg>'
            .       '<nfseDadosMsg xmlns="">' . $dadosEsc . '</nfseDadosMsg>'
            .     '</ConsultarLoteRpsRequest>'
            .   '</soapenv:Body>'
            . '</soapenv:Envelope>';
    }

    private function postSoap(string $soapEnvelope): array
    {
        $ch = curl_init($this->endpoint);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $soapEnvelope,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: "' . $this->soapAction . '"',
            ],
            // GestãoISS às vezes quebra com validação strict em alguns servidores
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $resp = curl_exec($ch);

        if ($resp === false) {
            $err = curl_error($ch);
            $errno = curl_errno($ch);
            curl_close($ch);
            throw new Exception("CURL-ERROR ({$errno}): {$err}");
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new Exception("HTTP {$httpCode}: " . substr($resp, 0, 3000));
        }

        return [$httpCode, $resp];
    }

    /**
     * Extrai o XML interno do SOAP.
     * GestãoISS costuma devolver dentro de <outputXML> ... escaped ...
     */
    private function extrairXmlRetorno(string $soapRaw): string
    {
        // outputXML
        if (preg_match('~<outputXML[^>]*>(.*?)</outputXML>~is', $soapRaw, $m)) {
            $inner = trim($m[1]);
            // vem escapado (&lt;...&gt;)
            if (str_contains($inner, '&lt;')) {
                $inner = html_entity_decode($inner, ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
            return $inner;
        }

        // return (fallback)
        if (preg_match('~<return[^>]*>(.*?)</return>~is', $soapRaw, $m)) {
            $inner = trim($m[1]);
            if (str_contains($inner, '&lt;')) {
                $inner = html_entity_decode($inner, ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
            return $inner;
        }

        return $soapRaw;
    }

    private function onlyDigits(string $v): string
    {
        return preg_replace('/\D+/', '', $v) ?? '';
    }

    private function xmlEscape(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
