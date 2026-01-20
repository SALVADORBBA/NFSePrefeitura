<?php
namespace NFSePrefeitura\NFSe\PFNatal;

use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use NFePHP\Common\Certificate;
use NFePHP\Common\Signer;
use NFePHP\Common\Strings;

class AssinaturaNatal
{
    private Certificate $certificate;
    private int $algorithm = OPENSSL_ALGO_SHA1;
    private array $canonical = [false, false, null, null];

    private string $nsNfse = 'http://www.abrasf.org.br/nfse.xsd';
    private string $nsDs   = 'http://www.w3.org/2000/09/xmldsig#';

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

    /**
     * Gera 2 assinaturas no padrão do exemplo:
     * - RPS: Signature após InfDeclaracaoPrestacaoServico (dentro do Rps)
     * - LOTE: Signature após LoteRps (irmã do LoteRps, não dentro dele)
     */
    public function assinarLoteRps(string $xml): string
    {
        
        
      
        $xml = $this->normalize($xml);

        // Verifica se o XML contém os nós necessários
        $dom = new DOMDocument();
        $dom->loadXML($xml);
        
        $infDeclaracao = $dom->getElementsByTagName('InfDeclaracaoPrestacaoServico')->item(0);
        $loteRps = $dom->getElementsByTagName('LoteRps')->item(0);
        
        if (!$infDeclaracao) {
            throw new InvalidArgumentException('Nó InfDeclaracaoPrestacaoServico não encontrado no XML');
        }
        
        if (!$loteRps) {
            throw new InvalidArgumentException('Nó LoteRps não encontrado no XML');
        }

        // 0) limpa assinaturas antigas para evitar duplicação
        $xml = $this->removerAssinaturas($xml);

        // 1) assina RPS
        $xml = Signer::sign(
            $this->certificate,
            $xml,
            'InfDeclaracaoPrestacaoServico',
            'Id',
            $this->algorithm,
            $this->canonical
        );

        // 1.1) reposiciona assinatura do RPS (igual seu exemplo)
        $xml = $this->reposicionarAssinaturaRps($xml);

        // 2) assina Lote via CLONE do nó <LoteRps> e injeta a Signature como irmã do LoteRps
        $xml = $this->assinarLoteComoIrmaDoLoteRps($xml);

        // valida: precisa ter 2 signatures
        $count = $this->contarSignatures($xml);
        if ($count < 2) {
            $dbg = $this->debugAssinaturas($xml);
            throw new InvalidArgumentException(
                "Ainda não gerou 2 assinaturas. Encontrado(s): {$count}. Debug: " . json_encode($dbg, JSON_UNESCAPED_UNICODE)
            );
        }

        // Verifica se as assinaturas foram realmente incluídas no XML
        $dom = new DOMDocument();
        $dom->loadXML($xml);
        $signatures = $dom->getElementsByTagName('Signature');
        
        if ($signatures->length === 0) {
            throw new InvalidArgumentException('Nenhuma assinatura foi incluída no XML');
        }

        return Strings::clearXmlString($xml);
    }

    /* =========================
     * Core helpers
     * ========================= */

    private function normalize(string $xml): string
    {
        if (trim($xml) === '') {
            throw new InvalidArgumentException('XML vazio');
        }
        return Strings::clearXmlString($xml);
    }

    private function loadDom(string $xml): array
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;

        if (!$dom->loadXML($xml, LIBXML_NOBLANKS | LIBXML_NOCDATA)) {
            throw new InvalidArgumentException('XML inválido (loadXML falhou)');
        }

        $xp = new DOMXPath($dom);
        $xp->registerNamespace('ns', $this->nsNfse);
        $xp->registerNamespace('ds', $this->nsDs);

