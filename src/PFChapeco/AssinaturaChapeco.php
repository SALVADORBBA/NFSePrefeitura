<?php
namespace NFSePrefeitura\NFSe\PFChapeco;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use InvalidArgumentException;
use RuntimeException;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

class AssinaturaChapeco
{
    private string $pfxPath;
    private string $pfxPassword;

    private string $certPem; // PEM completo: -----BEGIN CERTIFICATE----- ... -----END CERTIFICATE-----
    private string $keyPem;  // PEM chave privada

    private DOMDocument $dom;
    private DOMXPath $xpath;

    public function __construct(string $pfxPath, string $pfxPassword)
    {
        if (!is_file($pfxPath)) {
            throw new InvalidArgumentException("PFX não encontrado em: {$pfxPath}");
        }

        $this->pfxPath     = $pfxPath;
        $this->pfxPassword = $pfxPassword;

        $this->extractFromPfx();
    }

    // =========================================================
    // API PÚBLICA
    // =========================================================

    /**
     * Assina o nó InfDeclaracaoPrestacaoServico e insere <Signature> como IRMÃ dentro de <Rps>
     * (logo após </InfDeclaracaoPrestacaoServico>).
     */
    public function assinarRps(string $xml): string
    {
        $this->loadDom($xml);
        $this->removeAllSignatures();

        $inf = $this->getFirstNodeByLocalName('InfDeclaracaoPrestacaoServico');
        $id  = $this->ensureId($inf, 'Rps');

        $parentRps = $inf->parentNode;
        if (!$parentRps instanceof DOMElement || $parentRps->localName !== 'Rps') {
            throw new InvalidArgumentException('Estrutura inesperada: InfDeclaracaoPrestacaoServico deve estar dentro de <Rps>.');
        }

        $sigNode = $this->createSignatureNodeForElement($inf, $id);
        $this->insertAfter($sigNode, $inf);

        return $this->dom->saveXML();
    }

    /**
     * Assina o nó LoteRps e insere <Signature> como IRMÃ após </LoteRps>
     * (igual ao modelo oficial de Chapecó que você colou).
     */
    public function assinarLoteRps(string $xml): string
    {
        $this->loadDom($xml);
        $this->removeAllSignatures();

        $lote = $this->getFirstNodeByLocalName('LoteRps');
        $id   = $this->ensureId($lote, 'Lote');

        $sigNode = $this->createSignatureNodeForElement($lote, $id);
        $this->insertAfter($sigNode, $lote);

        return $this->dom->saveXML();
    }

    /**
     * Assina:
     * - Todos os InfDeclaracaoPrestacaoServico (RPS)
     * - O LoteRps (primeiro encontrado)
     */
    public function assinarRpsELote(string $xml): string
    {
        $this->loadDom($xml);
        $this->removeAllSignatures();

        // 1) Assina todos os RPS (InfDeclaracaoPrestacaoServico)
        $infList = $this->xpath->query('//*[local-name()="InfDeclaracaoPrestacaoServico"]');
        if ($infList) {
            foreach ($infList as $node) {
                if (!$node instanceof DOMElement) {
                    continue;
                }

                $id = $this->ensureId($node, 'Rps');

                $parentRps = $node->parentNode;
                if ($parentRps instanceof DOMElement && $parentRps->localName === 'Rps') {
                    $sigNode = $this->createSignatureNodeForElement($node, $id);
                    $this->insertAfter($sigNode, $node);
                }
            }
        }

        // 2) Assina o LoteRps (primeiro)
        $loteNodes = $this->xpath->query('//*[local-name()="LoteRps"]');
        if ($loteNodes && $loteNodes->length > 0) {
            $lote = $loteNodes->item(0);
            if ($lote instanceof DOMElement) {
                $id = $this->ensureId($lote, 'Lote');
                $sigNode = $this->createSignatureNodeForElement($lote, $id);
                $this->insertAfter($sigNode, $lote);
            }
        }

        return $this->dom->saveXML();
    }

    // =========================================================
    // DOM / XPATH
    // =========================================================

    private function loadDom(string $xml): void
    {
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->preserveWhiteSpace = false;
        $this->dom->formatOutput = false;

        if (@$this->dom->loadXML($xml) !== true) {
            throw new InvalidArgumentException("XML inválido (não foi possível carregar no DOM).");
        }

        $this->xpath = new DOMXPath($this->dom);
        $this->xpath->registerNamespace('ns', 'http://www.abrasf.org.br/nfse.xsd');
    }

