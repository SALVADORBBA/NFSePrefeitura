<?php
namespace NFSePrefeitura\NFSe\PFChapeco;

use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;

class TransformadorChapeco
{
    /**
     * Converte um XML GerarNfseEnvio (RPS "solto") em EnviarLoteRpsEnvio (lote ABRASF 2.04),
     * para uso no método RecepcionarLoteRps.
     *
     * - Extrai Prestador (Cnpj/InscricaoMunicipal) do XML original
     * - Copia o nó <Rps> inteiro para dentro de <ListaRps>
     * - Define <NumeroLote> e <QuantidadeRps>
     * - Remove atributo Id de <InfDeclaracaoPrestacaoServico> (se existir)
     */
    public static function gerarLoteEnvioFromGerarNfse(string $xmlGerarNfseEnvio, string $numeroLote): string
    {
        $xmlGerarNfseEnvio = trim($xmlGerarNfseEnvio);
        if ($xmlGerarNfseEnvio === '') {
            throw new InvalidArgumentException('XML GerarNfseEnvio vazio.');
        }

        if (trim($numeroLote) === '') {
            throw new InvalidArgumentException('NumeroLote vazio.');
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        if (@$dom->loadXML($xmlGerarNfseEnvio) !== true) {
            throw new InvalidArgumentException('XML GerarNfseEnvio inválido (não carregou no DOM).');
        }

        $xp = new DOMXPath($dom);
        $xp->registerNamespace('ns', 'http://www.abrasf.org.br/nfse.xsd');

        // 1) pega o <Rps> (dentro de <GerarNfseEnvio>)
        $rpsNode = $xp->query('//*[local-name()="GerarNfseEnvio"]/*[local-name()="Rps"]')->item(0);
        if (!$rpsNode instanceof DOMElement) {
            // fallback: se tiver só um <Rps> no doc
            $rpsNode = $xp->query('//*[local-name()="Rps"]')->item(0);
        }
        if (!$rpsNode instanceof DOMElement) {
            throw new InvalidArgumentException('Nó <Rps> não encontrado no GerarNfseEnvio.');
        }

        // 2) extrai Prestador CNPJ/IM do XML
        $cnpjNode = $xp->query('//*[local-name()="Prestador"]/*[local-name()="CpfCnpj"]/*[local-name()="Cnpj"]')->item(0);
        $imNode   = $xp->query('//*[local-name()="Prestador"]/*[local-name()="InscricaoMunicipal"]')->item(0);

        $cnpj = $cnpjNode ? preg_replace('/\D+/', '', (string) $cnpjNode->nodeValue) : '';
        $im   = $imNode ? preg_replace('/\D+/', '', (string) $imNode->nodeValue) : '';

        if ($cnpj === '' || $im === '') {
            throw new InvalidArgumentException('Prestador ausente no XML (Cnpj/InscricaoMunicipal não encontrados).');
        }

        // 3) cria o lote
        $out = new DOMDocument('1.0', 'UTF-8');
        $out->preserveWhiteSpace = false;
        $out->formatOutput = false;

        $enviar = $out->createElementNS('http://www.abrasf.org.br/nfse.xsd', 'EnviarLoteRpsEnvio');
        $out->appendChild($enviar);

        $lote = $out->createElement('LoteRps');
        $lote->setAttribute('versao', '2.04');
        $enviar->appendChild($lote);

        $lote->appendChild($out->createElement('NumeroLote', $numeroLote));

        $prest = $out->createElement('Prestador');
        $lote->appendChild($prest);

        $cpfCnpj = $out->createElement('CpfCnpj');
        $prest->appendChild($cpfCnpj);
        $cpfCnpj->appendChild($out->createElement('Cnpj', $cnpj));
        $prest->appendChild($out->createElement('InscricaoMunicipal', $im));

        $lote->appendChild($out->createElement('QuantidadeRps', '1'));

        $lista = $out->createElement('ListaRps');
        $lote->appendChild($lista);

        // importa o <Rps> inteiro
        $importedRps = $out->importNode($rpsNode, true);
        $lista->appendChild($importedRps);

        // 4) remove Id="pfx..." (ou qualquer Id) do InfDeclaracaoPrestacaoServico
        $xp2 = new DOMXPath($out);
        $xp2->registerNamespace('ns', 'http://www.abrasf.org.br/nfse.xsd');

        $inf = $xp2->query('//*[local-name()="InfDeclaracaoPrestacaoServico"]')->item(0);
        if ($inf instanceof DOMElement && $inf->hasAttribute('Id')) {
            $inf->removeAttribute('Id');
        }

        return $out->saveXML();
    }
}
