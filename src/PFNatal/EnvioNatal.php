<?php
namespace NFSePrefeitura\NFSe\PFNatal;

use SoapClient;
use SoapFault;
use Exception;

class EnvioNatal
{
    private const REMOTE_WSDL = 'https://wsnfsev1.natal.rn.gov.br:8444/axis2/services/NfseWSServiceV1?wsdl';
    private const BUNDLE_DIR  = __DIR__ . '/wsdl_bundle';
    private const LOCAL_WSDL_FILE = 'nfse.wsdl';

    private string $certPath;     // PFX
    private string $certPassword; // senha

    private ?string $lastRequest  = null;
    private ?string $lastResponse = null;

    public function __construct(string $certPath, string $certPassword)
    {
        $this->certPath     = $certPath;
        $this->certPassword = $certPassword;
    }

    public function getLastRequest(): ?string  { return $this->lastRequest; }
    public function getLastResponse(): ?string { return $this->lastResponse; }

    /** Cabeçalho exigido pelo retorno: versao="1" e versaoDados=2 */
    private function cabecalho(): string
    {
        return '<cabecalho xmlns="http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd" versao="1"><versaoDados>2</versaoDados></cabecalho>';
    }

    public function enviarLoteRps(string $xml): object
    {
        $client = $this->client();

        $params = [
            'nfseCabecMsg' => $this->cabecalho(),
            'nfseDadosMsg' => $xml
        ];

        try {
            $ret = $client->__soapCall('RecepcionarLoteRps', [$params]);
            $this->lastRequest  = $client->__getLastRequest();
            $this->lastResponse = $client->__getLastResponse();
            return $ret;
        } catch (SoapFault $e) {
            $this->lastRequest  = $client->__getLastRequest();
            $this->lastResponse = $client->__getLastResponse();
            throw new Exception(
                "ERRO SOAP:\n{$e->getMessage()}\n\nREQUEST:\n{$this->lastRequest}\n\nRESPONSE:\n{$this->lastResponse}",
                0,
                $e
            );
        }
    }

    public function consultarSituacaoLote(string $protocolo, string $cnpj, string $inscricaoMunicipal): object
    {
        $client = $this->client();

        $xml = '<ConsultarSituacaoLoteRpsEnvio xmlns="http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd">'.
               '<Protocolo>'.htmlspecialchars($protocolo, ENT_QUOTES | ENT_XML1, 'UTF-8').'</Protocolo>'.
               '<Prestador>'.
               '<Cnpj>'.preg_replace('/\D+/', '', $cnpj).'</Cnpj>'.
               '<InscricaoMunicipal>'.preg_replace('/\D+/', '', $inscricaoMunicipal).'</InscricaoMunicipal>'.
               '</Prestador>'.
               '</ConsultarSituacaoLoteRpsEnvio>';

        try {
            $ret = $client->__soapCall('ConsultarSituacaoLoteRps', [[
                'nfseCabecMsg' => $this->cabecalho(),
                'nfseDadosMsg' => $xml
            ]]);
            $this->lastRequest  = $client->__getLastRequest();
            $this->lastResponse = $client->__getLastResponse();
            return $ret;
        } catch (SoapFault $e) {
            $this->lastRequest  = $client->__getLastRequest();
            $this->lastResponse = $client->__getLastResponse();
            throw new Exception(
                "ERRO SOAP:\n{$e->getMessage()}\n\nREQUEST:\n{$this->lastRequest}\n\nRESPONSE:\n{$this->lastResponse}",
                0,
                $e
            );
        }
    }

    // ---------------------- SOAP CLIENT (com bundle WSDL/XSD + PFX->PEM) ----------------------

    private function client(): SoapClient
    {
        $wsdlLocal = $this->ensureWsdlBundle();
        $pemPath   = $this->ensurePemFromPfx();

        return new SoapClient($wsdlLocal, [
            'soap_version'       => SOAP_1_1,
            'trace'              => true,
            'exceptions'         => true,
            'cache_wsdl'         => WSDL_CACHE_NONE,
            'connection_timeout' => 60,
            'features'           => SOAP_SINGLE_ELEMENT_ARRAYS,
            'stream_context'     => stream_context_create([
                'ssl' => [
                    'local_cert'        => $pemPath,     // PEM gerado do PFX
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                    'ciphers'           => 'DEFAULT@SECLEVEL=1',
                ],
                'http' => [
                    'header'  => "Connection: close\r\nUser-Agent: Mozilla/5.0 (NFSe Client)\r\n",
                    'timeout' => 60,
                ]
            ])
        ]);
    }

