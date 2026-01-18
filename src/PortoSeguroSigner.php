<?php
namespace NFSePrefeitura\NFSe;

use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use NFePHP\Common\Certificate;
use NFePHP\Common\Strings;
use NFePHP\Common\Signer;

class AssinadorXMLSeguro
{
    private Certificate $certificate;

    /**
     * @param string $certPath Caminho do PFX
     * @param string $certPassword Senha do PFX
     */
    public function __construct(string $certPath, string $certPassword)
    {
        if (!$certPath || !is_file($certPath)) {
            throw new InvalidArgumentException("Certificado não encontrado: {$certPath}");
        }
        if ($certPassword === '') {
            throw new InvalidArgumentException("Senha do certificado não informada.");
        }

        $this->certificate = Certificate::readPfx(file_get_contents($certPath), $certPassword);
    }

    /**
     * Assina o XML no padrão ABRASF, referenciando o atributo Id do nó alvo.
     *
     * @param string $xml
     * @param string $tagToSign Ex: 'InfDeclaracaoPrestacaoServico' ou 'LoteRps'
     * @param string $idAttr Ex: 'Id'
     * @return string XML assinado
     */
    public function assinarXML(string $xml, string $tagToSign = 'InfDeclaracaoPrestacaoServico', string $idAttr = 'Id'): string
    {
        $xml = trim($xml);
        if ($xml === '') {
            throw new InvalidArgumentException("XML vazio para assinatura.");
        }

        // limpa lixo invisível
        $xml = Strings::clearXmlString($xml);

        // valida se existe o nó e se tem Id
        $this->assertHasId($xml, $tagToSign, $idAttr);

        /**
         * NÃO force OPENSSL_ALGO_SHA1 aqui!
         * Em várias versões do sped-common isso causa:
         * openssl_sign(): Unknown digest algorithm
         *
         * A assinatura padrão do NFePHP para legado usa RSA-SHA1 quando necessário.
         */
        $canonical = [false, false, null, null];

        return Signer::sign(
            $this->certificate,
            $xml,
            $tagToSign,
            $idAttr,
            null,        // <-- não forçar algoritmo
            $canonical
        );
    }

    /**
     * Garante que o nó existe e que tem atributo Id preenchido.
     */
    private function assertHasId(string $xml, string $tagName, string $idAttr): void
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        if (@$dom->loadXML($xml) === false) {
            throw new InvalidArgumentException("XML inválido (não foi possível carregar no DOMDocument).");
        }

        $xpath = new DOMXPath($dom);
        // ABRASF namespace
        $xpath->registerNamespace('nfse', 'http://www.abrasf.org.br/nfse.xsd');

        // tenta com namespace e sem namespace (porque alguns XMLs vem sem prefixo)
        $node = $xpath->query("//nfse:{$tagName}")->item(0);
        if (!$node) {
            $node = $dom->getElementsByTagName($tagName)->item(0);
        }

        if (!$node) {
            throw new InvalidArgumentException("Nó '{$tagName}' não encontrado no XML.");
        }

        $id = $node->getAttribute($idAttr);
        if (!$id) {
            throw new InvalidArgumentException("Atributo '{$idAttr}' não encontrado no nó '{$tagName}'.");
        }
    }
}
