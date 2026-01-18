<?php
namespace NFSePrefeitura\NFSe;

use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;
use RuntimeException;
use NFePHP\Common\Certificate;
use NFePHP\Common\Strings;
use NFePHP\Common\Signer;

class NFSeNacionalSigner
{
    private Certificate $certificate;

    /** ABRASF legado */
    private int $algorithm = OPENSSL_ALGO_SHA1;

    /**
     * Canonical do NFePHP normalmente aceita um array de 4 posições.
     * No C# é C14N "padrão" (sem comments).
     */
    private array $canonical = [true, false, null, null];

    public function __construct(Certificate $certificate)
    {
        $this->certificate = $certificate;
    }

    /**
     * Helper estilo seu: lê PFX e assina.
     * $nodeName = nome do nó a assinar (ex: "Rps" ou "InfDeclaracaoPrestacaoServico" ou "EnviarLoteRpsEnvio")
     */
    public static function assinarXml(
        string $xml,
        string $certPath,
        string $certPassword,
        string $nodeName
    ): string {
        if (!is_file($certPath)) {
            throw new InvalidArgumentException('Certificado não encontrado: ' . $certPath);
        }

        $cert = Certificate::readPfx(file_get_contents($certPath), $certPassword);

        $self = new self($cert);
        return $self->assinarNo($xml, $nodeName);
    }

    /**
     * Assina todos os nós com local-name() == $nodeName e
     * anexa <Signature> como filho do próprio nó (igual C#).
     */
    public function assinarNo(string $xml, string $nodeName): string
    {
        $xml = trim($xml);
        if ($xml === '') {
            throw new InvalidArgumentException('XML vazio para assinatura.');
        }

        $nodeName = trim($nodeName);
        if ($nodeName === '') {
            throw new InvalidArgumentException('Nome do nó para assinatura está vazio.');
        }

        $xml = Strings::clearXmlString($xml);

        $dom = $this->loadDomPreserveWhitespace($xml);

        // Seleciona por local-name() para ignorar namespaces (ABRASF)
        $xp = new DOMXPath($dom);
        $nodes = $xp->query('//*[local-name()="'.$this->xpathQuote($nodeName).'"]');

        if (!$nodes || $nodes->length === 0) {
            // igual C#: se não achou, retorna o xml original
            return $dom->saveXML($dom->documentElement);
        }

        /** @var DOMElement $el */
        foreach ($nodes as $el) {
            if (!$el instanceof DOMElement) {
                continue;
            }

            // Se já tem Signature dentro, não duplica
            if ($this->hasSignatureChild($el)) {
                continue;
            }

            $id = $this->obterIdDentroDoNo($el); // "#RPS00001" (igual C#)
            if ($id === '') {
                throw new RuntimeException("Elemento ID nao encontrado no xml para o nó {$nodeName}");
            }

            // Cria um XML "mini" só com esse elemento e assina
            $miniXml = $this->exportSingleElementAsXml($el);

            $signedMini = $this->signMiniXml($miniXml, $nodeName, $id);

            // Extrai apenas o <Signature> do mini assinado
            $sigNode = $this->extractSignatureNode($signedMini);

            // Importa pro doc original e anexa como filho do elemento alvo
            $imported = $dom->importNode($sigNode, true);
            $el->appendChild($imported);
        }

        return $dom->saveXML($dom->documentElement);
    }

    /**
     * Conveniência no padrão do exemplo C#:
     * assina "Rps" e depois assina "EnviarLoteRpsEnvio"
     */
    public function assinarLotePadraoCSharp(string $xml): string
    {
        $xml = $this->assinarNo($xml, 'Rps');
        $xml = $this->assinarNo($xml, 'EnviarLoteRpsEnvio');
        return $xml;
    }

    /* =========================================================
     * Internals
     * ========================================================= */

    private function loadDomPreserveWhitespace(string $xml): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        // C# usa PreserveWhitespace = true
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;

        $old = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($xml);
        $errs = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($old);

        if (!$ok) {
            $msg = 'Falha ao carregar XML.';
            if (!empty($errs)) {
                $msg .= ' ' . trim($errs[0]->message) . " (linha {$errs[0]->line})";
            }
            throw new RuntimeException($msg);
        }

