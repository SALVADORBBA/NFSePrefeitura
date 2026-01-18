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
        // Extrai o Id do InfDeclaracaoPrestacaoServico
        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('ns', 'http://www.abrasf.org.br/nfse.xsd');
        $infNode = $xpath->query('//ns:InfDeclaracaoPrestacaoServico')->item(0);
        $id = $infNode->getAttribute('Id');

        // Assina o nó InfDeclaracaoPrestacaoServico
        $signedXml = Signer::sign(
            $this->certificate,
            $xml,
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

        return $signedXml;
    }
}