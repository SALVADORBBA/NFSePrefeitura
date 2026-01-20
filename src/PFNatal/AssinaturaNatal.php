<?php
namespace NFSePrefeitura\NFSe\PFNatal;

use DOMDocument;
use DOMElement;
use Exception;

class AssinaturaNatal
{
    private string $certPath;     // PFX
    private string $certPassword; // senha PFX

    public function __construct(string $certPath, string $certPassword)
    {
        $this->certPath = $certPath;
        $this->certPassword = $certPassword;
    }

    /**
     * Assina o XML do Lote RPS.
     * - Preferência: InfDeclaracaoPrestacaoServico (quando existir)
     * - Fallback: InfRps (quando o layout não tem InfDeclaracaoPrestacaoServico)
     */
    public function assinarLoteRps(string $xml): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        if (!$dom->loadXML($xml)) {
            throw new Exception("XML inválido: não foi possível carregar no DOM.");
        }

        // 1) localiza o nó a assinar
        $nodeToSign = $this->findFirstNode($dom, ['InfDeclaracaoPrestacaoServico', 'InfRps', 'LoteRps']);

        if (!$nodeToSign) {
            throw new Exception("Nenhum nó assinável encontrado (InfDeclaracaoPrestacaoServico / InfRps / LoteRps).");
        }

        /** @var DOMElement $nodeToSign */
        $idAttr = $this->getIdAttribute($nodeToSign);

        if (!$idAttr) {
            throw new Exception("Nó {$nodeToSign->tagName} não possui atributo Id.");
        }

        // 2) carrega certificado/chave do PFX
        $pfx = file_get_contents($this->certPath);
        if (!$pfx) {
            throw new Exception("Não foi possível ler o PFX em: {$this->certPath}");
        }

        $certs = [];
        if (!openssl_pkcs12_read($pfx, $certs, $this->certPassword)) {
            throw new Exception("Falha ao ler PFX (senha inválida ou arquivo corrompido).");
        }

        $privateKey = $certs['pkey'] ?? null;
        $publicCert = $certs['cert'] ?? null;

        if (!$privateKey || !$publicCert) {
            throw new Exception("PFX não contém chave privada/certificado.");
        }

        // 3) gera assinatura XMLDSig (enveloped)
        $signature = $this->buildXmlDsigSignature(
            $dom,
            $idAttr,
            $privateKey,
            $publicCert
        );

        // 4) injeta assinatura dentro do nó assinado (enveloped signature)
        // Normalmente vai no final do nó (antes do fechamento).
        $nodeToSign->appendChild($signature);

        return $dom->saveXML();
    }

    /**
     * Procura o primeiro nó existente dentre os nomes informados.
     */
    private function findFirstNode(DOMDocument $dom, array $tagNames): ?DOMElement
    {
        foreach ($tagNames as $name) {
            $list = $dom->getElementsByTagName($name);
            if ($list && $list->length > 0) {
                return $list->item(0);
            }
        }
        return null;
    }

    /**
     * Retorna o valor do atributo Id (case sensitive), se existir.
     */
    private function getIdAttribute(DOMElement $el): ?string
    {
        if ($el->hasAttribute('Id')) {
            return (string) $el->getAttribute('Id');
        }
        // alguns layouts usam "id"
        if ($el->hasAttribute('id')) {
            return (string) $el->getAttribute('id');
        }
        return null;
    }

    /**
     * Cria uma assinatura XMLDSig enveloped com RSA-SHA1 + SHA1 (padrão ABRASF antigo).
     * Se sua prefeitura exigir SHA256, eu ajusto aqui.
     */
    private function buildXmlDsigSignature(
        DOMDocument $dom,
        string $idValue,
        string $privateKeyPem,
        string $publicCertPem
    ): DOMElement {

        $dsigNs = 'http://www.w3.org/2000/09/xmldsig#';

        // ---- SignedInfo ----
        $signatureEl = $dom->createElementNS($dsigNs, 'Signature');

        $signedInfo = $dom->createElementNS($dsigNs, 'SignedInfo');

        $canonMethod = $dom->createElementNS($dsigNs, 'CanonicalizationMethod');
        $canonMethod->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $signedInfo->appendChild($canonMethod);

        $sigMethod = $dom->createElementNS($dsigNs, 'SignatureMethod');
        $sigMethod->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#rsa-sha1');
        $signedInfo->appendChild($sigMethod);

        $reference = $dom->createElementNS($dsigNs, 'Reference');
        $reference->setAttribute('URI', '#' . $idValue);

        $transforms = $dom->createElementNS($dsigNs, 'Transforms');

        $t1 = $dom->createElementNS($dsigNs, 'Transform');
        $t1->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
        $transforms->appendChild($t1);

        $t2 = $dom->createElementNS($dsigNs, 'Transform');
        $t2->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $transforms->appendChild($t2);

        $reference->appendChild($transforms);

        $digestMethod = $dom->createElementNS($dsigNs, 'DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
        $reference->appendChild($digestMethod);

        // digest do nó referenciado (canonicalizado sem a assinatura)
        $digestValue = $dom->createElementNS($dsigNs, 'DigestValue');
        $digestValue->nodeValue = $this->digestNodeById($dom, $idValue);
        $reference->appendChild($digestValue);

        $signedInfo->appendChild($reference);
        $signatureEl->appendChild($signedInfo);

        // ---- SignatureValue ----
        $signedInfoCanon = $signedInfo->C14N(true, false);
        $signatureValueRaw = '';
        openssl_sign($signedInfoCanon, $signatureValueRaw, $privateKeyPem, OPENSSL_ALGO_SHA1);

        $signatureValue = $dom->createElementNS($dsigNs, 'SignatureValue', base64_encode($signatureValueRaw));
        $signatureEl->appendChild($signatureValue);

        // ---- KeyInfo / X509Data ----
        $keyInfo = $dom->createElementNS($dsigNs, 'KeyInfo');
        $x509Data = $dom->createElementNS($dsigNs, 'X509Data');

        $cleanCert = $this->cleanPemCertToBase64($publicCertPem);
        $x509Cert = $dom->createElementNS($dsigNs, 'X509Certificate', $cleanCert);

        $x509Data->appendChild($x509Cert);
        $keyInfo->appendChild($x509Data);
        $signatureEl->appendChild($keyInfo);

        return $signatureEl;
    }

    /**
     * Calcula DigestValue do elemento que tem Id=$idValue.
     */
    private function digestNodeById(DOMDocument $dom, string $idValue): string
    {
        $xpath = new \DOMXPath($dom);

        // procura qualquer elemento com atributo Id="..."
        $query = "//*[@Id='{$idValue}' or @id='{$idValue}']";
        $nodes = $xpath->query($query);

        if (!$nodes || $nodes->length === 0) {
            throw new Exception("Não foi possível localizar nó com Id={$idValue} para digest.");
        }

        /** @var DOMElement $el */
        $el = $nodes->item(0);

        // C14N do elemento (sem comentários)
        $canon = $el->C14N(true, false);

        // SHA1 + base64 (padrão ABRASF clássico)
        return base64_encode(sha1($canon, true));
    }

    private function cleanPemCertToBase64(string $pem): string
    {
        $pem = str_replace(["-----BEGIN CERTIFICATE-----", "-----END CERTIFICATE-----", "\r", "\n"], '', $pem);
        return trim($pem);
    }
}
