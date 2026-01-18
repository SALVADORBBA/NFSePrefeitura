<?php

namespace NotasFiscais\Abrasf;

use NFePHP\Common\Certificate;
use NFePHP\Common\Signer;

class PortoSeguroSigner
{
    protected $certificate;

    public function __construct(string $certPath, string $certPassword)
    {
        $pfx = file_get_contents($certPath);
        if ($pfx === false) {
            throw new \RuntimeException("Não foi possível ler o certificado: {$certPath}");
        }
        $this->certificate = Certificate::readPfx($pfx, $certPassword);
    }

    /**
     * Assina o InfDeclaracaoPrestacaoServico (por @Id) e garante que
     * o <Signature> fique dentro do <Rps> (como irmão do Inf...),
     * e NÃO no final do XML.
     */
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
        $xp->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        /** @var \DOMElement|null $inf */
        $inf = $xp->query('//nfse:InfDeclaracaoPrestacaoServico[@Id]')->item(0);
        if (!$inf) {
            throw new \RuntimeException("Nó InfDeclaracaoPrestacaoServico com Id não encontrado.");
        }

        $id = $inf->getAttribute('Id');
        if (!$id) {
            throw new \RuntimeException("Atributo Id vazio em InfDeclaracaoPrestacaoServico.");
        }

        // 1) Assina o XML completo (não assine um fragmento!)
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

        // 2) Agora reposiciona o Signature: tem que ficar dentro do <Rps>
        $signedDom = new \DOMDocument('1.0', 'UTF-8');
        $signedDom->preserveWhiteSpace = true;
        $signedDom->formatOutput = false;
        $signedDom->loadXML($signedXml);

        $xp2 = new \DOMXPath($signedDom);
        $xp2->registerNamespace('nfse', 'http://www.abrasf.org.br/nfse.xsd');
        $xp2->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        // acha o Inf do Id
        /** @var \DOMElement|null $inf2 */
        $inf2 = $xp2->query('//nfse:InfDeclaracaoPrestacaoServico[@Id="'.$id.'"]')->item(0);
        if (!$inf2) {
            throw new \RuntimeException("InfDeclaracaoPrestacaoServico Id={$id} não encontrado no XML assinado.");
        }

        // acha assinatura (às vezes o Signer joga no root / fora do Rps)
        /** @var \DOMElement|null $sig */
        $sig = $xp2->query('//ds:Signature[.//ds:Reference[@URI="#'.$id.'"]]')->item(0);
        if (!$sig) {
            // fallback: primeira assinatura
            $sig = $xp2->query('//ds:Signature')->item(0);
        }
        if (!$sig) {
            throw new \RuntimeException("Signature não encontrada após assinar.");
        }

        // acha o <Rps> pai do Inf (o Rps "externo")
        /** @var \DOMElement|null $rpsOuter */
        $rpsOuter = $xp2->query('ancestor::nfse:Rps[1]', $inf2)->item(0);
        if (!$rpsOuter) {
            throw new \RuntimeException("Rps pai do InfDeclaracaoPrestacaoServico não encontrado.");
        }

        // se a assinatura já estiver dentro do Rps correto, beleza
        $sigParent = $sig->parentNode;
        if ($sigParent && $sigParent->isSameNode($rpsOuter)) {
            return $signedDom->saveXML($signedDom->documentElement);
        }

        // remove a assinatura de onde estiver (root, outro lugar etc.)
        $sig = $sigParent->removeChild($sig);

        // e coloca como irmão do Inf... dentro do Rps
        if ($inf2->nextSibling) {
            $rpsOuter->insertBefore($sig, $inf2->nextSibling);
        } else {
            $rpsOuter->appendChild($sig);
        }

        return $signedDom->saveXML($signedDom->documentElement);
    }
}
