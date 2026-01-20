<?php
namespace NFSePrefeitura\NFSe\PFChapeco;

use DOMDocument;
use DOMXPath;
use InvalidArgumentException;

class AssinaturaChapeco
{
    private $certPath;
    private $certPassword;
    private $xml;
    private $dom;
    private $xpath;

    public function __construct(string $certPath, string $certPassword)
    {
        $this->certPath = $certPath;
        $this->certPassword = $certPassword;
    }

    public function assinarLoteRps(string $xml): string
    {
        $this->xml = $xml;
        $this->loadDom();
        $this->removerAssinaturas();
        
        // Assinar Rps
        $this->assinarRps();
        
        // Assinar Lote
        $this->assinarLote();
        
        return $this->dom->saveXML();
    }

    private function loadDom(): void
    {
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->preserveWhiteSpace = false;
        $this->dom->formatOutput = true;
        $this->dom->loadXML($this->xml);
        $this->xpath = new DOMXPath($this->dom);
        $this->xpath->registerNamespace('ns', 'http://www.abrasf.org.br/nfse.xsd');
    }

    private function removerAssinaturas(): void
    {
        $signatures = $this->xpath->query('//*[local-name()="Signature"]');
        foreach ($signatures as $signature) {
            $signature->parentNode->removeChild($signature);
        }
    }

    private function assinarRps(): void
    {
        $infRps = $this->xpath->query('//ns:InfDeclaracaoPrestacaoServico')->item(0);
        if (!$infRps) {
            throw new InvalidArgumentException('Nó InfDeclaracaoPrestacaoServico não encontrado no XML');
        }
        
        $xmlRps = $this->dom->saveXML($infRps);
        $xmlRpsAssinado = $this->signXml($xmlRps, $infRps->getAttribute('Id'));
        
        $docRps = new DOMDocument();
        $docRps->loadXML($xmlRpsAssinado);
        
        $signatureNode = $docRps->getElementsByTagName('Signature')->item(0);
        $importedSignature = $this->dom->importNode($signatureNode, true);
        
        $infRps->parentNode->insertBefore($importedSignature, $infRps->nextSibling);
    }

    private function assinarLote(): void
    {
        $loteRps = $this->xpath->query('//ns:LoteRps')->item(0);
        if (!$loteRps) {
            throw new InvalidArgumentException('Nó LoteRps não encontrado no XML');
        }
        
        $xmlLote = $this->dom->saveXML($loteRps);
        $xmlLoteAssinado = $this->signXml($xmlLote, 'lote_' . $loteRps->getElementsByTagName('NumeroLote')->item(0)->nodeValue);
        
        $docLote = new DOMDocument();
        $docLote->loadXML($xmlLoteAssinado);
        
        $signatureNode = $docLote->getElementsByTagName('Signature')->item(0);
        $importedSignature = $this->dom->importNode($signatureNode, true);
        
        $loteRps->parentNode->insertBefore($importedSignature, $loteRps->nextSibling);
    }

    private function signXml(string $xml, string $id): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'xml');
        file_put_contents($tempFile, $xml);
        
        $command = sprintf(
            'xmlsec1 --sign --privkey-pem %s,%s --id-attr:Id "%s" --output %s %s',
            $this->certPath,
            $this->certPassword,
            $id,
            $tempFile . '.signed',
            $tempFile
        );
        
        exec($command, $output, $return);
        
        if ($return !== 0) {
            throw new InvalidArgumentException('Erro ao assinar XML: ' . implode('\n', $output));
        }
        
        $signedXml = file_get_contents($tempFile . '.signed');
        unlink($tempFile);
        unlink($tempFile . '.signed');
        
        return $signedXml;
    }
}