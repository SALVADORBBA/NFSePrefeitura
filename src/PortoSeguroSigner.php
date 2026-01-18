<?php


namespace NFSePrefeitura\NFSe;

namespace NotasFiscais\Abrasf;

use NFePHP\Common\Certificate;
use NFePHP\Common\Signer;

class PortoSeguroSigner
{
    protected $certificate;

    public function __construct($certPath, $certPassword)
    {
        $this->certificate = Certificate::readPfx(file_get_contents($certPath), $certPassword);
    }

    public function signRps($xml)
    {
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($xml);

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('ns', 'http://www.abrasf.org.br/nfse.xsd');
        $infNode = $xpath->query('//ns:InfDeclaracaoPrestacaoServico')->item(0);
        $id = $infNode->getAttribute('Id');

        // Gere a assinatura digital do nó InfDeclaracaoPrestacaoServico
        $signedXml = \NFePHP\Common\Signer::sign(
            $this->certificate,
            $dom->saveXML($infNode),
            'InfDeclaracaoPrestacaoServico',
            'Id',
            $id,
            [
                'canonical' => true,
                'signatureAlgorithm' => 'http://www.w3.org/2000/09/xmldsig#rsa-sha1',
                'digestAlgorithm' => 'http://www.w3.org/2000/09/xmldsig#sha1',
                'transformAlgorithm' => [
                    'http://www.w3.org/2000/09/xmldsig#enveloped-signature',
                    'http://www.w3.org/TR/2001/REC-xml-c14n-20010315'
                ]
            ]
        );

        // Substitua o nó original pelo nó assinado
        $signedDom = new \DOMDocument();
        $signedDom->loadXML($signedXml);
        $signedInfNode = $signedDom->documentElement;

        $importedNode = $dom->importNode($signedInfNode, true);
        $infNode->parentNode->replaceChild($importedNode, $infNode);

        return $dom->saveXML();
    }
}