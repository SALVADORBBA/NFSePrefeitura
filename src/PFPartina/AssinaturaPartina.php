<?php
namespace NFSePrefeitura\NFSe\PFPartina;

use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use NFePHP\Common\Certificate;
use NFePHP\Common\Signer;
use NFePHP\Common\Strings;

class AssinaturaPartina
{
    private Certificate $certificate;
    private int $algorithm = OPENSSL_ALGO_SHA1;
    private array $canonical = [false, false, null, null];

    private string $nsNfse = 'http://www.abrasf.org.br/nfse.xsd';
    private string $nsDs   = 'http://www.w3.org/2000/09/xmldsig#';

    public function __construct(string $certPath, string $certPassword)
    {
        if (!is_file($certPath)) {
            throw new InvalidArgumentException("Certificado não encontrado: {$certPath}");
        }

        $this->certificate = Certificate::readPfx(
            file_get_contents($certPath),
            $certPassword
        );
    }

    public function assinarLoteRps(string $xml): string
    {
        $xml = $this->normalize($xml);

        $dom = new DOMDocument();
        $dom->loadXML($xml);
        
        $infDeclaracao = $dom->getElementsByTagName('InfDeclaracaoPrestacaoServico')->item(0);
        $loteRps = $dom->getElementsByTagName('LoteRps')->item(0);
        
        if (!$infDeclaracao) {
            throw new InvalidArgumentException('Nó InfDeclaracaoPrestacaoServico não encontrado no XML');
        }
        
        if (!$loteRps) {
            throw new InvalidArgumentException('Nó LoteRps não encontrado no XML');
        }

        // Remove existing signatures
        $xml = $this->removerAssinaturas($xml);

        // Sign RPS
        $xml = Signer::sign(
            $this->certificate,
            $xml,
            'InfDeclaracaoPrestacaoServico',
            'Id',
            $this->algorithm,
            $this->canonical
        );

        // Reposition RPS signature
        $xml = $this->reposicionarAssinaturaRps($xml);

        // Sign batch
        $xml = $this->assinarLoteComoIrmaDoLoteRps($xml);

        // Validate we have 2 signatures
        $count = $this->contarSignatures($xml);
        if ($count < 2) {
            throw new InvalidArgumentException("Ainda não gerou 2 assinaturas. Encontrado(s): {$count}");
        }

        return Strings::clearXmlString($xml);
    }

    private function normalize(string $xml): string
    {
        if (trim($xml) === '') {
            throw new InvalidArgumentException('XML vazio');
        }
        return Strings::clearXmlString($xml);
    }

    private function loadDom(string $xml): array
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;

        if (!$dom->loadXML($xml, LIBXML_NOBLANKS | LIBXML_NOCDATA)) {
            throw new InvalidArgumentException('XML inválido (loadXML falhou)');
        }

        $xp = new DOMXPath($dom);
        $xp->registerNamespace('ns', $this->nsNfse);
        $xp->registerNamespace('ds', $this->nsDs);

        return [$dom, $xp];
    }

    private function contarSignatures(string $xml): int
    {
        [$dom, $xp] = $this->loadDom($xml);
        return $xp->query('//ds:Signature')->length;
    }

    private function removerAssinaturas(string $xml): string
    {
        [$dom, $xp] = $this->loadDom($xml);
        foreach ($xp->query('//ds:Signature') as $sig) {
            $sig->parentNode?->removeChild($sig);
        }
        return $dom->saveXML($dom->documentElement);
    }

    private function reposicionarAssinaturaRps(string $xml): string
    {
        [$dom, $xp] = $this->loadDom($xml);

        $inf = $xp->query('//ns:InfDeclaracaoPrestacaoServico')->item(0)
            ?: $dom->getElementsByTagName('InfDeclaracaoPrestacaoServico')->item(0);

        if (!$inf) {
            throw new InvalidArgumentException('InfDeclaracaoPrestacaoServico não encontrado');
        }

        $rpsId = $inf->attributes?->getNamedItem('Id')?->nodeValue;
        if (!$rpsId) {
            throw new InvalidArgumentException('Atributo Id do RPS não encontrado');
        }

        $sigRps = $xp->query('//ds:Signature[.//ds:Reference[@URI="#' . $this->xpEsc($rpsId) . '"]]')->item(0);
        if (!$sigRps) {
            throw new InvalidArgumentException('Assinatura do RPS não encontrada');
        }

        if ($sigRps->parentNode !== $inf->parentNode || $inf->nextSibling !== $sigRps) {
            $sigRps->parentNode?->removeChild($sigRps);
            $inf->parentNode->insertBefore($sigRps, $inf->nextSibling);
        }

        return $dom->saveXML($dom->documentElement);
    }

    private function assinarLoteComoIrmaDoLoteRps(string $xml): string
    {
        // Implementation similar to Natal's version
        // Would need Partina's specific signing requirements
        return $xml;
    }

    private function xpEsc(string $str): string
    {
        return str_replace('"', '\"', $str);
    }
}