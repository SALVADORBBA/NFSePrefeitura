<?php

namespace NFSePrefeitura\NFSe\PFChapeco;

use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;
use RuntimeException;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

/**
 * Assinatura digital de XML para NFS-e Chapecó/SC (padrão ABRASF)
 * - Remove prefixo ds: da assinatura (exigido por Chapecó)
 * - Suporta PFX ou PEM (certificado + chave)
 * - Assina GerarNfseEnvio ou LoteRps
 */
class AssinaturaChapeco
{
    private DOMDocument $dom;
    private DOMXPath $xpath;
    private string $pfxPath;
    private string $pfxPassword;
    private string $certPem;
    private string $keyPem;

    public function __construct(string $pfxPath, string $pfxPassword)
    {
        if (!file_exists($pfxPath)) {
            throw new InvalidArgumentException("PFX não encontrado: {$pfxPath}");
        }

        $this->pfxPath     = $pfxPath;
        $this->pfxPassword = $pfxPassword;

        $this->extractFromPfx();
    }

    /**
     * Carrega o XML num DOMDocument com os namespaces necessários
     */
    private function loadDom(string $xml): void
    {
        $this->dom                     = new DOMDocument('1.0', 'UTF-8');
        $this->dom->preserveWhiteSpace = false;
        $this->dom->formatOutput       = true;

        libxml_use_internal_errors(true);
        if (!$this->dom->loadXML($xml)) {
            $errors = libxml_get_errors();
            $msg    = [];
            foreach ($errors as $e) {
                $msg[] = $e->message;
            }
            throw new InvalidArgumentException('XML malformado: ' . implode(' | ', $msg));
        }
        libxml_clear_errors();

        // Registra os namespaces para XPath
        $this->xpath = new DOMXPath($this->dom);
        $this->xpath->registerNamespace('nfse', 'http://www.abrasf.org.br/nfse.xsd');
        $this->xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
    }

    /**
     * Remove todas as assinaturas existentes no XML
     */
    private function removerAssinaturas(): void
    {
        $signatures = $this->dom->getElementsByTagName('Signature');
        while ($signatures->length > 0) {
            $sig = $signatures->item(0);
            if ($sig && $sig->parentNode) {
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
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
            ['uri' => '#' . $id]
        );

        // Chave privada (CORREÇÃO: isCert = false)
        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, ['type' => 'private']);
        $key->loadKey($this->keyPem, false, false);

        $dsig->sign($key);

        // adiciona o certificado no KeyInfo com todos os elementos necessários
        $this->adicionarCertificadoCompleto($dsig);

        // insere Signature DENTRO do elemento assinado (no final)
        $dsig->appendSignature($elementToSign);
    }

    private function normalizeCert(string $pem): string
    {
        // remove cabeçalhos e espaços, retorna base64 quebrado a cada 64 chars
        $pem = trim($pem);
        $pem = str_replace(["\r", "\n", " "], "", $pem);
        $pem = str_replace(['-----BEGINCERTIFICATE-----', '-----ENDCERTIFICATE-----'], '', $pem);

        return chunk_split($pem, 64, "\n");
    }