    private function ensureWsdlBundle(): string
    {
        $dir = rtrim(self::BUNDLE_DIR, '/\\');
        if (!is_dir($dir)) @mkdir($dir, 0775, true);

        $wsdlLocal = $dir . DIRECTORY_SEPARATOR . self::LOCAL_WSDL_FILE;
        if (is_file($wsdlLocal) && filesize($wsdlLocal) > 200) {
            return $wsdlLocal;
        }

        $wsdlContent = $this->curlGetWithPfx(self::REMOTE_WSDL);
        if (!$this->looksLikeWsdl($wsdlContent)) {
            throw new Exception("Conteúdo baixado não parece WSDL. Início:\n" . substr($wsdlContent, 0, 300));
        }

        $xsdUrls = $this->extractXsdUrlsFromWsdl($wsdlContent);

        $replacements = [];
        foreach ($xsdUrls as $url) {
            $n = $this->extractXsdNumber($url);
            $filename = $n ? "schema_{$n}.xsd" : ('schema_' . md5($url) . '.xsd');

            $xsdLocal = $dir . DIRECTORY_SEPARATOR . $filename;
            $xsdContent = $this->curlGetWithPfx($url);

            if (!$this->looksLikeXsd($xsdContent)) {
                throw new Exception("XSD baixado não parece XSD: {$url}\nInício:\n" . substr($xsdContent, 0, 250));
            }

            file_put_contents($xsdLocal, $xsdContent);
            $replacements[$url] = $filename;
        }

        $wsdlBundled = $wsdlContent;
        foreach ($replacements as $remote => $localFile) {
            $wsdlBundled = str_replace('schemaLocation="'.$remote.'"', 'schemaLocation="'.$localFile.'"', $wsdlBundled);
            $wsdlBundled = str_replace("schemaLocation='".$remote."'", "schemaLocation='".$localFile."'", $wsdlBundled);
        }

        file_put_contents($wsdlLocal, $wsdlBundled);

        if (!is_file($wsdlLocal) || filesize($wsdlLocal) < 200) {
            throw new Exception('Falha ao criar WSDL local em: ' . $wsdlLocal);
        }

        return $wsdlLocal;
    }

    private function extractXsdUrlsFromWsdl(string $wsdlContent): array
    {
        $urls = [];

        if (preg_match_all('/schemaLocation="([^"]+\?xsd=\d+)"/i', $wsdlContent, $m)) {
            $urls = array_merge($urls, $m[1] ?? []);
        }
        if (preg_match_all("/schemaLocation='([^']+\?xsd=\d+)'/i", $wsdlContent, $m2)) {
            $urls = array_merge($urls, $m2[1] ?? []);
        }

        $urls = array_values(array_unique(array_filter($urls)));

        // resolve relativos (se existirem)
        $urls = array_map(function ($u) {
            if (stripos($u, 'http') === 0) return $u;
            $base = preg_replace('/\?.*$/', '', self::REMOTE_WSDL);
            $base = preg_replace('/\/[^\/]+$/', '/', $base);
            return rtrim($base, '/') . '/' . ltrim($u, '/');
        }, $urls);

        return $urls;
    }

    private function extractXsdNumber(string $url): ?int
    {
        if (preg_match('/\?xsd=(\d+)/', $url, $m)) return (int)$m[1];
        return null;
    }

    private function looksLikeWsdl(string $s): bool
    {
        return (stripos($s, '<definitions') !== false) || (stripos($s, 'wsdl:definitions') !== false);
    }

    private function looksLikeXsd(string $s): bool
    {
        return (stripos($s, '<schema') !== false) || (stripos($s, 'xs:schema') !== false) || (stripos($s, 'xsd:schema') !== false);
    }

    private function curlGetWithPfx(string $url): string
    {
        if (!function_exists('curl_init')) throw new Exception('ext-curl não habilitada.');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 25,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,

            // mTLS via PFX/P12
            CURLOPT_SSLCERT     => $this->certPath,
            CURLOPT_SSLCERTTYPE => 'P12',
            CURLOPT_KEYPASSWD   => $this->certPassword,

            CURLOPT_HTTPHEADER => [
                'User-Agent: Mozilla/5.0 (NFSe Client)',
                'Connection: close',
            ],
        ]);

        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $body === '' || $code >= 400) {
            throw new Exception("Falha ao baixar ($url) (HTTP {$code}): " . ($err ?: 'resposta vazia'));
        }

        return $body;
    }

    private function ensurePemFromPfx(): string
    {
        if (!is_file($this->certPath)) {
            throw new Exception('Certificado PFX não encontrado: ' . $this->certPath);
        }

        $hash = md5($this->certPath . '|' . filemtime($this->certPath));
        $pemPath = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'nfse_natal_' . $hash . '.pem';

        if (is_file($pemPath) && filesize($pemPath) > 200) {
            return $pemPath;
        }

        $cmd = sprintf(
            'openssl pkcs12 -in %s -out %s -nodes -passin pass:%s 2>&1',
            escapeshellarg($this->certPath),
            escapeshellarg($pemPath),
            escapeshellarg($this->certPassword)
        );

        $out = shell_exec($cmd);

        if (!is_file($pemPath) || filesize($pemPath) < 200) {
            throw new Exception("Falha ao converter PFX->PEM. Saída:\n" . (string)$out);
        }

        @chmod($pemPath, 0600);
        return $pemPath;
    }
}