        return $dom;
    }

    /**
     * Igual ao C#:
     * XmlAttribute idElemento = doc.SelectSingleNode(".//@Id | .//@id");
     * return "#" + idElemento.Value;
     */
   private function obterIdDentroDoNo(\DOMElement $el, string $nodeName = ''): string
{
    $tmp = new \DOMDocument('1.0', 'UTF-8');
    $tmp->preserveWhiteSpace = true;
    $tmp->formatOutput = false;
    $tmp->appendChild($tmp->importNode($el, true));

    $xp = new \DOMXPath($tmp);

    // 1) Primeiro tenta igual ao C#: .//@Id | .//@id
    $idAttr = $xp->query('.//@Id | .//@id', $tmp->documentElement)->item(0);
    if ($idAttr && $idAttr->nodeValue) {
        $val = trim((string)$idAttr->nodeValue);
        if ($val !== '') {
            return '#' . $val;
        }
    }

    // 2) Fallback ABRASF: se o nó é Rps, o Id costuma estar em InfDeclaracaoPrestacaoServico
    if (strcasecmp($nodeName, 'Rps') === 0) {
        $inf = $xp->query('//*[local-name()="InfDeclaracaoPrestacaoServico"][@Id]')->item(0);
        if ($inf instanceof \DOMElement) {
            $val = trim($inf->getAttribute('Id'));
            if ($val !== '') {
                return '#' . $val;
            }
        }
    }

    return '';
}


    private function exportSingleElementAsXml(DOMElement $el): string
    {
        $mini = new DOMDocument('1.0', 'UTF-8');
        $mini->preserveWhiteSpace = true;
        $mini->formatOutput = false;
        $mini->appendChild($mini->importNode($el, true));
        return $mini->saveXML($mini->documentElement);
    }

    private function hasSignatureChild(DOMElement $el): bool
    {
        foreach ($el->childNodes as $c) {
            if ($c instanceof DOMElement && $c->localName === 'Signature') {
                return true;
            }
        }
        return false;
    }

    /**
     * Aqui é o ponto crítico:
     * usamos o Signer do NFePHP para assinar o mini XML e gerar <Signature>.
     *
     * Como existem variações de assinatura do método Signer::sign entre versões,
     * eu tentei 2 formas comuns:
     *  - sem passar $idValue (Signer procura Id no nó)
     *  - passando $idValue explicitamente
     */
    private function signMiniXml(string $miniXml, string $nodeName, string $uriWithHash): string
    {
        // uriWithHash = "#RPS00001"
        // idValue     = "RPS00001"
        $idValue = ltrim($uriWithHash, '#');

        // Tentativa 1: assinatura clássica (Signer encontra o Id)
        try {
            return Signer::sign(
                $this->certificate,
                $miniXml,
                $nodeName,
                'Id',
                $this->algorithm,
                $this->canonical
            );
        } catch (\Throwable $e1) {
            // Tentativa 2: versões que exigem o valor do Id explicitamente
            try {
                return Signer::sign(
                    $this->certificate,
                    $miniXml,
                    $nodeName,
                    'Id',
                    $idValue,
                    $this->algorithm,
                    $this->canonical
                );
            } catch (\Throwable $e2) {
                throw new RuntimeException(
                    "Falha ao assinar mini XML ({$nodeName}). ".
                    "Erro1: {$e1->getMessage()} | Erro2: {$e2->getMessage()}"
                );
            }
        }
    }

    private function extractSignatureNode(string $signedMiniXml): DOMElement
    {
        $dom = $this->loadDomPreserveWhitespace($signedMiniXml);
        $xp  = new DOMXPath($dom);

        // tenta pegar ds:Signature e fallback por local-name()
        $sig = $xp->query('//*[local-name()="Signature"]')->item(0);

        if (!$sig instanceof DOMElement) {
            throw new RuntimeException('Signature não encontrada no XML assinado.');
        }

        return $sig;
    }

    private function xpathQuote(string $value): string
    {
        // segurança mínima em Xpath string literal
        return str_replace('"', '\"', $value);
    }
}