    /**
     * Adiciona certificado completo com todos os elementos X509 necessários
     * na ordem correta conforme o padrão XMLDSig
     */
    private function adicionarCertificadoCompleto(XMLSecurityDSig $dsig): void
    {
        // Verifica se o signatureNode existe, se não, cria a estrutura básica
        $signature = $dsig->signatureNode;
        
        if ($signature === null) {
            // Cria a estrutura de assinatura manualmente se não existir
            $signature = $this->dom->createElement('Signature');
            $signature->setAttribute('xmlns', 'http://www.w3.org/2000/09/xmldsig#');
            $dsig->signatureNode = $signature;
        }
        
        // Remove KeyInfo existente se houver
        $keyInfos = $signature->getElementsByTagName('KeyInfo');
        while ($keyInfos->length > 0) {
            $keyInfo = $keyInfos->item(0);
            if ($keyInfo && $keyInfo->parentNode) {
                $keyInfo->parentNode->removeChild($keyInfo);
            }
        }
        
        // Cria novo KeyInfo
        $keyInfo = $this->dom->createElement('KeyInfo');
        $signature->appendChild($keyInfo);

        // Cria o elemento X509Data
        $x509Data = $this->dom->createElement('X509Data');
        $keyInfo->appendChild($x509Data);

        // Extrai informações do certificado
        $certInfo = openssl_x509_parse($this->certPem);
        if ($certInfo) {
            // Adiciona X509IssuerSerial PRIMEIRO (ordem correta)
            $x509IssuerSerial = $this->dom->createElement('X509IssuerSerial');
            $x509Data->appendChild($x509IssuerSerial);

            // X509IssuerName
            $issuerName = $this->montarIssuerName($certInfo['issuer']);
            $x509IssuerName = $this->dom->createElement('X509IssuerName', $issuerName);
            $x509IssuerSerial->appendChild($x509IssuerName);

            // X509SerialNumber
            $serialNumber = $this->formatarSerialNumber($certInfo['serialNumber']);
            $x509SerialNumber = $this->dom->createElement('X509SerialNumber', $serialNumber);
            $x509IssuerSerial->appendChild($x509SerialNumber);

            // Adiciona X509SubjectName DEPOIS do X509IssuerSerial
            if (isset($certInfo['subject'])) {
                $subjectName = $this->montarSubjectName($certInfo['subject']);
                $x509SubjectName = $this->dom->createElement('X509SubjectName', $subjectName);
                $x509Data->appendChild($x509SubjectName);
            }
        }

        // Adiciona o certificado X509 POR ÚLTIMO (ordem correta)
        $x509Cert = $this->dom->createElement('X509Certificate', $this->normalizeCert($this->certPem));
        $x509Data->appendChild($x509Cert);
    }

    /**
     * Monta o issuer name no formato esperado
     */
    private function montarIssuerName(array $issuer): string
    {
        $parts = [];
        $order = ['C', 'ST', 'L', 'O', 'OU', 'CN', 'emailAddress'];
        
        foreach ($order as $key) {
            if (isset($issuer[$key])) {
                $parts[] = "$key=" . $issuer[$key];
            }
        }
        
        return implode(', ', $parts);
    }

    /**
     * Monta o subject name no formato esperado
     */
    private function montarSubjectName(array $subject): string
    {
        $parts = [];
        $order = ['C', 'ST', 'L', 'O', 'OU', 'CN', 'emailAddress'];
        
        foreach ($order as $key) {
            if (isset($subject[$key])) {
                $parts[] = "$key=" . $subject[$key];
            }
        }
        
        return implode(', ', $parts);
    }

    /**
     * Formata o número de série do certificado
     */
    private function formatarSerialNumber(string $serialNumber): string
    {
        // Remove espaços e converte para decimal se estiver em hexadecimal
        $serialNumber = str_replace(' ', '', $serialNumber);
        if (strpos($serialNumber, ':') !== false) {
            // Está em formato hexadecimal com separadores
            $hex = str_replace(':', '', $serialNumber);
            return strval(hexdec($hex));
        }
        return $serialNumber;
    }