        return [$dom, $xp];
    }

    private function contarSignatures(string $xml): int
    {
        [$dom, $xp] = $this->loadDom($xml);
        return $xp->query('//ds:Signature')->length;
    }

    public function debugAssinaturas(string $xml): array
    {
        [$dom, $xp] = $this->loadDom($this->normalize($xml));

        $lote = $xp->query('//ns:LoteRps')->item(0) ?: $dom->getElementsByTagName('LoteRps')->item(0);
        $inf  = $xp->query('//ns:InfDeclaracaoPrestacaoServico')->item(0) ?: $dom->getElementsByTagName('InfDeclaracaoPrestacaoServico')->item(0);

        $loteId = $lote?->attributes?->getNamedItem('Id')?->nodeValue ?? null;
        $rpsId  = $inf?->attributes?->getNamedItem('Id')?->nodeValue ?? null;

        $uris = [];
        foreach ($xp->query('//ds:Reference/@URI') as $n) {
            $uris[] = $n->nodeValue;
        }

        return [
            'lote_id' => $loteId,
            'rps_id' => $rpsId,
            'signature_count' => $xp->query('//ds:Signature')->length,
            'reference_uris' => $uris,
        ];
    }

    private function removerAssinaturas(string $xml): string
    {
        [$dom, $xp] = $this->loadDom($xml);
        foreach ($xp->query('//ds:Signature') as $sig) {
            $sig->parentNode?->removeChild($sig);
        }
        return $dom->saveXML($dom->documentElement);
    }

    /**
     * Move a assinatura do RPS para ficar logo após </InfDeclaracaoPrestacaoServico> dentro do <Rps>
     */
    private function reposicionarAssinaturaRps(string $xml): string
    {
        [$dom, $xp] = $this->loadDom($xml);

        $inf = $xp->query('//ns:InfDeclaracaoPrestacaoServico')->item(0)
            ?: $dom->getElementsByTagName('InfDeclaracaoPrestacaoServico')->item(0);

        if (!$inf) {
            throw new InvalidArgumentException('InfDeclaracaoPrestacaoServico não encontrado');
        }

        $rpsId = $inf->attributes?->getNamedItem('Id')?->nodeValue;
        if (!$rpsId) {
            throw new InvalidArgumentException('Atributo Id do RPS não encontrado');
        }

        // signature que referencia o RPS
        $sigRps = $xp->query('//ds:Signature[.//ds:Reference[@URI="#' . $this->xpEsc($rpsId) . '"]]')->item(0);
        if (!$sigRps) {
            throw new InvalidArgumentException('Assinatura do RPS não encontrada');
        }

        // já está no lugar?
        if ($sigRps->parentNode === $inf->parentNode && $inf->nextSibling === $sigRps) {
            return $dom->saveXML($dom->documentElement);
        }

        $sigRps->parentNode?->removeChild($sigRps);
        $inf->parentNode->insertBefore($sigRps, $inf->nextSibling);

        return $dom->saveXML($dom->documentElement);
    }

    /**
     * Assina o Lote em um XML CLONADO contendo apenas <LoteRps>,
     * pega a <Signature> resultante e insere como irmã do <LoteRps> no XML original.
     * (exatamente como seu exemplo.xml)
     */
    private function assinarLoteComoIrmaDoLoteRps(string $xml): string
    {
        [$dom, $xp] = $this->loadDom($xml);

        $lote = $xp->query('//ns:LoteRps')->item(0) ?: $dom->getElementsByTagName('LoteRps')->item(0);
        if (!$lote) {
            throw new InvalidArgumentException('LoteRps não encontrado');
        }

        $loteId = $lote->attributes?->getNamedItem('Id')?->nodeValue;
        if (!$loteId) {
            throw new InvalidArgumentException('Atributo Id do Lote não encontrado em LoteRps');
        }

        // 1) cria XML mínimo com <LoteRps> como raiz
        $cloneDom = new DOMDocument('1.0', 'UTF-8');
        $cloneDom->preserveWhiteSpace = true;
        $cloneDom->formatOutput = false;

        $loteClone = $cloneDom->importNode($lote, true);
        $cloneDom->appendChild($loteClone);

        $loteXml = $cloneDom->saveXML($cloneDom->documentElement);

        // 2) assina o lote no XML clonado
        $loteAssinado = Signer::sign(
            $this->certificate,
            $loteXml,
            'LoteRps',
            'Id',
            $this->algorithm,
            $this->canonical
        );

        // 3) extrai a Signature criada no clone
        [$dom2, $xp2] = $this->loadDom($loteAssinado);

        // tenta por Reference "#IdDoLote"
        $sigLote = $xp2->query('//ds:Signature[.//ds:Reference[@URI="#' . $this->xpEsc($loteId) . '"]]')->item(0);

        // fallback: última assinatura do clone
        if (!$sigLote) {
            $all = $xp2->query('//ds:Signature');
            if ($all->length > 0) {
                $sigLote = $all->item($all->length - 1);
            }
        }

        if (!$sigLote) { 
            throw new InvalidArgumentException('Assinatura do Lote não foi gerada no clone (Signer::sign não criou ds:Signature)');
        }

        // remove a Signature do clone para importar no XML principal
        $sigLote->parentNode?->removeChild($sigLote);

        // 4) importa a Signature para o DOM principal como IRMÃ do Lote
        $sigImport = $dom->importNode($sigLote, true);

        // se já existir uma assinatura do lote (reprocesso), remove antes
        $exist = $xp->query('//ds:Signature[.//ds:Reference[@URI="#' . $this->xpEsc($loteId) . '"]]')->item(0);
        if ($exist) {
            $exist->parentNode?->removeChild($exist);
        }

        $lote->parentNode->insertBefore($sigImport, $lote->nextSibling);

        return $dom->saveXML($dom->documentElement);
    }

    private function xpEsc(string $v): string
    {
        return str_replace(['"', "'"], '', $v);
    }
}