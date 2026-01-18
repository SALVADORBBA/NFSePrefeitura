<?php
namespace NFSePrefeitura\NFSe;

class AssinadorXMLSeguro
{
    private $certificado;

    public function assinarXML($xml, $node, $certificado)
    {
        try {
            $this->validarParametros($xml, $node, $certificado);
            $this->certificado = $certificado;

            $dom = new \DOMDocument();
            $dom->preserveWhiteSpace = true;
            $dom->loadXML($xml);

            $nodes = $this->selecionarNos($dom->childNodes, $node);

            if (count($nodes) === 0) {
                return $xml;
            }

            return $this->processarAssinatura($dom, $nodes);
        } catch (\Exception $e) {
            throw new \Exception("Erro ao assinar XML: " . $e->getMessage(), 0, $e);
        }
    }

    private function validarParametros($xml, $node, $certificado)
    {
        if (empty($xml)) {
            throw new \InvalidArgumentException("XML não pode ser nulo ou vazio");
        }

        if (empty($node)) {
            throw new \InvalidArgumentException("Nó raiz não pode ser nulo ou vazio");
        }

        if ($certificado === null) {
            throw new \InvalidArgumentException("Certificado não pode ser nulo");
        }
    }

    private function selecionarNos($nodes, $nodeName)
    {
        $lista = [];

        foreach ($nodes as $node) {
            if ($node->nodeName === $nodeName) {
                $lista[] = $node;
            }

            if ($node->hasChildNodes()) {
                $lista = array_merge($lista, $this->selecionarNos($node->childNodes, $nodeName));
            }
        }

        return $lista;
    }

    private function processarAssinatura($dom, $nodes)
    {
        foreach ($nodes as $node) {
            $assinado = $this->assinarElemento($node);
            $importado = $dom->importNode($assinado, true);
            $node->appendChild($importado);
        }

        return $dom->saveXML();
    }

    private function assinarElemento($elemento)
    {
        // Implementação da assinatura digital em PHP
        // Esta parte precisaria usar a extensão OpenSSL do PHP
        // e pode variar dependendo da implementação específica
        throw new \Exception("Implementação da assinatura digital em PHP requer configuração adicional");
    }

    private function obterId($elemento)
    {
        $id = $elemento->getAttribute('Id') ?: $elemento->getAttribute('id');

        if (empty($id)) {
            throw new \Exception("Elemento com atributo ID não encontrado no XML");
        }

        return "#" . $id;
    }
}