    /**
     * Remove o prefixo ds: e converte xmlns:ds para xmlns padrão na assinatura XML
     * Mantém a estrutura correta do XMLDSig
     */
    private function removerPrefixoDs(string $xml): string
    {
        // Converte xmlns:ds para xmlns padrão apenas nos elementos da assinatura
        $xml = str_replace('xmlns:ds="http://www.w3.org/2000/09/xmldsig#"', 'xmlns="http://www.w3.org/2000/09/xmldsig#"', $xml);
        
        // Remove o prefixo ds: dos elementos
        $xml = str_replace('<ds:', '<', $xml);
        $xml = str_replace('</ds:', '</', $xml);
        
        // Mantém a indentação e não remove espaços desnecessariamente
        return $xml;
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

    /**
     * Assina XML para Chapecó (padrão ABRASF) - Função genérica
     * Remove duplicação de código entre assinarGerarNfseEnvio e assinarLoteRps
     * 
     * @param string $xml XML a ser assinado
     * @param string $nodeName Nome do nó a ser assinado ('GerarNfseEnvio' ou 'LoteRps')
     * @param bool $removeInfDeclaracaoId Se deve remover Id de InfDeclaracaoPrestacaoServico (usado para LoteRps)
     * @return string XML assinado
     */
    private function assinarXml(string $xml, string $nodeName, bool $removeInfDeclaracaoId = false): string
    {
        $this->loadDom($xml);
        $this->removerAssinaturas();

        $node = $this->getFirstNodeByLocalName($nodeName);
        if (!$node) {
            throw new InvalidArgumentException("Nó {$nodeName} não encontrado no XML.");
        }
        
        // Garante que o nó tenha um ID
        $id = $this->ensureId($node, $nodeName);
        
        // Remove o atributo Id de InfDeclaracaoPrestacaoServico se solicitado
        if ($removeInfDeclaracaoId) {
            $infDeclaracoes = $this->xpath->query('//*[local-name()="InfDeclaracaoPrestacaoServico"]');
            foreach ($infDeclaracoes as $infDeclaracao) {
                if ($infDeclaracao->hasAttribute('Id')) {
                    $infDeclaracao->removeAttribute('Id');
                }
            }
        }
        
        // Assina o nó
        $this->signElementById($node, $id);

        // Remove qualquer <Signature> fora do nó principal
        $signatures = $this->dom->getElementsByTagName('Signature');
        foreach ($signatures as $sig) {
            if ($sig->parentNode !== $node) {
                $sig->parentNode->removeChild($sig);
            }
        }

        // Garante que só exista UMA Signature dentro do nó
        $sigCount = 0;
        foreach ($node->childNodes as $child) {
            if ($child->nodeName === 'Signature' || $child->localName === 'Signature') {
                $sigCount++;
                if ($sigCount > 1) {
                    $node->removeChild($child);
                }
            }
        }

        $xml = $this->dom->saveXML();
        $xml = $this->removerPrefixoDs($xml); // Remove o prefixo ds:
        
        return $xml;
    }

    /**
     * Assina o nó GerarNfseEnvio para Chapecó (padrão correto)
     * @deprecated Use assinarXmlGenerico() para maior flexibilidade
     */
    public function assinarGerarNfseEnvio(string $xml): string
    {
        return $this->assinarXml($xml, 'GerarNfseEnvio', false);
    }

    /**
     * Assina o LoteRps para Chapecó - Estrutura 100% igual ao modelo
     * @deprecated Use assinarXmlGenerico() para maior flexibilidade
     */
    public function assinarLoteRps(string $xml): string
    {
        return $this->assinarXml($xml, 'LoteRps', true);
    }

    /**
     * Assina XML para Chapecó (função genérica recomendada)
     * 
     * @param string $xml XML a ser assinado
     * @param string $tipo Tipo de assinatura ('GerarNfseEnvio' ou 'LoteRps')
     * @return string XML assinado
     */
    public function assinarXmlGenerico(string $xml, string $tipo = 'GerarNfseEnvio'): string
    {
        $tiposValidos = ['GerarNfseEnvio', 'LoteRps'];
        if (!in_array($tipo, $tiposValidos, true)) {
            throw new InvalidArgumentException("Tipo de assinatura inválido: {$tipo}. Use: " . implode(', ', $tiposValidos));
        }
        
        return $this->assinarXml($xml, $tipo, $tipo === 'LoteRps');
    }
}