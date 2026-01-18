<?php

namespace NFSePrefeitura\NFSe;

use DOMDocument;
use DOMXPath;
use DOMElement;
use InvalidArgumentException;
use NFePHP\Common\Certificate;
use NFePHP\Common\Signer;
use NFePHP\Common\Strings;

class AssinadorXMLSeguro
{
    private Certificate $certificate;
    private int $algorithm = OPENSSL_ALGO_SHA1;
    private array $canonical = [false, false, null, null];

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
        if (trim($xml) === '') {
            throw new InvalidArgumentException('XML vazio');
        }

        $xml = Strings::clearXmlString($xml);

        /** 1️⃣ Assina o RPS (NÓ CORRETO) */
        $xml = Signer::sign(
            $this->certificate,
            $xml,
            'Rps',
            'Id',
            $this->algorithm,
            $this->canonical
        );

        /** 2️⃣ Move assinatura do RPS */
        $xml = $this->reposicionarAssinaturaRps($xml);

        /** 3️⃣ Assina o LOTE */
        $xml = Signer::sign(
            $this->certificate,
            $xml,
            'LoteRps',
            'Id',
            $this->algorithm,
            $this->canonical
        );

        /** 4️⃣ Move assinatura do LOTE */
        $xml = $this->reposicionarAssinaturaLote($xml);

        return Strings::clearXmlString($xml);
    }

    /**
     * Assinatura do RPS:
     * Após </Rps> e antes de </ListaRps>
     */
    private function reposicionarAssinaturaRps(string $xml): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($xml);

        $xp = new DOMXPath($dom);

        $rps = $xp->query('//*[local-name()="Rps"]')->item(0);
        $sig = $xp->query(
            '//*[local-name()="Signature"]
             [descendant::*[local-name()="Reference" and starts-with(@URI,"#Rps")]]'
        )->item(0);

        if (!$rps instanceof DOMElement || !$sig instanceof DOMElement) {
            return $xml;
        }

        $sig->parentNode->removeChild($sig);

        /** insere após </Rps> */
        $rps->parentNode->insertBefore(
            $sig,
            $this->nextElementSibling($rps)
        );

        return $dom->saveXML();
    }

    /**
     * Assinatura do LOTE:
     * Após </LoteRps> e antes de </EnviarLoteRpsEnvio>
     */
    private function reposicionarAssinaturaLote(string $xml): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($xml);

        $xp = new DOMXPath($dom);

        $lote = $xp->query('//*[local-name()="LoteRps"]')->item(0);
        $sig = $xp->query(
            '//*[local-name()="Signature"]
             [descendant::*[local-name()="Reference" and starts-with(@URI,"#Lote")]]'
        )->item(0);

        if (!$lote instanceof DOMElement || !$sig instanceof DOMElement) {
            return $xml;
        }

        $sig->parentNode->removeChild($sig);

        /** insere após </LoteRps> */
        $lote->parentNode->insertBefore(
            $sig,
            $this->nextElementSibling($lote)
        );

        return $dom->saveXML();
    }

    /**
     * Ignora nós de texto (quebra de linha)
     */
    private function nextElementSibling(DOMElement $node): ?DOMElement
    {
        $next = $node->nextSibling;
        while ($next && !$next instanceof DOMElement) {
            $next = $next->nextSibling;
        }
        return $next;
    }
}
