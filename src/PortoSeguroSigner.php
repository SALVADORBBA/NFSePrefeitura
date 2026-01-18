<?php

namespace NotasFiscais\Abrasf;

use NFePHP\Common\Certificate;
use NFePHP\Common\Signer;

class PortoSeguroSigner
{
    /** @var \NFePHP\Common\Certificate */
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
     * Assina TODOS os RPS do XML (cada InfDeclaracaoPrestacaoServico com @Id),
     * e posiciona <Signature> dentro de <Rps> como irmão do InfDeclaracaoPrestacaoServico,
     * conforme o modelo ABRASF (como no seu EnviarLoteRpsEnvio.xml).
     */
    public function signRps(string $xml): string
    {
        // Importante: preservar whitespace para não alterar o conteúdo antes/depois da assinatura
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;

        if (!$dom->loadXML($xml, LIBXML_NOBLANKS | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            // Tenta de novo sem LIBXML_NOBLANKS caso o XML tenha formatação específica
            $dom = new \DOMDocument('1.0', 'UTF-8');
            $dom->preserveWhiteSpace = true;
            $dom->formatOutput = false;

            if (!$dom->loadXML($xml)) {
                throw new \RuntimeException("XML inválido: não foi possível carregar.");
            }
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('nfse', 'http://www.abrasf.org.br/nfse.xsd');

        /** @var \DOMNodeList $infList */
        $infList = $xpath->query('//nfse:InfDeclaracaoPrestacaoServico[@Id]');
        if (!$infList || $infList->length === 0) {
            throw new \RuntimeException("Nenhum nó InfDeclaracaoPrestacaoServico com atributo Id foi encontrado.");
        }

        // Vamos assinar um por um, atualizando o XML a cada passo (mais compatível com o Signer)
        $currentXml = $dom->saveXML($dom->documentElement);

        foreach ($infList as $infNode) {
            /** @var \DOMElement $infNode */
            $id = $infNode->getAttribute('Id');
            if (!$id) {
                continue;
            }

            // 1) Assina no XML COMPLETO (evita perder namespaces do contexto)
            $signed = Signer::sign(
                $this->certificate,
                $currentXml,
                'InfDeclaracaoPrestacaoServico',
                'Id',
                $id,
                [
                    // Padrão do seu XML modelo: RSA-SHA1 / SHA1 / C14N 20010315
                    'canonical' => true,
                    'signatureAlgorithm' => 'http://www.w3.org/2000/09/xmldsig#rsa-sha1',
                    'digestAlgorithm'    => 'http://www.w3.org/2000/09/xmldsig#sha1',
                    'transformAlgorithm' => [
                        'http://www.w3.org/2000/09/xmldsig#enveloped-signature',
                        'http://www.w3.org/TR/2001/REC-xml-c14n-20010315',
                    ],
                    // NÃO força assinatura como filho do Inf... (isso costuma dar E324 em alguns provedores)
                    // Vamos reposicionar para ficar dentro do <Rps>
                ]
            );

            // 2) Reposiciona <Signature> para o formato do seu modelo:
            // <Rps><InfDeclaracaoPrestacaoServico .../> <Signature/></Rps>
            $signed = $this->moveSignatureToRpsSibling($signed, $id);

            // Atualiza para a próxima assinatura
            $currentXml = $signed;
        }

        // Retorna sem a declaração XML (geralmente os provedores aceitam melhor assim)
        $finalDom = new \DOMDocument('1.0', 'UTF-8');
        $finalDom->preserveWhiteSpace = true;
        $finalDom->formatOutput = false;
        $finalDom->loadXML($currentXml);

        return $finalDom->saveXML($finalDom->documentElement);
    }

    /**
     * Se o Signer inseriu <Signature> dentro do Inf... (enveloped),
     * move a assinatura para ficar como irmã do Inf... dentro do nó <Rps>.
     */
    private function moveSignatureToRpsSibling(string $xml, string $id): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;
        $dom->loadXML($xml);

        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('nfse', 'http://www.abrasf.org.br/nfse.xsd');
        $xp->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        /** @var \DOMElement|null $inf */
        $inf = $xp->query('//nfse:InfDeclaracaoPrestacaoServico[@Id="'.$id.'"]')->item(0);
        if (!$inf) {
            return $xml;
        }

        // Signature pode estar:
        // a) dentro do Inf...
        // b) já como irmão (dentro do Rps)
        $sigInside = $xp->query('./ds:Signature', $inf)->item(0);
        if (!$sigInside) {
            // Já está fora, nada a fazer
            return $xml;
        }

        $rps = $inf->parentNode; // no seu modelo, parent é o <Rps>
        if (!$rps instanceof \DOMElement) {
            return $xml;
        }

        // Remove do Inf... e adiciona depois do Inf... no Rps
        $sigNode = $inf->removeChild($sigInside);

        // Insere logo após o Inf...
        if ($inf->nextSibling) {
            $rps->insertBefore($sigNode, $inf->nextSibling);
        } else {
            $rps->appendChild($sigNode);
        }

        return $dom->saveXML($dom->documentElement);
    }
}
