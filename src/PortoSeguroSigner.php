<?php

namespace NotasFiscais\Abrasf;

use NFePHP\Common\Certificate;
use NFePHP\Common\Signer;
use NFePHP\Common\Strings;

class PortoSeguroSigner
{
    protected Certificate $certificate;
    private $algorithm = OPENSSL_ALGO_SHA1;
    private $canonical = [false, false, null, null];

    public function __construct(string $certPath, string $certPassword)
    {
        $pfx = file_get_contents($certPath);
        if ($pfx === false) {
            throw new \RuntimeException("Não foi possível ler o certificado: {$certPath}");
        }
        $this->certificate = Certificate::readPfx($pfx, $certPassword);
    }

    public function signRps(string $xml): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;

        if (!$dom->loadXML($xml)) {
            throw new \RuntimeException("XML inválido.");
        }

        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('nfse', 'http://www.abrasf.org.br/nfse.xsd');

        /** @var \DOMElement|null $inf */
        $inf = $xp->query('//nfse:InfDeclaracaoPrestacaoServico[@Id]')->item(0);
        if (!$inf) {
            throw new \RuntimeException("InfDeclaracaoPrestacaoServico com @Id não encontrado.");
        }

        $id = trim($inf->getAttribute('Id'));
        if ($id === '') {
            throw new \RuntimeException("Atributo Id vazio em InfDeclaracaoPrestacaoServico.");
        }

        // 1) Assina o XML COMPLETO
        $signedXml = Signer::sign(
            $this->certificate,
            $dom->saveXML($dom->documentElement),
            'InfDeclaracaoPrestacaoServico',
            'Id',
            $id,
            [
                'canonical' => true,
                'signatureAlgorithm' => 'http://www.w3.org/2000/09/xmldsig#rsa-sha1',
                'digestAlgorithm'    => 'http://www.w3.org/2000/09/xmldsig#sha1',
                'transformAlgorithm' => [
                    'http://www.w3.org/2000/09/xmldsig#enveloped-signature',
                    'http://www.w3.org/TR/2001/REC-xml-c14n-20010315',
                ],
            ]
        );

        // 2) Carrega assinado e reposiciona Signature para dentro do <Rps> externo
        $sd = new \DOMDocument('1.0', 'UTF-8');
        $sd->preserveWhiteSpace = true;
        $sd->formatOutput = false;
        if (!$sd->loadXML($signedXml)) {
            throw new \RuntimeException("Falha ao carregar XML assinado.");
        }

        $xp2 = new \DOMXPath($sd);
        $xp2->registerNamespace('nfse', 'http://www.abrasf.org.br/nfse.xsd');
        $xp2->registerNamespace('ds',   'http://www.w3.org/2000/09/xmldsig#');

        /** @var \DOMElement|null $inf2 */
        $inf2 = $xp2->query('//nfse:InfDeclaracaoPrestacaoServico[@Id="'.$id.'"]')->item(0);
        if (!$inf2) {
            throw new \RuntimeException("InfDeclaracaoPrestacaoServico Id={$id} não encontrado no XML assinado.");
        }

        // pega a assinatura que referencia esse Id
        /** @var \DOMElement|null $sig */
        $sig = $xp2->query('//ds:Signature[.//ds:Reference[@URI="#'.$id.'"]]')->item(0);
        if (!$sig) {
            throw new \RuntimeException("Signature com Reference #{$id} não encontrada.");
        }

        // acha o <Rps> EXTERNO (pai do InfDeclaracaoPrestacaoServico)
        /** @var \DOMElement|null $rpsOuter */
        $rpsOuter = $xp2->query('ancestor::nfse:Rps[1]', $inf2)->item(0);
        if (!$rpsOuter) {
            throw new \RuntimeException("Rps externo (pai do InfDeclaracaoPrestacaoServico) não encontrado.");
        }

        // se a assinatura não está dentro do Rps externo, move
        if (!$sig->parentNode->isSameNode($rpsOuter)) {
            $sig = $sig->parentNode->removeChild($sig);

            // insere logo após o InfDeclaracaoPrestacaoServico dentro do Rps externo
            if ($inf2->nextSibling) {
                $rpsOuter->insertBefore($sig, $inf2->nextSibling);
            } else {
                $rpsOuter->appendChild($sig);
            }
        }

        return $sd->saveXML($sd->documentElement);
    }

    public function signNFSeX(string $xml): string
    {
        if (empty($xml)) {
            throw new \RuntimeException("O argumento xml passado para ser assinado está vazio.");
        }
        
        $xml = Strings::clearXmlString($xml);

        $signed = Signer::sign(
            $this->certificate,
            $xml,
            'InfDeclaracaoPrestacaoServico',
            'Id',
            $this->algorithm,
            $this->canonical
        );
        
        return $signed;
    }
}