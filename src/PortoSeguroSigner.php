<?php


namespace NFSePrefeitura\NFSe;

use NFePHP\Common\Certificate;
use NFePHP\Common\Signer;

class PortoSeguroSigner
{
    private $certificate;

    public function __construct($certPath, $certPassword)
    {
        $this->certificate = Certificate::readPfx($certPath, $certPassword);
    }

    /**
     * Assina o XML no nó <InfDeclaracaoPrestacaoServico Id="...">
     * @param string $xml
     * @return string
     * @throws \Exception
     */
    public function signRps($xml)
    {
        // Carrega o XML
        $dom = new \DOMDocument('1.0', 'utf-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($xml);

        // Busca o nó a ser assinado
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('ns', 'http://www.abrasf.org.br/nfse.xsd');
        $nodes = $xpath->query('//ns:InfDeclaracaoPrestacaoServico');

        if ($nodes->length === 0) {
            throw new \Exception('Tag InfDeclaracaoPrestacaoServico não encontrada para assinatura.');
        }

        $node = $nodes->item(0);
        $id = $node->getAttribute('Id');
        if (empty($id)) {
            throw new \Exception('Atributo Id não encontrado na tag InfDeclaracaoPrestacaoServico.');
        }

        // Assina o nó
        $signedXml = Signer::sign(
            $this->certificate,
            $xml,
            'InfDeclaracaoPrestacaoServico',
            '#' . $id, // Referência correta
            OPENSSL_ALGO_SHA1,
            [
                'canonical' => 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315',
                'signature' => 'http://www.w3.org/2000/09/xmldsig#rsa-sha1',
                'digest' => 'http://www.w3.org/2000/09/xmldsig#sha1',
                'transforms' => [
                    'http://www.w3.org/2000/09/xmldsig#enveloped-signature',
                    'http://www.w3.org/TR/2001/REC-xml-c14n-20010315'
                ]
            ]
        );

        return $signedXml;
    }
}