    private function removeAllSignatures(): void
    {
        $nodes = $this->xpath->query('//*[local-name()="Signature"]');
        if (!$nodes) {
            return;
        }

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

    /**
     * Gera/garante Id:
     * - prefix 'Lote' => usa NumeroLote
     * - prefix 'Rps'  => usa IdentificacaoRps/Numero
     */
    private function ensureId(DOMElement $el, string $prefix): string
    {
        $id = trim((string)$el->getAttribute('Id'));
        if ($id !== '') {
            return $id;
        }

        $numero = '';

        if ($prefix === 'Lote') {
            $numNode = $this->xpath->query('.//*[local-name()="NumeroLote"]', $el)->item(0);
            $numero  = $numNode ? preg_replace('/\D+/', '', (string)$numNode->nodeValue) : '';
            if ($numero === '') {
                $numero = (string)time();
            }
            $id = 'Lote' . $numero;
        } else {
            $numNode = $this->xpath->query('.//*[local-name()="IdentificacaoRps"]/*[local-name()="Numero"]', $el)->item(0);
            $numero  = $numNode ? preg_replace('/\D+/', '', (string)$numNode->nodeValue) : '';
            if ($numero === '') {
                $numero = (string)time();
            }
            $id = 'Rps' . $numero;
        }

        $el->setAttribute('Id', $id);
        return $id;
    }

    private function insertAfter(DOMNode $newNode, DOMNode $referenceNode): void
    {
        $parent = $referenceNode->parentNode;
        if (!$parent) {
            throw new RuntimeException('Não foi possível inserir Signature: nó de referência sem pai.');
        }

        $next = $referenceNode->nextSibling;
        if ($next) {
            $parent->insertBefore($newNode, $next);
        } else {
            $parent->appendChild($newNode);
        }
    }

    // =========================================================
    // ASSINATURA (xmlseclibs)
    // =========================================================

    /**
     * Cria o nó <Signature> para o elemento alvo (Id).
     * - Reference URI="#Id"
     * - KeyInfo com X509Certificate (NUNCA vazio)
     */
    private function createSignatureNodeForElement(DOMElement $elementToSign, string $id): DOMElement
    {
        $elementToSign->setIdAttribute('Id', true);

        $dsig = new XMLSecurityDSig();
        $dsig->setCanonicalMethod(XMLSecurityDSig::C14N);

        $dsig->addReference(
            $elementToSign,
            XMLSecurityDSig::SHA1,
            [
                'http://www.w3.org/2000/09/xmldsig#enveloped-signature',
                XMLSecurityDSig::C14N
            ],
            ['uri' => '#' . $id]
        );

        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, ['type' => 'private']);
        $key->loadKey($this->keyPem, false, false);
        $dsig->sign($key);

        // >>> Aqui é o ponto que resolve seu "X509Data vazio" <<<
        // Passa o PEM completo (BEGIN/END). O xmlseclibs extrai o base64 e monta X509Certificate corretamente.
        $this->assertCertPemIsValid($this->certPem);
        $dsig->add509Cert($this->certPem, true, false);

        // Gera Signature em container temporário para não inserir dentro do elemento assinado
        $container = $this->dom->createElement('TmpContainer');
        $this->dom->documentElement->appendChild($container);

        $dsig->appendSignature($container);

        $sig = null;
        foreach ($container->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'Signature') {
                $sig = $child;
                break;
            }
        }

        if ($container->parentNode) {
            $container->parentNode->removeChild($container);
        }

        if (!$sig instanceof DOMElement) {
            throw new RuntimeException('Falha ao criar nó Signature.');
        }

        // Confere X509Certificate
        $this->assertSignatureHasX509Certificate($sig);

        // Importa pro DOM final
        $sigImported = $this->dom->importNode($sig, true);
        return $sigImported instanceof DOMElement ? $sigImported : $sig;
    }

    private function assertCertPemIsValid(string $pem): void
    {
        $pem = trim($pem);

        if (strpos($pem, '-----BEGIN CERTIFICATE-----') === false || strpos($pem, '-----END CERTIFICATE-----') === false) {
            throw new RuntimeException('Certificado PEM inválido: faltando BEGIN/END CERTIFICATE.');
        }

        // tenta abrir como X509 (validação real)
        $x509 = @openssl_x509_read($pem);
        if (!$x509) {
            throw new RuntimeException('Certificado PEM inválido (openssl_x509_read falhou). Verifique extração do PFX.');
        }
        openssl_x509_free($x509);
    }

    private function assertSignatureHasX509Certificate(DOMElement $sig): void
    {
        $tmp = new DOMDocument('1.0', 'UTF-8');
        $tmp->loadXML($sig->ownerDocument->saveXML($sig));

        $xp = new DOMXPath($tmp);
        $xp->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        $node = $xp->query('//ds:X509Data/ds:X509Certificate')->item(0);
        if (!$node || trim((string)$node->nodeValue) === '') {
            throw new RuntimeException('Assinatura gerada sem X509Certificate (X509Data vazio). Verifique extração do PFX.');
        }
    }

    // =========================================================
    // PFX -> PEM (ROBUSTO)
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

        $pkey = $certs['pkey'] ?? null;
        $cert = $certs['cert'] ?? null;

        // Fallback: alguns PFX trazem cert útil em extracerts
        if ((!$cert || trim((string)$cert) === '') && !empty($certs['extracerts']) && is_array($certs['extracerts'])) {
            $cert = $certs['extracerts'][0] ?? null;
        }

        if (!$pkey || !$cert) {
            throw new RuntimeException("PFX lido, mas cert/pkey vazios.");
        }

        $cert = $this->sanitizeCertPem($cert);

        // valida com openssl
        $x509 = @openssl_x509_read($cert);
        if (!$x509) {
            throw new RuntimeException("Cert extraído do PFX não é X509 válido (openssl_x509_read falhou).");
        }
        openssl_x509_free($x509);

        $this->keyPem  = $pkey;
        $this->certPem = $cert;
    }

    /**
     * Remove "Bag Attributes" e mantém apenas o bloco BEGIN/END CERTIFICATE.
     */
    private function sanitizeCertPem(string $pem): string
    {
        $pem = trim($pem);

        if (preg_match('/-----BEGIN CERTIFICATE-----(.*?)-----END CERTIFICATE-----/s', $pem, $m)) {
            $body = preg_replace('/\s+/', '', trim($m[1]));
            if ($body === '') {
                throw new RuntimeException('Bloco do certificado encontrado, mas vazio.');
            }
            return "-----BEGIN CERTIFICATE-----\n" . chunk_split($body, 64, "\n") . "-----END CERTIFICATE-----\n";
        }

        // Se não encontrou o bloco, devolve como está (vai falhar na validação depois)
        return $pem;
    }
}
