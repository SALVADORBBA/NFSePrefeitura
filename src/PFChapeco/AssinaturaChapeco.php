<?php
namespace NFSePrefeitura\NFSe\PFChapeco;

use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;
use RuntimeException;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

class AssinaturaChapeco
{
    private string $pfxPath;
    private string $pfxPassword;

    private DOMDocument $dom;
    private DOMXPath $xpath;

    private string $certPem; // CERT em PEM (extraído do PFX)
    private string $keyPem;  // KEY em PEM (extraída do PFX)

    public function __construct(string $pfxPath, string $pfxPassword)
    {
        if (!is_file($pfxPath)) {
            throw new InvalidArgumentException("PFX não encontrado em: {$pfxPath}");
        }

        $this->pfxPath     = $pfxPath;
        $this->pfxPassword = $pfxPassword;

        $this->extractFromPfx();
    }

    /**
     * Assina o LoteRps (ABRASF) usando PFX:
     * - remove <Signature> existentes
     * - garante atributo Id no LoteRps
     * - cria assinatura enveloped com Reference URI="#Id"
     * - insere Signature dentro do LoteRps
     */
    public function assinarLoteRps(string $xml): string
    {
        $this->loadDom($xml);
        $this->removerAssinaturas();

        $lote = $this->getFirstNodeByLocalName('LoteRps');
        $id   = $this->ensureId($lote, 'LoteRps');

        $this->signElementById($lote, $id);

        return $this->dom->saveXML();
    }

    /**
     * (Opcional) assina o nó InfDeclaracaoPrestacaoServico (se Chapecó exigir 2 assinaturas)
     */
    public function assinarRps(string $xml): string
    {
        $this->loadDom($xml);
        $this->removerAssinaturas();

        $inf = $this->getFirstNodeByLocalName('InfDeclaracaoPrestacaoServico');
        $id  = $this->requireId($inf, 'InfDeclaracaoPrestacaoServico');

        $this->signElementById($inf, $id);

        return $this->dom->saveXML();
    }

    // =========================================================
    // DOM
    // =========================================================

    private function loadDom(string $xml): void
    {
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->preserveWhiteSpace = false;
        $this->dom->formatOutput = true;

        if (@$this->dom->loadXML($xml) !== true) {
            throw new InvalidArgumentException("XML inválido (não foi possível carregar no DOM).");
        }

        $this->xpath = new DOMXPath($this->dom);
        $this->xpath->registerNamespace('ns', 'http://www.abrasf.org.br/nfse.xsd');
    }

    private function removerAssinaturas(): void
    {
        $nodes = $this->xpath->query('//*[local-name()="Signature"]');
        if (!$nodes) return;

        foreach ($nodes as $sig) {
            if ($sig->parentNode) {
                $sig->parentNode->removeChild($sig);
            }
        }
    }

    private function getFirstNodeByLocalName(string $name): DOMElement
    {
        $node = $this->xpath->query('//*[local-name()="' . $name . '"]')->item(0);
        if (!$node instanceof DOMElement) {
            throw new InvalidArgumentException("Nó {$name} não encontrado no XML");
        }
        return $node;
    }

    private function requireId(DOMElement $el, string $label): string
    {
        $id = trim((string)$el->getAttribute('Id'));
        if ($id === '') {
            throw new InvalidArgumentException("{$label} sem atributo Id (necessário para assinar).");
        }
        return $id;
    }

    private function ensureId(DOMElement $el, string $prefix): string
    {
        $id = trim((string)$el->getAttribute('Id'));
        if ($id !== '') return $id;

        // tenta NumeroLote (quando for lote)
        $numNode = $this->xpath->query('.//*[local-name()="NumeroLote"]', $el)->item(0);
        $numero  = $numNode ? preg_replace('/\D+/', '', (string)$numNode->nodeValue) : '';

        if ($numero === '') $numero = (string)time();

        $id = $prefix . $numero;
        $el->setAttribute('Id', $id);

        return $id;
    }

    // =========================================================
    // Assinatura (xmlseclibs)
    // =========================================================

    private function signElementById(DOMElement $elementToSign, string $id): void
    {
        // marca o atributo Id como do tipo ID para resolução do #URI
        $elementToSign->setIdAttribute('Id', true);

        $dsig = new XMLSecurityDSig();
        $dsig->setCanonicalMethod(XMLSecurityDSig::C14N);

        // ABRASF comumente usa SHA1 + RSA-SHA1 (muitas prefeituras antigas ainda exigem)
        $dsig->addReference(
            $elementToSign,
            XMLSecurityDSig::SHA1,
            [
                'http://www.w3.org/2000/09/xmldsig#enveloped-signature',
                XMLSecurityDSig::C14N
            ],
            ['uri' => '#' . $id]
        );

        // Chave privada (CORREÇÃO: isCert = false)
        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, ['type' => 'private']);
        $key->loadKey($this->keyPem, false, false);

        $dsig->sign($key);

        // adiciona o certificado no KeyInfo
        $dsig->add509Cert($this->normalizeCert($this->certPem), true, false, ['subjectName' => true]);

        // insere Signature DENTRO do elemento assinado (no final)
        $dsig->appendSignature($elementToSign);
    }

    private function normalizeCert(string $pem): string
    {
        // remove cabeçalhos e espaços, retorna base64 quebrado a cada 64 chars
        $pem = trim($pem);
        $pem = str_replace(["\r", "\n", " "], "", $pem);
        $pem = str_replace(["-----BEGINCERTIFICATE-----", "-----ENDCERTIFICATE-----"], "", $pem);

        return chunk_split($pem, 64, "\n");
    }

    // =========================================================
    // PFX -> PEM (memória)
    // =========================================================

    private function extractFromPfx(): void
    {
        $pfxContent = file_get_contents($this->pfxPath);
        if ($pfxContent === false) {
            throw new RuntimeException("Não foi possível ler o PFX: {$this->pfxPath}");
        }

        $certs = [];
        if (!openssl_pkcs12_read($pfxContent, $certs, $this->pfxPassword)) {
            throw new RuntimeException("Falha ao ler PFX. Verifique senha/arquivo.");
        }

        $cert = $certs['cert'] ?? null;
        $pkey = $certs['pkey'] ?? null;

        if (!$cert || !$pkey) {
            throw new RuntimeException("PFX lido, mas cert/pkey vazios.");
        }

        $this->certPem = $cert;
        $this->keyPem  = $pkey;
    }
}
