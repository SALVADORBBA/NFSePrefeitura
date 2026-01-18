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

        if (!$infNode) {
            throw new \Exception('Nó InfDeclaracaoPrestacaoServico não encontrado.');
        }

        $id = $infNode->getAttribute('Id');
        if (empty($id)) {
            throw new \Exception('Atributo Id não encontrado no nó InfDeclaracaoPrestacaoServico.');
        }

        // Assina o nó InfDeclaracaoPrestacaoServico e inclui o Signature como filho direto
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

        // Remove declaração XML do nó assinado
        $signedXml = preg_replace('/<\?xml.*?\?>/', '', $signedXml);

        $signedDom = new \DOMDocument();
        $signedDom->preserveWhiteSpace = false;
        $signedDom->formatOutput = false;
        $signedDom->loadXML($signedXml);
        $signedInfNode = $signedDom->documentElement;

        // Substitui o nó original pelo nó assinado (com Signature como filho)
        $importedNode = $dom->importNode($signedInfNode, true);
        $infNode->parentNode->replaceChild($importedNode, $infNode);

        // Retorna o XML sem declaração XML
        return $dom->saveXML($dom->documentElement);
    }
}